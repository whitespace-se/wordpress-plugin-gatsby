# Whitespace Gatsby integration for Wordpress

Wordpress plugin that adds preview and other integrations with Gatsby.

## How to install

If you want to use this plugin as an MU-plugin, first add this to your
composer.json:

```json
{
  "extra": {
    "installer-paths": {
      "path/to/your/mu-plugins/{$name}/": [
        "whitespace-se/wordpress-plugin-gatsby"
      ]
    }
  }
}
```

Where `path/to/your/mu-plugins` is something like `wp-content/mu-plugins` or
`web/app/mu-plugins`.

Then get the plugin via composer:

```bash
composer require whitespace-se/wordpress-plugin-gatsby
```

## Configuration

### Preview

Define the `GATSBY_PREVIEW_ENDPOINT` constant in you config (e.g. in
`wp-config.php`) to set up the preview, for example:

```php
define("GATSBY_PREVIEW_ENDPOINT", "https://example.com/wp-preview");
```

This url will be appended with a query string containing these parameters:

- `id` – The GraphQL ID for the post
- `user` => The encrypted ID of the user that is previewing the post
- `wpnonce` => A `wp_rest` nonce
- `contentType` => The content type of the post (deprecated)

You should use GraphQL to request data for the preview and that request must
contain the `x-wp-user` and `x-wp-nonce` headers, containing the values received
in the query string.

The GraphQL query may look like this, where `$id` is the GraphQL ID for the post
received in the query string:

```graphql
query PreviewQuery($id: ID!) {
  wp {
    contentNode(id: $id, asPreview: true) {
      # ...
    }
  }
}
```

### Refresh on save

Define the `GATSBY_REFRESH_ENDPOINTS` constant in you config (e.g. in
`wp-config.php`) if you need to trigger a Gatsby refresh when posts are updated.
Example:

```php
define("GATSBY_REFRESH_ENDPOINTS", "http://localhost:8000/__refresh");
```

The value can be a single URL or multiple URLs either as an array or
comma-separated in a single string. You can also use the
`gatsby_refresh_endpoints` filter to alter the value.
