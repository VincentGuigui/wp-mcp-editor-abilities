# MCP Editor Abilities

**Author:** Vincent Guigui

A WordPress plugin that exposes content-management abilities through the [MCP Adapter](https://github.com/wordpress/mcp-adapter), enabling AI agents and MCP clients to read and edit WordPress content programmatically.

## What it does

1. **Forces all WordPress abilities to be MCP-public** — any ability registered by core or other plugins is automatically exposed through MCP (via the `wp_register_ability_args` filter).

2. **Registers 12 custom editor abilities:**

### Posts
| Ability | Description | Permission |
|---|---|---|
| `mcp-editor/list-posts` | Paginated list of posts (filterable by status) | `edit_posts` |
| `mcp-editor/add-post` | Create a new post (defaults to draft) | `edit_posts` |
| `mcp-editor/duplicate-post` | Duplicate an existing post as a new draft | `edit_post` |
| `mcp-editor/update-post-field` | Update a single post field (title, status, date, excerpt, slug, content) | `edit_post` |

### Post Blocks
| Ability | Description | Permission |
|---|---|---|
| `mcp-editor/list-post-blocks` | List all Gutenberg blocks in a post | `edit_post` |
| `mcp-editor/update-post-block` | Edit a specific block's attributes or HTML | `edit_post` |

### Categories
| Ability | Description | Permission |
|---|---|---|
| `mcp-editor/list-categories` | Paginated list of categories | `manage_categories` |
| `mcp-editor/add-category` | Create a new category | `manage_categories` |
| `mcp-editor/update-category-field` | Update a single category field (name, slug, description, parent) | `manage_categories` |

### Post ↔ Category
| Ability | Description | Permission |
|---|---|---|
| `mcp-editor/assign-post-category` | Add a category to a post | `edit_post` |
| `mcp-editor/remove-post-category` | Remove a category from a post | `edit_post` |

### Site
| Ability | Description | Permission |
|---|---|---|
| `mcp-editor/update-site-field` | Update a site option (name, description) | `manage_options` |

3. **Boots the MCP Adapter** so the abilities are reachable over the MCP JSON-RPC endpoint.

## Requirements

- **WordPress 6.9+** (ships the Abilities API)
- **MCP Adapter plugin** installed and activated — [github.com/wordpress/mcp-adapter](https://github.com/wordpress/mcp-adapter)
- An **application password** for the WordPress user that will authenticate MCP requests

## Installation

1. Copy the `mcp-edit-abilities` folder into `wp-content/plugins/`.
2. Activate **MCP Adapter** in the WordPress admin.
3. Activate **MCP Editor Abilities** in the WordPress admin.
4. Create an application password for your user under **Users → Profile → Application Passwords**.

## Testing with the batch script

A `mcp.bat` script is included for quick testing from the Windows command line.

### Configuration

Credentials are stored in `mcp-config.ini` (same folder as the batch). This file is protected from web access by the included `.htaccess`.

```ini
; mcp-config.ini
[mcp]
url=https://your-site
username=your-user
password=xxxx xxxx xxxx xxxx xxxx xxxx
```

### Usage

```batch
:: List all available abilities
mcp.bat

:: Execute abilities without parameters
mcp.bat mcp-editor/list-posts
mcp.bat mcp-editor/list-categories

:: Execute abilities with JSON parameters
mcp.bat mcp-editor/list-posts "{\"per_page\":5}"
mcp.bat mcp-editor/add-post "{\"title\":\"My New Post\"}"
mcp.bat mcp-editor/duplicate-post "{\"id\":1}"
mcp.bat mcp-editor/update-post-field "{\"id\":1,\"field\":\"title\",\"value\":\"New Title\"}"
mcp.bat mcp-editor/list-post-blocks "{\"id\":1}"
mcp.bat mcp-editor/update-post-block "{\"id\":1,\"block_index\":0,\"inner_html\":\"<p>Updated</p>\"}"
mcp.bat mcp-editor/add-category "{\"name\":\"My Category\"}"
mcp.bat mcp-editor/update-category-field "{\"id\":12,\"field\":\"name\",\"value\":\"Renamed\"}"
mcp.bat mcp-editor/assign-post-category "{\"post_id\":1,\"category_id\":12}"
mcp.bat mcp-editor/remove-post-category "{\"post_id\":1,\"category_id\":12}"
mcp.bat mcp-editor/update-site-field "{\"field\":\"name\",\"value\":\"My Site\"}"
```

## Security notes

- All abilities enforce WordPress capability checks (`edit_post`, `manage_options`, `manage_categories`).
- The `mcp-config.ini` file containing credentials is blocked from web access via `.htaccess`.
- MCP requests require HTTP Basic Auth with an application password.
- For production use, consider moving `mcp-config.ini` outside the web root entirely.

## License

GPL-2.0-or-later
