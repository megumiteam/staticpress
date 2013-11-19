<?php
/*
Plugin Name: StaticPress
Author: wokamoto
Plugin URI: https://github.com/megumiteam/staticpress
Description: Transform your WordPress into static websites and blogs.
Version: 0.4.3.4
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
	require(dirname(__FILE__).'/includes/class-static_press_admin.php');
if (!class_exists('static_press'))
	require(dirname(__FILE__).'/includes/class-static_press.php');

load_plugin_textdomain(static_press_admin::TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');

$staticpress = new static_press(
	plugin_basename(__FILE__),
	static_press_admin::static_url(),
	static_press_admin::static_dir(),
	static_press_admin::remote_get_option()
	);

add_filter('StaticPress::get_url', array($staticpress, 'replace_url'));
add_filter('StaticPress::static_url', array($staticpress, 'static_url'));
add_filter('StaticPress::put_content', array($staticpress, 'rewrite_generator_tag'), 10, 2);
add_filter('StaticPress::put_content', array($staticpress, 'add_last_modified'), 10, 2);
add_filter('StaticPress::put_content', array($staticpress, 'remove_link_tag'), 10, 2);
add_filter('StaticPress::put_content', array($staticpress, 'replace_relative_URI'), 10, 2);

register_activation_hook(__FILE__, array($staticpress, 'activate'));
register_deactivation_hook(__FILE__, array($staticpress, 'deactivate'));

if (is_admin())
	new static_press_admin(plugin_basename(__FILE__));
