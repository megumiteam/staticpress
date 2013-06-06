<?php
/*
Plugin Name: StaticPress
Author: wokamoto 
Plugin URI: https://github.com/megumiteam/staticpress
Description: Transform your WordPress into static websites and blogs.
Version: 0.4.1
Author URI: http://www.digitalcube.jp/
Text Domain: static-press
Domain Path: /languages

License:
 Released under the GPL license
  http://www.gnu.org/copyleft/gpl.html

  Copyright 2013 (email : wokamoto1973@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if (!class_exists('static_press_admin'))
	require_once(dirname(__FILE__).'/includes/class-admin-menu.php');
if (!class_exists('static_press'))
	require_once(dirname(__FILE__).'/includes/class-static_press.php');

load_plugin_textdomain(static_press_admin::TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');

$static_admin = new static_press_admin(plugin_basename(__FILE__));
$static = new static_press(
	plugin_basename(__FILE__),
	$static_admin->static_url(),
	$static_admin->static_dir(),
	$static_admin->remote_get_option()
	);
register_activation_hook(__FILE__, array($static, 'activate'));
register_deactivation_hook(__FILE__, array($static, 'deactivate'));
