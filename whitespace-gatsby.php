<?php
/**
 * Plugin Name: Whitespace Gatsby integration
 * Plugin URI: -
 * Description: Adds preview and other integrations with Gatsby
 * Version: 0.1.0
 * Author: Whitespace
 * Author URI: https://www.whitespace.se/
 */

namespace WhitespaceGatsby;

use GraphQLRelay\Relay;

function gatsby_get_base_url() {
  if (defined("GATSBY_BASE_URL")) {
    return constant("GATSBY_BASE_URL");
  }
  return false;
}

add_action("template_redirect", function () {
  if (!is_user_logged_in()) {
    wp_redirect(admin_url());
    exit();
  }

  if (is_preview()) {
    return;
  }

  if (is_singular()) {
    $base_url = gatsby_get_base_url();
    if ($base_url) {
      $redirect_uri = str_replace(
        home_url(),
        gatsby_get_base_url(),
        get_permalink(),
      );
      header("Location: " . $redirect_uri);
      exit();
    }
  }
});

function encrypt_user($user) {
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

function decrypt_user($string) {
  $gatsby_encrypt = get_option("gatsby_encrypt");
  return openssl_decrypt(
    $string,
    $gatsby_encrypt["cipher"],
    NONCE_KEY,
    0,
    base64_decode($gatsby_encrypt["iv"]),
  );
}

/**
 * Gatsby Preview
 */
if (defined("GATSBY_PREVIEW_ENDPOINT")) {
  add_filter("preview_post_link", function ($link) {
    global $post;
    if ($post->post_type == "revision") {
      $parent_post = get_post($post->post_parent);
    } else {
      $parent_post = $post;
    }
    $id = Relay::toGlobalId($parent_post->post_type, $parent_post->ID);
    return GATSBY_PREVIEW_ENDPOINT .
      "?" .
      http_build_query([
        "id" => $id,
        "user" => encrypt_user(wp_get_current_user()->ID),
        "wpnonce" => wp_create_nonce("wp_rest"),
        "contentType" => $parent_post->post_type,
      ]);
  });
  add_filter("determine_current_user", function ($user_id) {
    if (isset($_SERVER["HTTP_X_WP_USER"])) {
      $user_id = decrypt_user($_SERVER["HTTP_X_WP_USER"]);
    }
    return $user_id;
  });
  add_filter("graphql_access_control_allow_headers", function ($headers) {
    return array_merge($headers, ["x-wp-user"]);
  });
}

/**
 * Gatsby Refresh
 */
add_filter("gatsby_refresh_endpoints", function ($endpoints) {
  if (defined("GATSBY_REFRESH_ENDPOINTS") && !empty(GATSBY_REFRESH_ENDPOINTS)) {
    $endpoints = GATSBY_REFRESH_ENDPOINTS;
  }
  if (!is_array($endpoints)) {
    $endpoints = explode(",", GATSBY_REFRESH_ENDPOINTS);
  }
  return $endpoints;
});

function gatsby_refresh_endpoints() {
  return apply_filters("gatsby_refresh_endpoints", []);
}

add_action(
  "save_post",
  function ($post_id, $post) {
    $gatsby_refresh_endpoints = gatsby_refresh_endpoints();
    if (empty($gatsby_refresh_endpoints)) {
      return;
    }
    if (!(wp_is_post_revision($post) || wp_is_post_autosave($post))) {
      foreach ($gatsby_refresh_endpoints as $url) {
        $user = parse_url($url, PHP_URL_USER);
        $pass = parse_url($url, PHP_URL_PASS);
        // error_log(var_export(["gatsby refresh" => $url], true));
        $curl_refresh_gatsby = curl_init($url);
        curl_setopt($curl_refresh_gatsby, CURLOPT_POST, true);
        curl_setopt($curl_refresh_gatsby, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_refresh_gatsby, CURLOPT_RETURNTRANSFER, true);
        if ($user && $pass) {
          curl_setopt($curl_refresh_gatsby, CURLOPT_USERPWD, "$user:$pass");
          curl_setopt($curl_refresh_gatsby, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }
        $response = curl_exec($curl_refresh_gatsby);
        // if ($error = curl_error($curl_refresh_gatsby)) {
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
  },
  10,
  2,
);
