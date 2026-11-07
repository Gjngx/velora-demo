# Plugin Manager Module

This module provides access to WordPress native REST API endpoints for plugin management in WordPress, allowing users to install, activate, deactivate, and delete plugins. It also includes a custom endpoint for retrieving plugin details from WordPress.org.

## WordPress Native REST API Endpoints

The module uses the following WordPress native REST API endpoints:

- `GET /wp/v2/plugins` - Lists all installed plugins
- `GET /wp/v2/plugins/<plugin>` - Gets details of a specific installed plugin
- `POST /wp/v2/plugins` - Installs a plugin from WordPress.org
- `POST /wp/v2/plugins/<plugin>` - Updates a plugin's status (activate/deactivate)
- `DELETE /wp/v2/plugins/<plugin>` - Deletes a plugin

## Custom Endpoints for WordPress.org Repository

Since WordPress native API doesn't provide detailed information about plugins in the WordPress.org repository, we maintain this custom endpoint:

### Get Plugin Details from WordPress.org

- **Endpoint:** `GET /angie/v1/plugins/info/{slug}`
- **Description:** Gets detailed information about a specific plugin from WordPress.org
- **Parameters:**
  - `slug` (string, required): Plugin slug
- **Permissions:** Requires `install_plugins` capability

## Testing

To test these endpoints, you need a WordPress environment with the angie.php plugin activated. You can use tools like curl or Postman to make requests to the endpoints.

### Example Requests

```bash
# List all plugins
curl -X GET "http://localhost:4444/?rest_route=/wp/v2/plugins" \
  -H "Authorization: Basic {base64_encoded_credentials}"

# Install a plugin
curl -X POST "http://localhost:4444/?rest_route=/wp/v2/plugins" \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic {base64_encoded_credentials}" \
  -d '{"slug": "contact-form-7"}'

# Activate a plugin
curl -X POST "http://localhost:4444/?rest_route=/wp/v2/plugins/contact-form-7%2Fwp-contact-form-7.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic {base64_encoded_credentials}" \
  -d '{"status": "active"}'

# Deactivate a plugin
curl -X POST "http://localhost:4444/?rest_route=/wp/v2/plugins/contact-form-7%2Fwp-contact-form-7.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic {base64_encoded_credentials}" \
  -d '{"status": "inactive"}'

# Delete a plugin
curl -X DELETE "http://localhost:4444/?rest_route=/wp/v2/plugins/contact-form-7%2Fwp-contact-form-7.php" \
  -H "Content-Type: application/json" \
  -H "Authorization: Basic {base64_encoded_credentials}"

# Get plugin information from WordPress.org
curl -X GET "http://localhost:4444/?rest_route=/angie/v1/plugins/info/contact-form-7" \
  -H "Authorization: Basic {base64_encoded_credentials}"
```

## Error Handling

WordPress native REST API implements proper error handling with appropriate HTTP status codes:

- 400 Bad Request: Invalid parameters
- 401 Unauthorized: User doesn't have required capabilities
- 404 Not Found: Plugin not found
- 409 Conflict: Plugin already installed/activated
- 500 Internal Server Error: Installation/activation failure

## Security Considerations

1. **Capability Checks:** WordPress native REST API verifies that the user has the appropriate capabilities
2. **Input Validation:** All user inputs are sanitized and validated before use
3. **Plugin Validation:** Plugins are validated against the WordPress.org repository before installation

## Limitations

The WordPress native plugin REST API has the following limitations:

1. **No WordPress.org Repository Search:** The native API does not provide endpoints for searching the WordPress.org plugin repository. Users will need to use the WordPress admin interface or the WordPress.org website to find plugins before installing them.

2. **Limited Plugin Information:** The native API provides basic information about installed plugins but does not offer detailed information about plugins in the WordPress.org repository. For this reason, we provide a custom endpoint to retrieve detailed plugin information from WordPress.org.

3. **URL Encoding Required:** When referencing specific plugins in URLs, the plugin path must be URL encoded (e.g., `contact-form-7/wp-contact-form-7.php` becomes `contact-form-7%2Fwp-contact-form-7.php`).
