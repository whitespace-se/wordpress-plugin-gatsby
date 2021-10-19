<?php
namespace Whitespace\Gatsby;

use Whitespace\Gatsby\PluginHandler;

class RefreshHandler {
  public const OPTION_SECTION = "refresh";

  private PluginHandler $pluginHandler;

  public function __construct(PluginHandler $plugin_handler) {
    $this->pluginHandler = $plugin_handler;

    add_action("save_post", [$this, "onSavePost"], 10, 2);
    add_action("admin_init", [$this, "onAdminInit"]);
    add_filter("WhitespaceGatsby/sanitize_option", [$this, "sanitizeOption"]);
  }

  public function onAdminInit() {
    add_settings_section(
      self::OPTION_SECTION,
      __("Gatsby refresh", "whitespace-gatsby"),
      null,
      $this->pluginHandler::OPTION_SECTIONS,
    );

    add_settings_field(
      "refresh_endpoints",
      __("Refresh endpoints", "whitespace-gatsby"),
      function () {
        $overriden =
          defined("GATSBY_REFRESH_ENDPOINTS") &&
          !is_null(\GATSBY_REFRESH_ENDPOINTS); ?>
        <textarea
          id="refresh_endpoints"
          name="<?php echo $this->pluginHandler
            ::OPTION_NAME; ?>[refresh_endpoints]"
          rows="5"
          cols="40"
          style="width: 100%;"
          <?php if ($overriden) {
            echo "disabled";
          } ?>
        ><?php echo htmlspecialchars(
          implode(
            "\n",
            $this->pluginHandler->getOption("refresh_endpoints", []),
          ),
          ENT_NOQUOTES,
        ); ?></textarea>

        <div class="description">
          <p><?php _e(
            "URLs that will be requested each time a post is updated. One per line.",
            "whitespace-gatsby",
          ); ?></p>
          <p><?php _e("Available replacements", "whitespace-gatsby"); ?>:</p>
          <ul>
            <li><code>{DESCRIPTION}</code> &ndash; <?php _e(
              "The reason for the notification, e.g. \"Post 42 updated in WordPress\"",
              "whitespace-gatsby",
            ); ?></li>
            <li><code>{BLOG_ID}</code> &ndash; <?php _e(
              "Blog ID (for multisites)",
              "whitespace-gatsby",
            ); ?></li>
            <li><code>{BLOG_DOMAIN}</code> &ndash; <?php _e(
              "Blog domain (for multisites)",
              "whitespace-gatsby",
            ); ?></li>
            <li><code>{BLOG_PATH}</code> &ndash; <?php _e(
              "Blog path without leading or trailing slash (for multisites)",
              "whitespace-gatsby",
            ); ?></li>
          </ul>
        </div>
        <?php
      },
      $this->pluginHandler::OPTION_SECTIONS,
      self::OPTION_SECTION,
    );
  }

  public function sanitizeOption($input) {
    $input["refresh_endpoints"] = isset($input["refresh_endpoints"])
      ? $this->splitUserInput($input["refresh_endpoints"])
      : [];
    return $input;
  }

  private static function splitUserInput($input) {
    if (empty($input)) {
      return [];
    }
    preg_match_all('/([^,\s][^,]+?)\s*(?:,|$)/m', $input, $matches);
    return $matches[1] ?? [];
  }

  public function getRefreshEndpoints(): array {
    if (
      defined("GATSBY_REFRESH_ENDPOINTS") &&
      !is_null(\GATSBY_REFRESH_ENDPOINTS)
    ) {
      $endpoints = \GATSBY_REFRESH_ENDPOINTS;
      if (!is_array($endpoints)) {
        $endpoints = self::splitUserInput($endpoints);
      }
    } else {
      $endpoints = $this->pluginHandler->getOption("refresh_endpoints", []);
    }

    /**
     * @deprecated Use "WhitespaceGatsby/refresh_endpoints" filter instead.
     */
    $endpoints = apply_filters("gatsby_refresh_endpoints", $endpoints);

    $endpoints = apply_filters(
      "WhitespaceGatsby/refresh_endpoints",
      $endpoints,
    );
    return $endpoints;
  }

  public function setRefreshEndpoints(array $urls): bool {
    return $this->pluginHandler->setOption("refresh_endpoints", $urls);
  }

  public function onSavePost($post_id, $post): void {
    if (!wp_is_post_revision($post) && !wp_is_post_autosave($post)) {
      $this->sendRefreshRequest($post);
    }
  }

  public function sendRefreshRequest($post) {
    $gatsby_refresh_endpoints = $this->getRefreshEndpoints();
    if (empty($gatsby_refresh_endpoints)) {
      return;
    }
    foreach ($gatsby_refresh_endpoints as $url) {
      $user = parse_url($url, PHP_URL_USER);
      $pass = parse_url($url, PHP_URL_PASS);
      // error_log(var_export(["gatsby refresh" => $url], true));
      $handle = curl_init($url);
      curl_setopt($handle, CURLOPT_POST, true);
      curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
      if ($user && $pass) {
        curl_setopt($handle, CURLOPT_USERPWD, "$user:$pass");
        curl_setopt($handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
      }
      $response = curl_exec($handle);
      // if ($error = curl_error($handle)) {
      //   error_log(
      //     var_export(["curl error on gatsby refresh" => $error], true),
      //   );
      // } else {
      //   error_log(
      //     var_export(["curl response on gatsby refresh" => $response], true),
      //   );
      // }
    }
  }
}
