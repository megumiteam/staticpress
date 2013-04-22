<?php
/*
Plugin Name: Static Static
Author: wokamoto 
Plugin URI: http://www.digitalcube.jp/
Description: 静的書き出しプラグイン
Version: 0.1.0
Author URI: http://www.digitalcube.jp/
Domain Path: /languages
Text Domain: 
*/
if (!class_exists('static_static_admin'))
	require_once(dirname(__FILE__).'/includes/class-admin-menu.php');
if (!class_exists('static_static'))
	require_once(dirname(__FILE__).'/includes/class-static_static.php');

$static_admin = new static_static_admin(plugin_basename(__FILE__));
$static = new static_static(plugin_basename(__FILE__), $static_admin->static_url(), $static_admin->static_dir());
register_activation_hook(__FILE__, array($static, 'activation'));

/*
$static->insert_all_url();

while ($url = $static->fetch_url()) {
	$static_file = $static->create_static_file($url->url, $url->type, true, true);
	echo "{$url->ID} {$url->type} : {$url->url} → {$static_file}\n";
	if ($url->pages > 1) {
		for ($page = 2; $page <= $url->pages; $page++) {
			$page_url = untrailingslashit(trim($url->url));
			$static_file = false;
			switch($url->type){
			case 'term_archive':
			case 'author_archive':
			case 'other_page':
				$page_url = sprintf('%s/page/%d', $page_url, $page);
				$static_file = $static->create_static_file($page_url, 'other_page', false, true);
				break;
			case 'single':
				$page_url = sprintf('%s/%d', $page_url, $page) . "\n";
				$static_file = $static->create_static_file($page_url, 'other_page', false, true);
				break;
			}
			if (!$static_file)
				break;
			echo "{$url->ID} ({$page}) : {$page_url} → {$static_file}\n";
		}
	} else if ($url->type == 'front_page') {
		$page = 2;
		$page_url = sprintf('%s/page/%d', untrailingslashit(trim($url->url)), $page);
		while($static_file = $static->create_static_file($page_url, 'other_page', false, true)){
			echo "{$url->ID} ({$page}) : {$page_url} → {$static_file}\n";
			$page++;
			$page_url = sprintf('%s/page/%d', untrailingslashit($url->url), $page);
		}
	}
}
*/