# Theme Manager Module

This module provides REST API endpoints for theme management in WordPress, allowing users to search, install, activate, and deactivate themes.

## REST API Endpoints

### Core WordPress Endpoints (Already Implemented)

- `GET /wp/v2/themes` - Lists all installed themes
- `GET /wp/v2/themes/<stylesheet>` - Gets details of a specific installed theme

### Custom Endpoints

#### Search WordPress.org Theme Repository

- **Endpoint:** `GET /angie/v1/themes/search`
- **Description:** Searches the WordPress.org theme repository
- **Parameters:**
  - `search` (string, required): Search term
  - `page` (integer, optional): Page number for pagination
  - `per_page` (integer, optional): Number of results per page
- **Permissions:** Requires `install_themes` capability

#### Get Theme Details from WordPress.org

- **Endpoint:** `GET /angie/v1/themes/info/{slug}`
- **Description:** Gets detailed information about a specific theme from WordPress.org
- **Parameters:**
  - `slug` (string, required): Theme slug
- **Permissions:** Requires `install_themes` capability

#### Install Theme

- **Endpoint:** `POST /angie/v1/themes/install`
- **Description:** Installs a theme from WordPress.org
- **Parameters:**
  - `slug` (string, required): Theme slug to install
- **Permissions:** Requires `install_themes` capability

#### Activate Theme

- **Endpoint:** `POST /angie/v1/themes/activate`
- **Description:** Activates an installed theme
- **Parameters:**
  - `stylesheet` (string, required): Theme stylesheet identifier
- **Permissions:** Requires `switch_themes` capability

#### Deactivate Theme

- **Endpoint:** `POST /angie/v1/themes/deactivate`
- **Description:** Deactivates the current theme by switching to the default theme
- **Parameters:** None
- **Permissions:** Requires `switch_themes` capability

#### Delete Theme

- **Endpoint:** `POST /angie/v1/themes/delete`
- **Description:** Deletes an installed theme
- **Parameters:**
  - `stylesheet` (string, required): Theme stylesheet identifier
- **Permissions:** Requires `delete_themes` capability

## Testing

To test these endpoints, you need a WordPress environment with the angie.php plugin activated. You can use tools like curl or Postman to make requests to the endpoints.

### Example Requests

#### Search Themes
```
curl -X GET "http://your-wordpress-site/index.php?rest_route=/angie/v1/themes/search&search=twenty" \
  -H "X-WP-Nonce: your-nonce"
```

#### Get Theme Info
```
curl -X GET "http://your-wordpress-site/index.php?rest_route=/angie/v1/themes/info/twentytwentythree" \
  -H "X-WP-Nonce: your-nonce"
```

#### Install Theme
```
curl -X POST "http://your-wordpress-site/index.php?rest_route=/angie/v1/themes/install" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: your-nonce" \
  -d '{"slug": "twentytwentythree"}'
```

#### Activate Theme
```
curl -X POST "http://your-wordpress-site/index.php?rest_route=/angie/v1/themes/activate" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: your-nonce" \
  -d '{"stylesheet": "twentytwentythree"}'
```

#### Deactivate Theme
```
curl -X POST "http://your-wordpress-site/index.php?rest_route=/angie/v1/themes/deactivate" \
  -H "X-WP-Nonce: your-nonce"
```

#### Delete Theme
```
curl -X POST "http://your-wordpress-site/index.php?rest_route=/angie/v1/themes/delete" \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: your-nonce" \
  -d '{"stylesheet": "twentytwentythree"}'
```

## Error Handling

All endpoints implement proper error handling with appropriate HTTP status codes:

- 400 Bad Request: Invalid parameters
- 401 Unauthorized: User doesn't have required capabilities
- 404 Not Found: Theme not found
- 409 Conflict: Theme already installed/activated or cannot be deleted (active/default theme)
- 500 Internal Server Error: Installation/activation/deletion failure

## Security Considerations

1. **Capability Checks:** All endpoints verify that the user has the appropriate capabilities
2. **Input Validation:** All user inputs are sanitized and validated before use
3. **Theme Validation:** Themes are validated against the WordPress.org repository before installation
