<?php
class static_press {
	const FETCH_LIMIT        =   5;
	const FETCH_LIMIT_STATIC = 100;
	const EXPIRES            = 3600;	// 60min * 60sec = 1hour

	static $instance;

	private $plugin_basename;
	private $plugin_name;
	private $plugin_version;

	private $static_url;
	private $home_url;
	private $static_home_url;
	private $url_table;
	private $static_dir;
	private $remote_get_option;

	private $transient_key = 'static static';

	private $static_files_ext = array(
		'html','htm','txt','css','js','gif','png','jpg','jpeg',
		'mp3','ico','ttf','woff','woff2','otf','eot','svg','svgz','xml',
		'gz','zip', 'pdf', 'swf', 'xsl', 'mov', 'mp4', 'wmv', 'flv',
		'webm', 'ogg', 'oga', 'ogv', 'ogx', 'spx', 'opus',
		);

	function __construct($plugin_basename, $static_url = '/', $static_dir = '', $remote_get_option = array()){
		self::$instance = $this;

		$this->plugin_basename = $plugin_basename;
		$this->url_table = self::url_table();
		$this->init_params($static_url, $static_dir, $remote_get_option);

		add_action('wp_ajax_static_press_init', array($this, 'ajax_init'));
		add_action('wp_ajax_static_press_fetch', array($this, 'ajax_fetch'));
		add_action('wp_ajax_static_press_finalyze', array($this, 'ajax_finalyze'));
	}

	static public function url_table(){
		global $wpdb;
		return $wpdb->prefix.'urls';
	}

	private function init_params($static_url, $static_dir, $remote_get_option){
		global $wpdb;

		$parsed   = parse_url($this->get_site_url());
		$scheme   =
			isset($parsed['scheme'])
			? $parsed['scheme']
			: 'http';
		$host     = 
			isset($parsed['host'])
			? $parsed['host']
			: (defined('DOMAIN_CURRENT_SITE') ? DOMAIN_CURRENT_SITE : $_SERVER['HTTP_HOST']);
		$this->home_url = "{$scheme}://{$host}/";
		$this->static_url = preg_match('#^https?://#i', $static_url) ? $static_url : $this->home_url;
		$this->static_home_url = preg_replace('#^https?://[^/]+/#i', '/', trailingslashit($this->static_url));

		$this->static_dir = untrailingslashit(!empty($static_dir) ? $static_dir : ABSPATH);
		if (preg_match('#^https?://#i', $this->static_home_url)) {
			$this->static_dir .= preg_replace('#^https?://[^/]+/#i', '/', $this->static_home_url);
		} else {
			$this->static_dir .= $this->static_home_url;
		}
		$this->make_subdirectories($this->static_dir);

		$this->remote_get_option = $remote_get_option;

	    $data = get_file_data(
	    	dirname(dirname(__FILE__)).'/plugin.php',
	    	array('pluginname' => 'Plugin Name', 'version' => 'Version')
	    	);
		$this->plugin_name    = isset($data['pluginname']) ? $data['pluginname'] : 'StaticPress';
		$this->plugin_version = isset($data['version']) ? $data['version'] : '';

		$this->create_table();
	}

	public function activate(){
		global $wpdb;

		if ($wpdb->get_var("show tables like '{$this->url_table}'") != $this->url_table)
			$this->create_table();
		else if (!$wpdb->get_row("show fields from `{$this->url_table}` where field = 'enable'"))
			$wpdb->query("ALTER TABLE `{$this->url_table}` ADD COLUMN `enable` int(1) unsigned NOT NULL DEFAULT '1'");
	}

	public function deactivate(){
		global $wpdb;

		if ($wpdb->get_var("show tables like '{$this->url_table}'") != $this->url_table)
			$this->drop_table();
	}

