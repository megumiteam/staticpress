<?php
if ( !class_exists('InputValidator') )
	require(dirname(__FILE__).'/class-InputValidator.php');

class static_press_admin {
	const OPTION_STATIC_URL   = 'StaticPress::static url';
	const OPTION_STATIC_DIR   = 'StaticPress::static dir';
	const OPTION_STATIC_BASIC = 'StaticPress::basic auth';
	const OPTION_FETCH_LIMIT  = 'StaticPress::fetch limit';
	const OPTION_FETCH_LIMIT_STATIC = 'StaticPress::fetch limit static';
	const OPTION_EXPIRES = 'StaticPress::expires';
	const OPTION_DEBUG_MODE   = 'StaticPress::debug mode';
	const OPTION_PAGE = 'static-press';
	const TEXT_DOMAIN = 'static-press';
	const DEBUG_MODE  = false;
	const ACCESS_LEVEL = 'manage_options';

	static $instance;

	private $plugin_basename;
	private $plugin_name;
	private $plugin_version;

	private $static_url;
	private $static_dir;
	private $basic_auth;
	private $fetch_limit;
	private $fetch_limit_static;
	private $expires;
	private $debug_mode;
	private $admin_action;

	function __construct($plugin_basename){
		self::$instance = $this;

		$this->static_url = self::static_url();
		$this->static_dir = self::static_dir();
		$this->basic_auth = self::basic_auth();
		$this->fetch_limit = self::fetch_limit();
		$this->fetch_limit_static = self::fetch_limit_static();
		$this->expires = self::expires();
		$this->debug_mode = self::debug_mode();
		$this->plugin_basename = $plugin_basename;
		$this->admin_action = admin_url('/admin.php') . '?page=' . self::OPTION_PAGE . '-options';

	    $data = get_file_data(
	    	dirname(dirname(__FILE__)).'/plugin.php',
	    	array('pluginname' => 'Plugin Name', 'version' => 'Version')
	    	);
		$this->plugin_name    = isset($data['pluginname']) ? $data['pluginname'] : 'StaticPress';
		$this->plugin_version = isset($data['version']) ? $data['version'] : '';

		add_action('admin_menu', array($this, 'admin_menu'));
		add_filter('plugin_action_links', array($this, 'plugin_setting_links'), 10, 2 );

		add_action('admin_head', array($this,'add_admin_head'), 99);
	}

	static public function static_url(){
		return get_option(self::OPTION_STATIC_URL, self::get_site_url().'static/');
	}

	static public function static_dir(){
		return get_option(self::OPTION_STATIC_DIR, ABSPATH);
	}

	static public function basic_auth(){
		return get_option(self::OPTION_STATIC_BASIC, false);
	}

	static public function debug_mode(){
		return get_option(self::OPTION_DEBUG_MODE, false);
	}

	static public function fetch_limit(){
		return get_option(self::OPTION_FETCH_LIMIT, 4);
	}

	static public function fetch_limit_static(){
		return get_option(self::OPTION_FETCH_LIMIT_STATIC, 100);
	}

	static public function expires(){
		return get_option(self::OPTION_EXPIRES, 3600);
	}

	static public function fetch_option(){
		return array(
			'fetch_limit'        => self::fetch_limit(),
			'fetch_limit_static' => self::fetch_limit_static(),
			'expires'            => self::expires()
		);
	}

	static public function remote_get_option(){
		$basic_auth = self::basic_auth();
		return 
			$basic_auth
			? array('headers' => array('Authorization' => 'Basic '.$basic_auth))
			: array();
	}

	static public function get_site_url(){
		global $current_blog;
		return trailingslashit(
			isset($current_blog)
			? get_home_url($current_blog->blog_id)
			: get_home_url()
			);
	}

	//**************************************************************************************
	// Add Admin Menu
	//**************************************************************************************
	public function admin_menu() {
		$hook = add_menu_page(
			__($this->plugin_name, self::TEXT_DOMAIN) ,
			__($this->plugin_name, self::TEXT_DOMAIN) ,
			self::ACCESS_LEVEL,
			self::OPTION_PAGE ,
			array($this, 'static_press_page') ,
			plugins_url('images/staticpress.png', dirname(__FILE__))
			);
		add_action('admin_print_scripts-'.$hook, array($this, 'add_admin_scripts'));

		$hook = add_submenu_page(
			self::OPTION_PAGE ,
			__($this->plugin_name.' Options', self::TEXT_DOMAIN) ,
			__($this->plugin_name.' Options', self::TEXT_DOMAIN) ,
			self::ACCESS_LEVEL,
			self::OPTION_PAGE . '-options' ,
			array($this, 'options_page'),
			plugins_url('images/staticpress_options.png', dirname(__FILE__))
			);
		
		do_action('StaticPress::admin_menu', self::OPTION_PAGE);
	}

