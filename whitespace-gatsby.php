<?php
/**
 * Plugin Name: Whitespace Gatsby integration
 * Plugin URI: -
 * Description: Adds preview and other integrations with Gatsby
 * Version: 0.1.0
 * Author: Whitespace
 * Author URI: https://www.whitespace.se/
 * Text Domain: whitespace-gatsby
 */

define("WHITESPACE_GATSBY_PLUGIN_FILE", __FILE__);
define("WHITESPACE_GATSBY_PATH", dirname(__FILE__));
define("WHITESPACE_GATSBY_AUTOLOAD_PATH", WHITESPACE_GATSBY_PATH . "/autoload");

add_action("init", function () {
  $path = plugin_basename(dirname(__FILE__)) . "/languages";
  load_plugin_textdomain("whitespace-gatsby", false, $path);
  load_muplugin_textdomain("whitespace-gatsby", $path);
});

array_map(static function () {
  include_once func_get_args()[0];
}, glob(WHITESPACE_GATSBY_AUTOLOAD_PATH . "/*.php"));
