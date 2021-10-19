<?php
namespace Whitespace\Gatsby;

use GraphQLRelay\Relay;
use Whitespace\Gatsby\PluginHandler;

class PreviewHandler {
  public const OPTION_SECTION = "preview";

  private PluginHandler $pluginHandler;

  public function __construct(PluginHandler $plugin_handler) {
    $this->pluginHandler = $plugin_handler;

    add_filter("preview_post_link", [$this, "filterPreviewPostLink"]);
    add_filter("determine_current_user", [$this, "filterDetermineCurrentUser"]);
    add_filter("graphql_access_control_allow_headers", [
      $this,
      "filterGraphqlAccessControlAllowHeaders",
    ]);
  }

  private function encrypt_user($user) {
    $gatsby_encrypt = get_option("gatsby_encrypt");
    if (empty($gatsby_encrypt)) {
      $cipher = "AES-256-CBC";
      $ivlen = openssl_cipher_iv_length($cipher);
      $iv = openssl_random_pseudo_bytes($ivlen);
      $gatsby_encrypt = ["iv" => base64_encode($iv), "cipher" => $cipher];
      add_option("gatsby_encrypt", $gatsby_encrypt);
    }
    return openssl_encrypt(
      $user,
      $gatsby_encrypt["cipher"],
      NONCE_KEY,
      0,
      base64_decode($gatsby_encrypt["iv"]),
    );
  }

  private function decrypt_user($string) {
    $gatsby_encrypt = get_option("gatsby_encrypt");
    return openssl_decrypt(
      $string,
      $gatsby_encrypt["cipher"],
      NONCE_KEY,
      0,
      base64_decode($gatsby_encrypt["iv"]),
    );
  }

  public function onAdminInit() {
    add_settings_section(
      self::OPTION_SECTION,
      __("Preview", "whitespace-gatsby"),
      null,
      $this->pluginHandler::OPTION_SECTIONS,
    );

    add_settings_field(
      "preview_endpoint",
      __("Preview endpoint", "whitespace-gatsby"),
      function () {
        $overriden =
          defined("GATSBY_PREVIEW_ENDPOINT") &&
          !is_null(\GATSBY_PREVIEW_ENDPOINT); ?>
        <input
          id="preview_endpoint"
          type="url"
          name="<?php echo $this->pluginHandler
            ::OPTION_NAME; ?>[preview_endpoint]"
          <?php if ($overriden) {
            echo "disabled";
          } ?>
          value="<?php echo htmlspecialchars(
            implode(
              "\n",
              $this->pluginHandler->getOption("preview_endpoint", []),
            ),
          ); ?>"
        >
        <?php
      },
      $this->pluginHandler::OPTION_SECTIONS,
      self::OPTION_SECTION,
    );
  }

  public function getPreviewEndpoint(): array {
    if (
      defined("GATSBY_PREVIEW_ENDPOINT") &&
      !is_null(\GATSBY_PREVIEW_ENDPOINT)
    ) {
      $endpoint = \GATSBY_PREVIEW_ENDPOINT;
    } else {
      $endpoint = $this->pluginHandler->getOption("preview_endpoint");
    }
    $endpoint = apply_filters("WhitespaceGatsby/preview_endpoint", $endpoint);
    return $endpoint;
  }

  public function filterPreviewPostLink($link) {
    global $post;
    if ($post->post_type == "revision") {
      $parent_post = get_post($post->post_parent);
    } else {
      $parent_post = $post;
    }
    $endpoint = $this->getPreviewEndpoint();
    if (!empty($endpoint)) {
      $id = Relay::toGlobalId($parent_post->post_type, $parent_post->ID);
      $link =
        $endpoint .
        "?" .
        http_build_query([
          "id" => $id,
          "user" => $this->encrypt_user(wp_get_current_user()->ID),
          "wpnonce" => wp_create_nonce("wp_rest"),
          "contentType" => $parent_post->post_type,
        ]);
    }
    return $link;
  }

  public function filterDetermineCurrentUser($user_id) {
    if (isset($_SERVER["HTTP_X_WP_USER"])) {
      $user_id = $this->decrypt_user($_SERVER["HTTP_X_WP_USER"]);
    }
    return $user_id;
  }

  public function filterGraphqlAccessControlAllowHeaders($headers) {
    return array_merge($headers, ["x-wp-user"]);
  }
}
