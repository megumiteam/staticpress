<?php

if( !defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') )
	exit();


if (!class_exists('static_press_admin'))
	require(dirname(__FILE__).'/includes/class-static_press_admin.php');
if (!class_exists('static_press'))
	require(dirname(__FILE__).'/includes/class-static_press.php');

delete_option(static_press_admin::OPTION_STATIC_URL);
delete_option(static_press_admin::OPTION_STATIC_DIR);
delete_option(static_press_admin::OPTION_STATIC_BASIC);

delete_option(static_press_admin::FETCH_LIMIT);
delete_option(static_press_admin::FETCH_LIMIT_STATIC);
delete_option(static_press_admin::EXPIRES);
delete_option(static_press_admin::DEBUG_MODE);

global $wpdb;

$url_table = static_press::url_table();
if ($wpdb->get_var("show tables like '{$url_table}'") != $url_table)
	$wpdb->query("DROP TABLE `{$url_table}`");
