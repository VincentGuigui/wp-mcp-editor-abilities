# MCP Editor Abilities — Context

## What this is

A WordPress plugin that registers content-management abilities and exposes them via the MCP (Model Context Protocol) adapter. It acts as a bridge between AI agents and WordPress content.

## Key design decisions

- The `wp_register_ability_args` filter forces **all** abilities (core + third-party) to be MCP-public. This is intentional for development/testing — restrict in production.
- Abilities are namespaced under `mcp-editor-abilities/`.
- Credentials for the test script live in `mcp-config.ini`, protected from web access by `.htaccess`.

## References

- MCP Adapter plugin: https://github.com/wordpress/mcp-adapter
- WordPress Abilities API (WP 6.9+): https://make.wordpress.org/core/2025/03/07/abilities-api/
- MCP protocol spec: https://modelcontextprotocol.io/
- Plugin repo: https://github.com/vincentguigui/wp-mcp-editor-abilities

## Local dev

- Site URL: `https://your-site/` (self-signed cert, use `curl -k`)
- MCP endpoint: `https://your-site/wp-json/mcp/mcp-adapter-default-server`
- Test with: `mcp.bat` (no args = list abilities, with arg = execute ability)
