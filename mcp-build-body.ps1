# Reads ability name from MCP_ABILITY env var
# Reads params JSON from MCP_PARAMS_FILE (a temp file path)
# Writes the full JSON-RPC body to MCP_OUTFILE
$ability  = $env:MCP_ABILITY
$pFile    = $env:MCP_PARAMS_FILE
$outFile  = $env:MCP_OUTFILE

$params = '{}'
if ($pFile -and (Test-Path $pFile)) {
    $params = Get-Content -Raw $pFile
}

$body = @{
    jsonrpc = "2.0"
    id      = 2
    method  = "tools/call"
    params  = @{
        name      = "mcp-adapter-execute-ability"
        arguments = @{
            ability_name = $ability
            parameters   = ($params | ConvertFrom-Json)
        }
    }
} | ConvertTo-Json -Depth 10 -Compress

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[IO.File]::WriteAllText($outFile, $body, $utf8NoBom)