	private function create_table(){
		global $wpdb;

		if ($wpdb->get_var("show tables like '{$this->url_table}'") != $this->url_table) {
			$wpdb->query("
CREATE TABLE `{$this->url_table}` (
 `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 `type` varchar(255) NOT NULL DEFAULT 'other_page',
 `url` varchar(255) NOT NULL,
 `object_id` bigint(20) unsigned NULL,
 `object_type` varchar(20) NULL ,
 `parent` bigint(20) unsigned NOT NULL DEFAULT 0,
 `pages` bigint(20) unsigned NOT NULL DEFAULT 1,
 `enable` int(1) unsigned NOT NULL DEFAULT '1',
 `file_name` varchar(255) NOT NULL DEFAULT '',
 `file_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 `last_statuscode` int(20) NULL,
 `last_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 `last_upload` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 `create_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
 PRIMARY KEY (`ID`),
 KEY `type` (`type`),
 KEY `url` (`url`),
 KEY `file_name` (`file_name`),
 KEY `file_date` (`file_date`),
 KEY `last_upload` (`last_upload`)
)");
		}
	}

	private function drop_table(){
		global $wpdb;

		if ($wpdb->get_var("show tables like '{$this->url_table}'") != $this->url_table) {
			$wpdb->query("DROP TABLE `{$this->url_table}`");
		}
	}

	private function json_output($content){
		header('Content-Type: application/json; charset='.get_option('blog_charset'));
		echo json_encode($content);
		die();
	}

	public function ajax_init(){
		global $wpdb;

		if (!defined('WP_DEBUG_DISPLAY'))
			define('WP_DEBUG_DISPLAY', false);

		if (!is_user_logged_in())
			wp_die('Forbidden');

		$urls = $this->insert_all_url();
		$sql = $wpdb->prepare(
			"select type, count(*) as count from {$this->url_table} where `last_upload` < %s and enable = 1 group by type",
			$this->fetch_start_time()
			);
		$all_urls = $wpdb->get_results($sql);
		$result =
			!is_wp_error($all_urls)
			? array('result' => true, 'urls_count' => $all_urls)
			: array('result' => false);

		$this->json_output(apply_filters('StaticPress::ajax_init', $result));
	}

	public function ajax_fetch(){
		if (!is_user_logged_in())
			wp_die('Forbidden');

		if (!defined('WP_DEBUG_DISPLAY'))
			define('WP_DEBUG_DISPLAY', false);

		$url = $this->fetch_url();
		if (!$url) {
			$result = array('result' => false, 'final' => true);
			$this->json_output(apply_filters('StaticPress::ajax_fetch', $result, $url));
		}

		$result = array();
		$static_file = $this->create_static_file($url->url, $url->type, true, true);
		$file_count = 1;
		$result[$url->ID] = array(
			'ID' => $url->ID,
			'page' => 1,
			'type' => $url->type,
			'url' => $url->url,
			'static' => $static_file,
			);
		if ($url->pages > 1) {
			for ($page = 2; $page <= $url->pages; $page++) {
				$page_url = untrailingslashit(trim($url->url));
				$static_file = false;
				switch($url->type){
				case 'term_archive':
				case 'author_archive':
				case 'other_page':
					$page_url = sprintf('%s/page/%d', $page_url, $page);
					$static_file = $this->create_static_file($page_url, 'other_page', false, true);
					break;
				case 'single':
					$page_url = sprintf('%s/%d', $page_url, $page);
					$static_file = $this->create_static_file($page_url, 'other_page', false, true);
					break;
				}
				if (!$static_file)
					break;
				$file_count++;
				$result["{$url->ID}-{$page}"] = array(
					'ID' => $url->ID,
					'page' => $page,
					'type' => $url->type,
					'url' => $page_url,
					'static' => $static_file,
					);
			}
		}

		while ($url = $this->fetch_url()) {
			$limit = ($url->type == 'static_file' ? self::FETCH_LIMIT_STATIC : self::FETCH_LIMIT);
			$static_file = $this->create_static_file($url->url, $url->type, true, true);
			$file_count++;
			$result[$url->ID] = array(
				'ID' => $url->ID,
				'page' => 1,
				'type' => $url->type,
				'url' => $url->url,
				'static' => $static_file,
				);
			if ($file_count >= $limit)
				break;
		}

		$result = array('result' => true, 'files' => $result, 'final' => ($url === false));
		$this->json_output(apply_filters('StaticPress::ajax_fetch', $result, $url));
	}

	public function ajax_finalyze(){
		if (!defined('WP_DEBUG_DISPLAY'))
			define('WP_DEBUG_DISPLAY', false);

		if (!is_user_logged_in())
			wp_die('Forbidden');

		$static_file = $this->create_static_file($this->get_site_url().'404.html');
		$this->fetch_finalyze();

		$result = array('result' => true);
		$this->json_output(apply_filters('StaticPress::ajax_finalyze', $result));
	}

	public function replace_url($url){
		$site_url = trailingslashit($this->get_site_url());
		$url = trim(str_replace($site_url, '/', $url));
		$static_files_filter = apply_filters('StaticPress::static_files_filter', $this->static_files_ext);
		if (!preg_match('#[^/]+\.' . implode('|', array_merge($static_files_filter, array('php'))) . '$#i', $url))
			$url = trailingslashit($url);
		unset($static_files_filter);
		return $url;
	}

	public function static_url($permalink) {
		return urldecode(
			preg_match('/\.[^\.]+?$/i', $permalink) 
			? $permalink
			: trailingslashit(trim($permalink)) . 'index.html');
	}

	private function get_site_url(){
		global $current_blog;
		return trailingslashit(
			isset($current_blog)
			? get_home_url($current_blog->blog_id)
			: get_home_url()
			);
	}

	private function get_transient_key() {
		$current_user = function_exists('wp_get_current_user') ? wp_get_current_user() : '';
		if (isset($current_user->ID) && $current_user->ID)
			return "{$this->transient_key} - {$current_user->ID}";
		else
			return $this->transient_key;
	}

	private function fetch_start_time() {
		$transient_key = $this->get_transient_key();
		$param = get_transient($transient_key);
		if (!is_array($param))
			$param = array();
		if (isset($param['fetch_start_time'])) {
			return $param['fetch_start_time'];
		} else {
			$start_time = date('Y-m-d h:i:s', time());
			$param['fetch_start_time'] = $start_time;
			set_transient($transient_key, $param, self::EXPIRES);
			return $start_time;
		}
	}

	private function fetch_last_id($next_id = false) {
		$transient_key = $this->get_transient_key();
		$param = (array)get_transient($transient_key);
		if (!is_array($param))
			$param = array();
		$last_id = isset($param['fetch_last_id']) ? intval($param['fetch_last_id']) : 0;
		if ($next_id) {
			$last_id = $next_id;
			$param['fetch_last_id'] = $next_id;
			set_transient($transient_key, $param, self::EXPIRES);
		}
		return $last_id;
	}

	private function fetch_finalyze() {
		$transient_key = $this->get_transient_key();
		if (get_transient($transient_key))
			delete_transient($transient_key);
	}

	private function fetch_url() {
		global $wpdb;

		$sql = $wpdb->prepare(
			"select ID, type, url, pages from {$this->url_table} where `last_upload` < %s and ID > %d and enable = 1 order by ID limit 1",
			$this->fetch_start_time(),
			$this->fetch_last_id()
			);
		$result = $wpdb->get_row($sql);
		if (!is_null($result) && !is_wp_error($result) && $result->ID) {
			$next_id = $this->fetch_last_id($result->ID);
			return $result;
		} else {
			$this->fetch_finalyze();
			return false;
		}
	}

	private function get_all_url() {
		global $wpdb;

		$sql = $wpdb->prepare(
			"select ID, type, url, pages from {$this->url_table} where `last_upload` < %s and enable = 1",
			$this->fetch_start_time()
			);
		return $wpdb->get_results($sql);
	}

	private function dir_sep(){
		return defined('DIRECTORY_SEPARATOR') ? DIRECTORY_SEPARATOR : '/';
	}

	// make subdirectries
	private function make_subdirectories($file){
		$dir_sep = $subdir = $this->dir_sep();
		$directories = explode($dir_sep, dirname($file));
		foreach ($directories as $dir){
			if (empty($dir))
				continue;
			$subdir .= trailingslashit($dir);
			if (!file_exists($subdir))
				mkdir($subdir, 0755);
		}
	}

	private function create_static_file($url, $file_type = 'other_page', $create_404 = true, $crawling = false) {
		$url = apply_filters('StaticPress::get_url', $url);
		$file_dest = untrailingslashit($this->static_dir) . $this->static_url($url);
		$dir_sep = defined('DIRECTORY_SEPARATOR') ? DIRECTORY_SEPARATOR : '/';
		if ( $dir_sep !== '/' )
			$file_dest = str_replace('/', $dir_sep, $file_dest);

		$http_code = 200;
		$blog_charset = get_option('blog_charset');
		switch ($file_type) {
		case 'front_page':
		case 'single':
		case 'term_archive':
		case 'author_archive':
		case 'seo_files':
		case 'other_page':
			// get remote file
			if (($content = $this->remote_get($url)) && isset($content['body'])) {
				if ($blog_charset === 'UTF-8') {
					$content['body'] = $this->clean_utf8($content['body']);
				}
				$http_code = intval($content['code']);
				switch ($http_code) {
				case 200:
					if ($crawling)
						$this->other_url($content['body'], $url, $http_code);
				case 404:
					if ($create_404 || $http_code == 200) {
						$content = apply_filters('StaticPress::put_content', $content['body'], $http_code);
						$this->make_subdirectories($file_dest);
						file_put_contents($file_dest, $content);
						$file_date = date('Y-m-d h:i:s', filemtime($file_dest));
					}
				}
			}
			break;
		case 'static_file':
			// get static file
			$file_source = untrailingslashit(ABSPATH) . $url;
			if ( $dir_sep !== '/' )
				$file_source = str_replace('/', $dir_sep, $file_source);
			if (!is_file($file_source) || !file_exists($file_source)) {
				$this->delete_url(array($url));
				return false;
			}
			if ($file_source != $file_dest && (!file_exists($file_dest) || filemtime($file_source) > filemtime($file_dest))) {
				$file_date = date('Y-m-d h:i:s', filemtime($file_source));
				$this->make_subdirectories($file_dest);
				copy($file_source, $file_dest);
			}
			break;
		}
		do_action('StaticPress::file_put', $file_dest, untrailingslashit($this->static_url). $this->static_url($url));

		if (file_exists($file_dest)) {
			$this->update_url(array(array(
				'type' => $file_type,
				'url' => $url,
				'file_name' => $file_dest,
				'file_date' => $file_date,
				'last_statuscode' => $http_code,
				'last_upload' => date('Y-m-d h:i:s', time()),
				)));
		} else {
			$file_dest = false;
			$this->update_url(array(array(
				'type' => $file_type,
				'url' => $url,
				'file_name' => '',
				'last_statuscode' => 404,
				'last_upload' => date('Y-m-d h:i:s', time()),
				)));
		}

		return $file_dest;
	}

	private function remote_get($url){
		if (!preg_match('#^https://#i', $url))
			$url = untrailingslashit($this->get_site_url()) . (preg_match('#^/#i', $url) ? $url : "/{$url}");
		$response = wp_remote_get($url, $this->remote_get_option);
		if (is_wp_error($response))
			return false;
		return array(
			'code' => $response['response']['code'],
			'body' => $this->remove_link_tag($response['body'], intval($response['response']['code'])),
			);
	}

	public function remove_link_tag($content, $http_code = 200) {
		$content = preg_replace(
			'#^[ \t]*<link [^>]*rel=[\'"](pingback|EditURI|shortlink|wlwmanifest)[\'"][^>]+/?>\n#ism',
			'',
			$content);
		$content = preg_replace(
			'#^[ \t]*<link [^>]*rel=[\'"]alternate[\'"] [^>]*type=[\'"]application/rss\+xml[\'"][^>]+/?>\n#ism',
			'',
			$content);
		return $content;
	}

	public function	add_last_modified($content, $http_code = 200) {
		if (intval($http_code) === 200) {
			$type = preg_match('#<!DOCTYPE html>#i', $content) ? 'html' : 'xhtml';
			switch ( $type ) {
			case 'html':
				$last_modified = sprintf('<meta http-equiv="Last-Modified" content="%s GMT">', gmdate("D, d M Y H:i:s"));
				break;
			case 'xhtml':
			default:
				$last_modified = sprintf('<meta http-equiv="Last-Modified" content="%s GMT" />', gmdate("D, d M Y H:i:s"));
				break;
			}
			$content = preg_replace('#(<head>|<head [^>]+>)#ism', '$1'."\n".$last_modified, $content);
		}
		return $content;
	}

	public function	rewrite_generator_tag($content, $http_code = 200) {
		$content = preg_replace(
			'#(<meta [^>]*name=[\'"]generator[\'"] [^>]*content=[\'"])([^\'"]*)([\'"][^>]*/?>)#ism',
			'$1$2 with '.$this->plugin_name.(!empty($this->plugin_version) ? ' ver.'.$this->plugin_version : '').'$3',
			$content);
		return $content;
	}

	public function replace_relative_URI($content, $http_code = 200) {
		$site_url = trailingslashit($this->get_site_url());
		$parsed = parse_url($site_url);
		$home_url = $parsed['scheme'] . '://' . $parsed['host'];
		if (isset($parsed['port']))
			$home_url .= ':'.$parsed['port'];

		$pattern  = array(
			'# (href|src|action)="(/[^"]*)"#ism',
			"# (href|src|action)='(/[^']*)'#ism",
		);
		$content = preg_replace($pattern, ' $1="'.$home_url.'$2"', $content);

		$content = str_replace($site_url, trailingslashit($this->static_url), $content);

		$parsed = parse_url($this->static_url);
		$static_url = $parsed['scheme'] . '://' . $parsed['host'];
		if (isset($parsed['port']))
			$static_url .= ':'.$parsed['port'];
		$pattern  = array(
			'# (href|src|action)="'.preg_quote($static_url).'([^"]*)"#ism',
			"# (href|src|action)='".preg_quote($static_url)."([^']*)'#ism",
		);
		$content  = preg_replace($pattern, ' $1="$2"', $content);

		if ( $home_url !== $static_url ) {
			$pattern  = array(
				'# (href|src|action)="'.preg_quote($home_url).'([^"]*)"#ism',
				"# (href|src|action)='".preg_quote($home_url)."([^']*)'#ism",
			);
			$content  = preg_replace($pattern, ' $1="$2"', $content);
		}

		$pattern = array(
			'meta [^>]*property=[\'"]og:[^\'"]*[\'"] [^>]*content=',
			'link [^>]*rel=[\'"]canonical[\'"] [^>]*href=',
			'link [^>]*rel=[\'"]shortlink[\'"] [^>]*href=',
			'data-href=',
			'data-url=',
			);
		$pattern  = '#<('.implode('|', $pattern).')[\'"](/[^\'"]*)[\'"]([^>]*)>#uism';
		$content = preg_replace($pattern, '<$1"'.$static_url.'$2"$3>', $content);

		$content = str_replace(addcslashes($site_url, '/'), addcslashes(trailingslashit($this->static_url), '/'), $content);

		return $content;
	}

	private function insert_all_url(){
		$urls = $this->get_urls();
		return $this->update_url($urls);
	}

	private function update_url($urls){
		global $wpdb;

		foreach ((array)$urls as $url){
			if (!isset($url['url']) || !$url['url'])
				continue;
			$sql = $wpdb->prepare(
				"select ID from {$this->url_table} where url=%s limit 1",
				$url['url']);

			$url['enable'] = 1;
			if (preg_match('#\.php$#i', $url['url'])) {
				$url['enable'] = 0;
			} else if (preg_match('#\?[^=]+[=]?#i', $url['url'])) {
				$url['enable'] = 0;
			} else if (preg_match('#/wp-admin/$#i', $url['url'])) {
				$url['enable'] = 0;
			} else if (isset($url['type']) && $url['type'] == 'static_file') {
				$plugin_dir  = trailingslashit(str_replace(ABSPATH, '/', WP_PLUGIN_DIR));
				$theme_dir   = trailingslashit(str_replace(ABSPATH, '/', WP_CONTENT_DIR) . '/themes');
				$file_source = untrailingslashit(ABSPATH) . $url['url'];
				$file_dest   = untrailingslashit($this->static_dir) . $url['url'];
				$pattern     = '#^(/(readme|readme-[^\.]+|license)\.(txt|html?)|('.preg_quote($plugin_dir).'|'.preg_quote($theme_dir).').*/((readme|changelog|license)\.(txt|html?)|(screenshot|screenshot-[0-9]+)\.(png|jpe?g|gif)))$#i';
				if ($file_source === $file_dest) {
					$url['enable'] = 0;
				} else if (preg_match($pattern, $url['url'])) {
					$url['enable'] = 0;
				} else if (!file_exists($file_source)) {
					$url['enable'] = 0;
				} else if (!file_exists($file_dest))  {
					$url['enable'] = 1;
				} else if (filemtime($file_source) <= filemtime($file_dest)) {
					$url['enable'] = 0;
				}

				if ($url['enable'] == 1) {
					if (preg_match('#^'.preg_quote($plugin_dir).'#i', $url['url'])){
						$url['enable'] = 0;
						$active_plugins = get_option('active_plugins');
						foreach ($active_plugins as $active_plugin) {
							$active_plugin = trailingslashit($plugin_dir . dirname($active_plugin));
							if ($active_plugin == trailingslashit($plugin_dir . '.'))
								continue;
							if (preg_match('#^'.preg_quote($active_plugin).'#i', $url['url'])) {
								$url['enable'] = 1;
								break;
							}
						}
					} else if (preg_match('#^'.preg_quote($theme_dir).'#i', $url['url'])) {
						$url['enable'] = 0;
						$current_theme = trailingslashit($theme_dir . get_stylesheet());
						if (preg_match('#^'.preg_quote($current_theme).'#i', $url['url'])) {
							$url['enable'] = 1;
						}
					}
				}
			}

			if ($id = $wpdb->get_var($sql)){
				$sql = "update {$this->url_table}";
				$update_sql = array();
				foreach($url as $key => $val){
					$update_sql[] = $wpdb->prepare("{$key} = %s", $val);
				}
				$sql .= ' set '.implode(',', $update_sql);
				$sql .= $wpdb->prepare(' where ID=%s', $id);
			} else {
				$sql = "insert into {$this->url_table}";
				$sql .= ' (`' . implode('`,`', array_keys($url)). '`,`create_date`)';
				$insert_val = array();
				foreach($url as $key => $val){
					$insert_val[] = $wpdb->prepare("%s", $val);
				}
				$insert_val[] = $wpdb->prepare("%s", date('Y-m-d h:i:s'));
				$sql .= ' values (' . implode(',', $insert_val) . ')';
			}
			if ($sql)
				$wpdb->query($sql);

			do_action('StaticPress::update_url', $url);
		}
		return $urls;
	}

	private function delete_url($urls){
		global $wpdb;

		foreach ((array)$urls as $url){
			if (!isset($url['url']) || !$url['url'])
				continue;
			$sql = $wpdb->prepare(
				"delete from `{$this->url_table}` where `url` = %s",
				$url['url']);
			if ($sql)
				$wpdb->query($sql);
			do_action('StaticPress::delete_url', $url);
		}
		return $urls;
	}

	private function get_urls(){
		global $wpdb;

		$wpdb->query("truncate table `{$this->url_table}`");
		$this->post_types = "'".implode("','",get_post_types(array('public' => true)))."'";
		$urls = array();
		$urls = array_merge($urls, $this->front_page_url());
		$urls = array_merge($urls, $this->single_url());
		$urls = array_merge($urls, $this->terms_url());
		$urls = array_merge($urls, $this->author_url());
		$urls = array_merge($urls, $this->static_files_url());
		$urls = array_merge($urls, $this->seo_url());
		return $urls;
	}

	// Check correct sitemap url by robots.txt
	private function seo_url($url_type = 'seo_files'){
		$urls = array();
		$analyzed = array();
		$sitemap = '/sitemap.xml';
		$robots = '/robots.txt';
		$urls[] = array('type' => $url_type, 'url' => $robots, 'last_modified' => date('Y-m-d h:i:s'));
		if(($txt = $this->remote_get($robots)) && isset($txt['body'])){
			$http_code = intval($txt['code']);
			switch (intval($http_code)){
			case 200:
				if(preg_match('/sitemap:\s.*?(\/[\-_a-z0-9%]+\.xml)/i',$txt['body'],$match)){
					$sitemap = $match[1];
				}
			}
		}
		$this->sitemap_analyzer($analyzed,$urls,$sitemap,$url_type);
		return $urls;
	}

	// Crawling sitemap XML files
	private function sitemap_analyzer(&$analyzed,&$urls,$url,$url_type){
		$urls[] = array('type' => $url_type, 'url' => $url, 'last_modified' => date('Y-m-d h:i:s'));
		$analyzed[] = $url;
		if(($xml = $this->remote_get($url)) && isset($xml['body'])){
			$http_code = intval($xml['code']);
			switch (intval($http_code)){
			case 200:
				if(preg_match_all('/<loc>(.*?)<\/loc>/i',$xml['body'],$matches)){
					foreach($matches[1] as $link){
						if(preg_match('/\/([\-_a-z0-9%]+\.xml)$/i',$link,$matchSub)){
							if(!in_array($matchSub[0],$analyzed)){
								$this->sitemap_analyzer($analyzed,$urls,$matchSub[0],$url_type);
							}
						}
					}
				}
			}
		}
	}


	private function front_page_url($url_type = 'front_page'){
		$urls = array();
		$site_url = $this->get_site_url();
		$urls[] = array(
			'type' => $url_type,
			'url' => apply_filters('StaticPress::get_url', $site_url),
			'last_modified' => date('Y-m-d h:i:s'),
			);
		return $urls;
	}

	private function single_url($url_type = 'single') {
		global $wpdb;

		if (!isset($this->post_types) || empty($this->post_types))
			$this->post_types = "'".implode("','",get_post_types(array('public' => true)))."'";

		$urls = array();
		$posts = $wpdb->get_results("
select ID, post_type, post_content, post_status, post_modified
 from {$wpdb->posts}
 where (post_status = 'publish' or post_type = 'attachment')
 and post_type in ({$this->post_types})
 order by post_type, ID
");
		foreach ($posts as $post) {
			$post_id = $post->ID;
			$modified = $post->post_modified;
			$permalink = get_permalink($post->ID);
			if (is_wp_error($permalink))
				continue;
			$count = 1;
			if ( $splite = preg_split("#<!--nextpage-->#", $post->post_content) )
				$count = count($splite);
			$urls[] = array(
				'type' => $url_type,
				'url' => apply_filters('StaticPress::get_url', $permalink),
				'object_id' => intval($post_id),
				'object_type' =>  $post->post_type,
				'pages' => $count,
				'last_modified' => $modified,
				);
		}
		return $urls;
	}

	private function get_term_info($term_id) {
		global $wpdb;

		if (!isset($this->post_types) || empty($this->post_types))
			$this->post_types = "'".implode("','",get_post_types(array('public' => true)))."'";

		$result = $wpdb->get_row($wpdb->prepare("
select MAX(P.post_modified) as last_modified, count(P.ID) as count
 from {$wpdb->posts} as P
 inner join {$wpdb->term_relationships} as tr on tr.object_id = P.ID
 inner join {$wpdb->term_taxonomy} as tt on tt.term_taxonomy_id = tr.term_taxonomy_id
 where P.post_status = %s and P.post_type in ({$this->post_types})
  and tt.term_id = %d
",
			'publish',
			intval($term_id)
			));
		if (!is_wp_error($result)) {
			$modified = $result->last_modified;
			$count = $result->count;
		} else {
			$modified = date('Y-m-d h:i:s');
			$count = 1;
		}
		$page_count = intval($count / intval(get_option('posts_per_page'))) + 1;
		return array($modified, $page_count);
	}

	private function terms_url($url_type = 'term_archive') {
		global $wpdb;

		$urls = array();
		$taxonomies = get_taxonomies(array('public'=>true));
		foreach($taxonomies as $taxonomy) {
			$terms = get_terms($taxonomy);
			if (is_wp_error($terms))
				continue;
			foreach ($terms as $term){
				$term_id = $term->term_id;
				$termlink = get_term_link($term->slug, $taxonomy);
				if (is_wp_error($termlink))
					continue;
				list($modified, $page_count) = $this->get_term_info($term_id);
				$urls[] = array(
					'type' => $url_type,
					'url' => apply_filters('StaticPress::get_url', $termlink),
					'object_id' => intval($term_id),
					'object_type' => $term->taxonomy,
					'parent' => $term->parent,
					'pages' => $page_count,
					'last_modified' => $modified,
					);

				$termchildren = get_term_children($term->term_id, $taxonomy);
				if (is_wp_error($termchildren))
					continue;
				foreach ( $termchildren as $child ) {
					$term = get_term_by('id', $child, $taxonomy);
					$term_id = $term->term_id;
					if (is_wp_error($term))
						continue;
					$termlink = get_term_link($term->name, $taxonomy);
					if (is_wp_error($termlink))
						continue;
					list($modified, $page_count) = $this->get_term_info($term_id);
					$urls[] = array(
						'type' => $url_type,
						'url' => apply_filters('StaticPress::get_url', $termlink),
						'object_id' => intval($term_id),
						'object_type' => $term->taxonomy,
						'parent' => $term->parent,
						'pages' => $page_count,
						'last_modified' => $modified,
						);
				}
			}
		}
		return $urls;
	}

	private function author_url($url_type = 'author_archive') {
		global $wpdb;

		if (!isset($this->post_types) || empty($this->post_types))
			$this->post_types = "'".implode("','",get_post_types(array('public' => true)))."'";

		$urls = array();

		$authors = $wpdb->get_results("
SELECT DISTINCT post_author, COUNT(ID) AS count, MAX(post_modified) AS modified
 FROM {$wpdb->posts} 
 where post_status = 'publish'
   and post_type in ({$this->post_types})
 group by post_author
 order by post_author
");
		foreach ($authors as $author) {
			$author_id = $author->post_author;
			$page_count = intval($author->count / intval(get_option('posts_per_page'))) + 1;
			$modified = $author->modified;
			$author = get_userdata($author_id);
			if (is_wp_error($author))
				continue;
			$authorlink = get_author_posts_url($author->ID, $author->user_nicename);
			if (is_wp_error($authorlink))
				continue;
			$urls[] = array(
				'type' => $url_type,
				'url' => apply_filters('StaticPress::get_url', $authorlink),
				'object_id' => intval($author_id),
				'pages' => $page_count,
				'last_modified' => $modified,
				);
		}
		return $urls;
	}

	private function static_files_url($url_type = 'static_file'){
		$urls = array();

		$static_files_filter = apply_filters('StaticPress::static_files_filter', $this->static_files_ext);
		foreach ($static_files_filter as &$file_ext) {
			$file_ext = '*.'.$file_ext;
		}
		$static_files = array_merge(
			$this->scan_file(trailingslashit(ABSPATH), '{'.implode(',',$static_files_filter).'}', false),
			$this->scan_file(trailingslashit(ABSPATH).'wp-admin/', '{'.implode(',',$static_files_filter).'}', true),
			$this->scan_file(trailingslashit(ABSPATH).'wp-includes/', '{'.implode(',',$static_files_filter).'}', true),
			$this->scan_file(trailingslashit(WP_CONTENT_DIR), '{'.implode(',',$static_files_filter).'}', true)
			);
		unset($static_files_filter);

		foreach ($static_files as $static_file){
			$static_file_url = str_replace(trailingslashit(ABSPATH), trailingslashit($this->get_site_url()), $static_file);
			$urls[] = array(
				'type' => $url_type,
				'url' => apply_filters('StaticPress::get_url', $static_file_url),
				'last_modified' => date('Y-m-d h:i:s', filemtime($static_file)),
				);
		}
		return $urls;
	}

	private function url_exists($link) {
		global $wpdb;

		$link = apply_filters('StaticPress::get_url', $link);
		$count = intval(wp_cache_get('StaticPress::'.$link, 'static_press'));
		if ($count > 0)
			return true;

		$sql = $wpdb->prepare(
			"select count(*) from {$this->url_table} where `url` = %s limit 1",
			$link
			);
		$count = intval($wpdb->get_var($sql));
		wp_cache_set('StaticPress::'.$link, $count, 'static_press');
		
		return $count > 0;
	}

	private function other_url($content, $url){
		$urls = array();

		while (($url = dirname($url)) && $url != '/') {
			if (!$this->url_exists($url)) {
				$urls[] = array(
					'url' => apply_filters('StaticPress::get_url', $url),
					'last_modified' => date('Y-m-d h:i:s'),
					);
			}
		}

		$pattern = '#href=[\'"](' . preg_quote($this->get_site_url()) . '[^\'"\?\#]+)[^\'"]*[\'"]#i';
		if ( preg_match_all($pattern, $content, $matches) ){
			$matches = array_unique($matches[1]);
			foreach ($matches as $link) {
				if (!$this->url_exists($link)) {
					$urls[] = array(
						'url' => apply_filters('StaticPress::get_url', $link),
						'last_modified' => date('Y-m-d h:i:s'),
						);
				}
			}
		}
		unset($matches);

		if (count($urls) > 0)
			$this->update_url($urls);

		return $urls;
	}

	private function scan_file($dir, $target = false, $recursive = true) {
		if (!$target) {
			$static_files_filter = apply_filters('StaticPress::static_files_filter', $this->static_files_ext);
			foreach ($static_files_filter as &$file_ext) {
				$file_ext = '*.'.$file_ext;
			}
			$target = '{'.implode(',',$static_files_filter).'}';
			unset($static_files_filter);
		}

		$list = $tmp = array();
		if ($recursive) {
			foreach(glob($dir . '*/', GLOB_ONLYDIR) as $child_dir) {
				if ($tmp = $this->scan_file($child_dir, $target, $recursive)) {
					$list = array_merge($list, $tmp);
				}
			}
		}

		foreach(glob($dir . $target, GLOB_BRACE) as $image) {
			$list[] = $image;
		}

		return $list;
	}

	private function clean_utf8($content) {
		$regex = <<<'END'
		/
		  (
		    (?: [\x00-\x7F]                 # single-byte sequences   0xxxxxxx
		    |   [\xC0-\xDF][\x80-\xBF]      # double-byte sequences   110xxxxx 10xxxxxx
		    |   [\xE0-\xEF][\x80-\xBF]{2}   # triple-byte sequences   1110xxxx 10xxxxxx * 2
		    |   [\xF0-\xF7][\x80-\xBF]{3}   # quadruple-byte sequence 11110xxx 10xxxxxx * 3 
		    ){1,100}                        # ...one or more times
		  )
		| .                                 # anything else
		/x
END;
		return preg_replace($regex, '$1', $content);
	}
}
