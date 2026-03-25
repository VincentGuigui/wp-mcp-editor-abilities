# MCP Editor Abilities

**Author:** Vincent Guigui

A WordPress plugin that exposes content-management abilities through the [MCP Adapter](https://github.com/wordpress/mcp-adapter), enabling AI agents and MCP clients to read and edit WordPress content programmatically.

## What it does

1. **Forces all WordPress abilities to be MCP-public** — any ability registered by core or other plugins is automatically exposed through MCP (via the `wp_register_ability_args` filter).

2. **Registers 6 custom editor abilities:**

| Ability | Description | Permission |
|---|---|---|
| `mcp-editor-abilities/list-posts` | Paginated list of posts (filterable by status) | `edit_posts` |
| `mcp-editor-abilities/edit-post` | Update a post's title and/or content | `edit_post` |
| `mcp-editor-abilities/edit-post-title` | Update only the title of a post | `edit_post` |
| `mcp-editor-abilities/edit-site-name` | Change the WordPress site name | `manage_options` |
| `mcp-editor-abilities/get-post-blocks` | List all Gutenberg blocks in a post | `edit_post` |
| `mcp-editor-abilities/edit-post-block` | Edit a specific block's attributes or HTML | `edit_post` |

3. **Boots the MCP Adapter** so the abilities are reachable over the MCP JSON-RPC endpoint.

## Requirements

- **WordPress 6.9+** (ships the Abilities API)
- **MCP Adapter plugin** installed and activated — [github.com/wordpress/mcp-adapter](https://github.com/wordpress/mcp-adapter)
- An **application password** for the WordPress user that will authenticate MCP requests

## Installation

1. Copy the `mcp-editor-abilities` folder into `wp-content/plugins/`.
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
url      = https://your-site.local
username = your-user
password = xxxx xxxx xxxx xxxx xxxx xxxx
```

### Usage

```batch
:: List all available abilities
mcp.bat

:: Execute an ability (no parameters)
mcp.bat mcp-editor-abilities/list-posts

:: Execute an ability with JSON parameters
mcp.bat mcp-editor-abilities/list-posts "{\"per_page\":5}"
mcp.bat mcp-editor-abilities/get-post-blocks "{\"id\":1}"
mcp.bat mcp-editor-abilities/edit-post-title "{\"id\":1,\"title\":\"New Title\"}"
mcp.bat mcp-editor-abilities/edit-post-block "{\"id\":1,\"block_index\":0,\"inner_html\":\"<p>Updated</p>\"}"
```

## Security notes

- All abilities enforce WordPress capability checks (`edit_post`, `manage_options`, etc.).
- The `mcp-config.ini` file containing credentials is blocked from web access via `.htaccess`.
- MCP requests require HTTP Basic Auth with an application password.
- For production use, consider moving `mcp-config.ini` outside the web root entirely.

## License

GPL-2.0-or-later
