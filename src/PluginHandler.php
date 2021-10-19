<?php

namespace Whitespace\Gatsby;

class PluginHandler {
  public const OPTION_NAME = "whitespace_gatsby";
  public const OPTION_GROUP = "whitespace_gatsby";
  public const OPTION_SECTIONS = "whitespace-gatsby";

  private $handlers;

  public function __construct() {
    add_action("admin_menu", [$this, "onAdminMenu"]);
    add_action("admin_init", [$this, "onAdminInit"]);
    $this->handlers = [];
  }

  public function registerHandler($name, $handler) {
    $this->handlers[$name] = $handler;
  }

  public function getHandler($name) {
    return $this->handlers[$name] ?? null;
  }

  public function onAdminMenu() {
    add_options_page(
      __("Headless settings", "whitespace-gatsby"),
      __("Headless", "whitespace-gatsby"),
      "manage_options",
      __("whitespace-gatsby"),
      [$this, "renderOptionsPage"],
    );
  }
  public function renderOptionsPage(): void {
    ?>
      <div class="wrap">
          <h1><?php _e("Headless settings", "whitespace-gatsby"); ?></h1>
          <form method="post" action="options.php">
          <?php
          // This prints out all hidden setting fields
          settings_fields(self::OPTION_GROUP);
          do_settings_sections(self::OPTION_SECTIONS);
          submit_button();?>
          </form>
      </div>
      <?php
  }

  public function onAdminInit() {
    register_setting(self::OPTION_GROUP, self::OPTION_NAME, [
      $this,
      "sanitizeOption",
    ]);
  }

  public function sanitizeOption($input) {
    $input = apply_filters("WhitespaceGatsby/sanitize_option", $input);
    return $input;
  }

  public function getOption(string $name = null, $default = null) {
    $option_value = get_option(self::OPTION_NAME, []);
    if ($name) {
      $option_value = $option_value[$name] ?? $default;
    }
    return $option_value;
  }

  public function setOption(string $name, $value): bool {
    $option_value = get_option(self::OPTION_NAME, []);
    $option_value[$name] = $value;
    return update_option(self::OPTION_NAME, $option_value);
  }
}