	public function add_admin_scripts(){
		do_action('StaticPress::admin_scripts');
	}

	public function add_admin_head(){

?>

<style type="text/css" id="<?php echo self::OPTION_PAGE;?>-menu-css">
#toplevel_page_<?php echo self::OPTION_PAGE;?> .wp-menu-image {
	background: url( <?php echo plugins_url('images/menuicon-splite.png', dirname(__FILE__)); ?> ) 0 90% no-repeat !important;
}
#toplevel_page_<?php echo self::OPTION_PAGE;?>.current .wp-menu-image,
#toplevel_page_<?php echo self::OPTION_PAGE;?>.wp-has-current-submenu .wp-menu-image,
#toplevel_page_<?php echo self::OPTION_PAGE;?>:hover .wp-menu-image {
	background-position: top left !important;
}
#icon-static-press {background-image: url(<?php echo plugins_url('images/rebuild32.png', dirname(__FILE__)); ?>);}
#icon-static-press-options {background-image: url(<?php echo plugins_url('images/options32.png', dirname(__FILE__)); ?>);}
</style>
<?php

		do_action('StaticPress::admin_head');
	}

	public function options_page(){
		$nonce_action  = 'update_options';
		$nonce_name    = '_wpnonce_update_options';

		$title = __($this->plugin_name.' Options', self::TEXT_DOMAIN);

		$iv = new InputValidator('POST');
		$iv->set_rules($nonce_name, 'required');
		$iv->set_rules('static_url', array('trim','esc_html'));
		$iv->set_rules('static_dir', array('trim','esc_html'));
		$iv->set_rules('basic_usr',  array('trim','esc_html'));
		$iv->set_rules('basic_pwd',  array('trim','esc_html'));

		// Update options
		if (!is_wp_error($iv->input($nonce_name)) && check_admin_referer($nonce_action, $nonce_name)) {
			// Get posted options
			$static_url = $iv->input('static_url');
			$static_dir = $iv->input('static_dir');
			$basic_usr  = $iv->input('basic_usr');
			$basic_pwd  = $iv->input('basic_pwd');
			$basic_auth = 
				($basic_usr && $basic_pwd)
				? base64_encode("{$basic_usr}:{$basic_pwd}")
				: false;
			$debug_mode = !!$iv->input('debug_mode');

			$fetch_limit        = $iv->input('fetch_limit');
			$fetch_limit_static = $iv->input('fetch_limit_static');
			$expires            = $iv->input('expires');

			// Update options
			update_option(self::OPTION_STATIC_URL, $static_url);
			update_option(self::OPTION_STATIC_DIR, $static_dir);
			update_option(self::OPTION_STATIC_BASIC, $basic_auth);
			update_option(self::OPTION_DEBUG_MODE, $debug_mode);

			update_option(self::OPTION_FETCH_LIMIT, $fetch_limit);
			update_option(self::OPTION_FETCH_LIMIT_STATIC, $fetch_limit_static);
			update_option(self::OPTION_EXPIRES, $expires);

			printf(
				'<div id="message" class="updated fade"><p><strong>%s</strong></p></div>'."\n",
				empty($err_message) ? __('Done!', self::TEXT_DOMAIN) : $err_message
				);

			$this->static_url = $static_url;
			$this->static_dir = $static_dir;
			$this->basic_auth = $basic_auth;

			$this->debug_mode         = $debug_mode;
			$this->fetch_limit        = $fetch_limit;
			$this->fetch_limit_static = $fetch_limit_static;
			$this->expires            = $expires;
		}
		do_action('StaticPress::options_save');

		$basic_usr = $basic_pwd = '';
		if ( $this->basic_auth )
			list($basic_usr,$basic_pwd) = explode(':', base64_decode($this->basic_auth));
?>
		<div class="wrap" id="<?php echo self::OPTION_PAGE; ?>-options">
		<?php screen_icon(self::OPTION_PAGE.'-options'); ?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<form method="post" action="<?php echo $this->admin_action;?>">
		<?php echo wp_nonce_field($nonce_action, $nonce_name, true, false) . "\n"; ?>
		<table class="wp-list-table fixed"><tbody>
		<tr><td colspan=2>
			<h3><?php echo __('Basic Configurations', self::TEXT_DOMAIN); ?></h3>
		</td></tr>
		<?php $this->input_field('static_url', __('Static URL', self::TEXT_DOMAIN), $this->static_url); ?>
		<?php $this->input_field('static_dir', __('Save DIR (Document root)', self::TEXT_DOMAIN), $this->static_dir); ?>
		<?php $this->input_field('basic_usr', __('(OPTION) BASIC Auth User', self::TEXT_DOMAIN), $basic_usr); ?>
		<?php $this->input_field('basic_pwd', __('(OPTION) BASIC Auth Password', self::TEXT_DOMAIN), $basic_pwd, 'password'); ?>
		<tr><td colspan=2>
			<h3><?php echo __('Advanced Options', self::TEXT_DOMAIN); ?></h3>
		</td></tr>
		<?php $this->input_field('fetch_limit', __('FETCH LIMIT', self::TEXT_DOMAIN), $this->fetch_limit); ?>
		<?php $this->input_field('fetch_limit_static', __('FETCH LIMIT STATIC', self::TEXT_DOMAIN), $this->fetch_limit_static); ?>
		<?php $this->input_field('expires', __('EXPIRES', self::TEXT_DOMAIN), $this->expires); ?>
		<?php $this->input_field('debug_mode', __('DEBUG MODE', self::TEXT_DOMAIN), 1, 'checkbox', $this->debug_mode); ?>
		</tbody></table>
		<?php submit_button(); ?>
		</form>
		</div>
<?php

		do_action('StaticPress::options_page');
	}

	private function input_field($field, $label, $val, $type = 'text', $checked = false){
		$label = sprintf('<th><label for="%1$s">%2$s</label></th>'."\n", $field, $label);
		$input = "";
		switch($type){
			case 'text':
			case 'password':
				$input = sprintf('<td><input type="%3$s" name="%1$s" value="%2$s" id="%1$s" size=100 /></td>'."\n", $field, esc_attr($val), $type);
				break;
			case 'checkbox':
				$checked_attr = $checked ? 'checked="checked"' : '';
				$input = sprintf('<td><input type="%3$s" name="%1$s" value="%2$s" id="%1$s" %4$s /></td>'."\n", $field, esc_attr($val), $type, $checked_attr);
				break;
		}
		echo "<tr>\n{$label}{$input}</tr>\n";
	}

	public function static_press_page(){
		$title = __('Rebuild', self::TEXT_DOMAIN);
?>
		<div class="wrap" style="margin=top:2em;" id="<?php echo self::OPTION_PAGE; ?>">
		<?php screen_icon(); ?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<?php submit_button(__('Rebuild', self::TEXT_DOMAIN), 'primary', 'rebuild'); ?>
		<div id="rebuild-result"></div>
		<div id="debug-output"></div>
		</div>
<?php

		wp_enqueue_script('jQuery', false, array(), false, true);
		add_action('admin_footer', array($this, 'admin_footer'));

		do_action('StaticPress::static_press_page');
	}

	public function admin_footer(){
		$admin_ajax = admin_url('admin-ajax.php');
?>
<script type="text/javascript">
jQuery(function($){
	var time_start = 0;
	var file_count = 0;
	var loader = $('<div id="loader" style="line-height: 115px; text-align: center;"><img alt="activity indicator" src="<?php echo plugins_url( 'images/ajax-loader.gif' , dirname(__FILE__) ); ?>"></div>');

	function static_press_init(){
		time_start = (new Date()).getTime();
		file_count = 0;
		$('#debug-output').html('');
		$('#rebuild').hide();
		$('#rebuild-result')
			.html('<p><strong><?php echo __('Initialyze...', self::TEXT_DOMAIN);?></strong></p>')
			.after(loader);
		$.ajax('<?php echo $admin_ajax; ?>',{
			data: {action: 'static_press_init'},
			cache: false,
			dataType: 'json',
			type: 'POST',
			success: function(response){
				<?php if ($this->debug_mode) echo "console.log(response);\n" ?>
				

				if (response.result) {
					$('#rebuild-result').append('<p><strong><?php echo __('URLS', self::TEXT_DOMAIN);?></strong></p>')
					var ul = $('<ul></ul>');
					$.each(response.urls_count, function(){
						ul.append('<li>' + this.type + ' (' + this.count + ')</li>');
					});
					$('#rebuild-result').append('<p></p>').append(ul);
				}
				$('#rebuild-result').append('<p><strong><?php echo __('Fetch Start...', self::TEXT_DOMAIN);?></strong></p>');				
				static_press_fetch();
			},
			error: function(response){
				<?php if ($this->debug_mode){ ?>
				console.log(response);
				$('#debug-output').html($('<pre>'+response.responseText+'</pre>'));
				<?php } ?>
				$('#rebuild').show();
				$('#loader').remove();
				$('#rebuild-result').append('<p id="message"><strong><?php echo __('Error!', self::TEXT_DOMAIN);?></strong></p>');
				$('html,body').animate({scrollTop: $('#message').offset().top},'slow');
				file_count = 0;
			}
		});
	}

	function static_press_fetch(){
		$.ajax('<?php echo $admin_ajax; ?>',{
			data: {action: 'static_press_fetch'},
			cache: false,
			dataType: 'json',
			type: 'POST',
			success: function(response){
				if ($('#rebuild-result ul.result-list').size() == 0)
					$('#rebuild-result').append('<p class="result-list-wrap"><ul class="result-list"></ul></p>');				
				if (response.result) {
				<?php if ($this->debug_mode) echo "console.log(response);\n" ?>
					var ul = $('#rebuild-result .result-list');
					$.each(response.files, function(){
						if (this.static) {
							file_count++;
							ul.append('<li>' + file_count + ' : ' + this.static + '</li>');
						}
					});
					if($('li:last-child', ul).length > 0)
						$('html,body').animate({scrollTop: $('li:last-child', ul).offset().top},'slow');
					if (response.final)
						static_press_finalyze();
					else
						static_press_fetch();
				} else {
					static_press_finalyze();
				}
			},
			error: function(response){
				<?php if ($this->debug_mode){ ?>
				console.log(response);
				$('#debug-output').html($('<pre>'+response.responseText+'</pre>'));
				<?php } ?>
				$('#rebuild').show();
				$('#loader').remove();
				$('#rebuild-result').append('<p id="message"><strong><?php echo __('Error!', self::TEXT_DOMAIN);?></strong></p>');
				$('html,body').animate({scrollTop: $('#message').offset().top},'slow');
				file_count = 0;
			}
		});
	}

	function static_press_finalyze(){
		var time_elapsed = ((new Date()).getTime() - time_start) / 1000;
		$.ajax('<?php echo $admin_ajax; ?>',{
			data: {action: 'static_press_finalyze'},
			cache: false,
			dataType: 'json',
			type: 'POST',
			success: function(response){
				<?php if ($this->debug_mode) echo "console.log(response);\n" ?>
				$('#rebuild').show();
				$('#loader').remove();
				$('#rebuild-result').append('<p id="message"><strong><?php echo __('End',   self::TEXT_DOMAIN);?></strong></p>');
				$('#rebuild-result').append('<p id="elapsed"><strong><?php echo __('Elapsed Time', self::TEXT_DOMAIN);?>: '+time_elapsed+'sec.</strong></p>');
				$('html,body').animate({scrollTop: $('#message').offset().top},'slow');
				file_count = 0;
			},
			error: function(response){
				<?php if ($this->debug_mode){ ?>
				console.log(response);
				$('#debug-output').html($('<pre>'+response.responseText+'</pre>'));
				<?php } ?>
				$('#rebuild').show();
				$('#loader').remove();
				$('#rebuild-result').append('<p id="message"><strong><?php echo __('Error!',   self::TEXT_DOMAIN);?></strong></p>');
				$('html,body').animate({scrollTop: $('#message').offset().top},'slow');
				file_count = 0;
			}
		});
	}

	$('#rebuild').click(static_press_init);
});
</script>
<?php
		do_action('StaticPress::admin_footer');
	}

	//**************************************************************************************
	// Add setting link
	//**************************************************************************************
	public function plugin_setting_links($links, $file) {
		if ($file === $this->plugin_basename) {
			$settings_link = '<a href="' . $this->admin_action . '">' . __('Settings') . '</a>';
			array_unshift($links, $settings_link); // before other links
		}
		$links = apply_filters('StaticPress::plugin_setting_links', $links);

		return $links;
	}
}
