@echo off
setlocal enabledelayedexpansion

:: ============================================================
::  MCP Editor Abilities - Test
::  Usage:
::    mcp.bat                                           -> list abilities
::    mcp.bat mcp-edit-abilities/list-posts             -> execute
::    mcp.bat mcp-edit-abilities/edit-post-title "{\"id\":1,\"title\":\"New\"}"
:: ============================================================

:: Load config from mcp-config.ini (next to this script)
set "CONFIG_FILE=%~dp0mcp-config.ini"
if not exist "%CONFIG_FILE%" (
    echo [FAIL] Config file not found: %CONFIG_FILE%
    echo Create mcp-config.ini with [mcp] section containing url, username, password.
    goto :eof
)

set "URL="
set "USERNAME="
set "PASSWORD="
for /f "usebackq tokens=1,* delims==" %%K in ("%CONFIG_FILE%") do (
    set "line=%%K"
    if not "!line:~0,1!"==";" if not "!line:~0,1!"=="[" (
        set "key=%%K"
        set "val=%%L"
        for /f "tokens=*" %%T in ("!key!") do set "key=%%T"
        for /f "tokens=*" %%T in ("!val!") do set "val=%%T"
        if /i "!key!"=="url"      set "URL=!val!"
        if /i "!key!"=="username" set "USERNAME=!val!"
        if /i "!key!"=="password" set "PASSWORD=!val!"
    )
)

if "!URL!"=="" (
    echo [FAIL] Could not read URL from %CONFIG_FILE%
    goto :eof
)

set "ABILITY=%~1"
set "PARAMS=%~2"
set "ENDPOINT=!URL!/wp-json/mcp/mcp-adapter-default-server"
set "HEADERS_TMP=%TEMP%\mcp_headers.tmp"
set "RESP_TMP=%TEMP%\mcp_resp.tmp"

:: ------------------------------------------------------------
:: Initialize session
:: ------------------------------------------------------------
curl -k -s -u "!USERNAME!:!PASSWORD!" -X POST "!ENDPOINT!" ^
  -H "Content-Type: application/json" ^
  -D "!HEADERS_TMP!" ^
  -d "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"initialize\",\"params\":{\"protocolVersion\":\"2025-03-26\",\"capabilities\":{},\"clientInfo\":{\"name\":\"batch-test\",\"version\":\"1.0\"}}}" ^
  > nul 2>&1

:: Extract session ID from headers
set "SESSION_ID="
for /f "tokens=2 delims= " %%A in ('findstr /i "Mcp-Session-Id:" "!HEADERS_TMP!" 2^>nul') do set "SESSION_ID=%%A"
:: Trim trailing carriage return
if defined SESSION_ID set "SESSION_ID=!SESSION_ID: =!"

if not defined SESSION_ID (
    echo [FAIL] Could not obtain session ID. Check URL and credentials.
    if exist "!HEADERS_TMP!" type "!HEADERS_TMP!"
    goto :end
)

echo Session: !SESSION_ID!
echo.

:: ------------------------------------------------------------
:: No argument: list abilities
:: ------------------------------------------------------------
if "!ABILITY!"=="" (
    echo Abilities on !ENDPOINT!:
    echo.
    curl -k -s -u "!USERNAME!:!PASSWORD!" -X POST "!ENDPOINT!" ^
      -H "Content-Type: application/json" ^
      -H "Mcp-Session-Id: !SESSION_ID!" ^
      -d "{\"jsonrpc\":\"2.0\",\"id\":2,\"method\":\"tools/call\",\"params\":{\"name\":\"mcp-adapter-discover-abilities\",\"arguments\":{}}}" > "!RESP_TMP!"
    powershell -NoProfile -Command "$j=Get-Content '!RESP_TMP!'|ConvertFrom-Json; $a=$j.result.structuredContent.abilities; if($a){$a|ForEach-Object{Write-Host ('* '+$_.name+' : '+$_.label); Write-Host ('  '+$_.description); Write-Host}} else{Write-Host 'No abilities found or unexpected response:'; Get-Content '!RESP_TMP!'}"
    if exist "!RESP_TMP!" del "!RESP_TMP!"
    goto :end
)

:: ------------------------------------------------------------
:: Argument given: execute that ability (optional 2nd arg = JSON params)
:: ------------------------------------------------------------
echo Executing: !ABILITY!
if not "!PARAMS!"=="" echo Params:    !PARAMS!
echo.

if "!PARAMS!"=="" set "PARAMS={}"

curl -k -s -u "!USERNAME!:!PASSWORD!" -X POST "!ENDPOINT!" ^
  -H "Content-Type: application/json" ^
  -H "Mcp-Session-Id: !SESSION_ID!" ^
  -d "{\"jsonrpc\":\"2.0\",\"id\":2,\"method\":\"tools/call\",\"params\":{\"name\":\"mcp-adapter-execute-ability\",\"arguments\":{\"ability_name\":\"!ABILITY!\",\"parameters\":!PARAMS!}}}" > "!RESP_TMP!"

powershell -NoProfile -Command "Get-Content '!RESP_TMP!' | ConvertFrom-Json | ConvertTo-Json -Depth 10"
echo.

if exist "!RESP_TMP!" del "!RESP_TMP!"

:end
if exist "!HEADERS_TMP!" del "!HEADERS_TMP!"
endlocal
