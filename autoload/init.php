<?php

use Whitespace\Gatsby\PluginHandler;
use Whitespace\Gatsby\PreviewHandler;
use Whitespace\Gatsby\RedirectHandler;
use Whitespace\Gatsby\RefreshHandler;

class WhitespaceGatsby {
  private static ?PluginHandler $pluginHandler = null;
  public static function init() {
    if (is_null(self::$pluginHandler)) {
      self::$pluginHandler = new PluginHandler();
      add_action("plugins_loaded", function () {
        do_action("WhitespaceGatsby/init", self::$pluginHandler);
      });
    }
  }
  public static function __callStatic($name, $arguments) {
    return self::$pluginHandler->$name(...$arguments);
  }
  public static function getPluginHandler(): PluginHandler {
    return self::$pluginHandler;
  }
}

WhitespaceGatsby::init();

add_action("WhitespaceGatsby/init", function ($pluginHandler) {
  $pluginHandler->registerHandler(
    "preview",
    new PreviewHandler($pluginHandler),
  );
  $pluginHandler->registerHandler(
    "redirect",
    new RedirectHandler($pluginHandler),
  );
  $pluginHandler->registerHandler(
    "refresh",
    new RefreshHandler($pluginHandler),
  );
});
