<?php

if( !defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') )
	exit();


if (!class_exists('static_press_admin'))
	require_once(dirname(__FILE__).'/includes/class-admin-menu.php');

delete_option(static_press_admin::OPTION_STATIC_URL);
delete_option(static_press_admin::OPTION_STATIC_DIR);
delete_option(static_press_admin::OPTION_STATIC_BASIC);

global $wpdb;

$url_table = $wpdb->prefix.'urls';
if ($wpdb->get_var("show tables like '{$url_table}'") != $url_table)
	$wpdb->query("DROP TABLE `{$url_table}`");
