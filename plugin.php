<?php
/*
Plugin Name: Static Static
Author: wokamoto 
Plugin URI: http://www.digitalcube.jp/
Description: 静的書き出しプラグイン
Version: 0.3.1
Author URI: http://www.digitalcube.jp/
Domain Path: /languages
Text Domain: 
*/
if (!class_exists('static_static_admin'))
	require_once(dirname(__FILE__).'/includes/class-admin-menu.php');
if (!class_exists('static_static'))
	require_once(dirname(__FILE__).'/includes/class-static_static.php');

$static_admin = new static_static_admin(plugin_basename(__FILE__));
$static = new static_static(
	plugin_basename(__FILE__),
	$static_admin->static_url(),
	$static_admin->static_dir(),
	$static_admin->remote_get_option()
	);
register_activation_hook(__FILE__, array($static, 'activate'));
register_deactivation_hook(__FILE__, array($static, 'deactivate'));
