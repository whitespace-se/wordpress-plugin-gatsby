<?php
namespace Whitespace\Gatsby;

use Whitespace\Gatsby\PluginHandler;

class RedirectHandler {
  public const OPTION_SECTION = "redirect";

  private PluginHandler $pluginHandler;

  public function __construct(PluginHandler $plugin_handler) {
    $this->pluginHandler = $plugin_handler;

    add_action("admin_init", [$this, "onAdminInit"]);
    add_action("template_redirect", [$this, "onTemplateRedirect"]);
  }

  public function onAdminInit() {
    add_settings_section(
      self::OPTION_SECTION,
      __("Redirection to front-end", "whitespace-gatsby"),
      null,
      $this->pluginHandler::OPTION_SECTIONS,
    );

    add_settings_field(
      "base_url",
      __("Front-end base URL", "whitespace-gatsby"),
      function () {
        $overriden =
          defined("GATSBY_BASE_URL") && !is_null(\GATSBY_BASE_URL); ?>
        <input
          id="base_url"
          type="url"
          size="50"
          name="<?php echo $this->pluginHandler::OPTION_NAME; ?>[base_url]"
          <?php if ($overriden) {
            echo "disabled";
          } ?>
          value="<?php echo htmlspecialchars(
            implode("\n", $this->pluginHandler->getOption("base_url", [])),
          ); ?>"
        >
        <?php
      },
      $this->pluginHandler::OPTION_SECTIONS,
      self::OPTION_SECTION,
    );
  }

  public function getBaseUrl(): array {
    if (defined("GATSBY_BASE_URL") && !is_null(\GATSBY_BASE_URL)) {
      $url = \GATSBY_BASE_URL;
    } else {
      $url = $this->pluginHandler->getOption("base_url");
    }
    $url = apply_filters("WhitespaceGatsby/base_url", $url);
    return $url;
  }

  public function setBaseUrl(array $urls): bool {
    return $this->pluginHandler->setOption("base_url", $urls);
  }

  public function onTemplateRedirect(): void {
    if (!is_user_logged_in()) {
      wp_redirect(admin_url());
      exit();
    }

    if (is_preview()) {
      return;
    }

    if (is_singular()) {
      $base_url = $this->getBaseUrl();
      if ($base_url) {
        $redirect_uri = str_replace(home_url(), $base_url, get_permalink());
        header("Location: " . $redirect_uri);
        exit();
      }
    }
  }
}
