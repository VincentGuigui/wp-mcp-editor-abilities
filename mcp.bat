@echo off
setlocal enabledelayedexpansion

:: ============================================================
::  MCP Editor Abilities - Test
::  Usage:
::    test-mcp.bat                                           -> list abilities
::    test-mcp.bat mcp-edit-abilities/list-posts             -> execute
::    test-mcp.bat mcp-edit-abilities/edit-post-title {\"id\":1,\"title\":\"New\"}
:: ============================================================

:: Load config from mcp-config.ini (next to this script)
set "CONFIG_FILE=%~dp0mcp-config.ini"
if not exist "%CONFIG_FILE%" (
    echo [FAIL] Config file not found: %CONFIG_FILE%
    echo Create mcp-config.ini with [mcp] section containing url, username, password.
    goto :eof
)

for /f "usebackq tokens=1,* delims==" %%K in (`findstr /v /r "^;" "%CONFIG_FILE%" ^| findstr /v /r "^\[" ^| findstr "="`) do (
    set "key=%%K"
    set "val=%%L"
    for /f "tokens=*" %%T in ("!key!") do set "key=%%T"
    for /f "tokens=*" %%T in ("!val!") do set "val=%%T"
    if /i "!key!"=="url"      set "URL=!val!"
    if /i "!key!"=="username" set "USERNAME=!val!"
    if /i "!key!"=="password" set "PASSWORD=!val!"
)

set ABILITY=%~1
set PARAMS=%~2
set ENDPOINT=%URL%/wp-json/mcp/mcp-adapter-default-server
set HEADERS_TMP=%TEMP%\mcp_headers.tmp
set RESP_TMP=%TEMP%\mcp_resp.tmp
set CURL=curl -k -s -u "%USERNAME%:%PASSWORD%"

:: ------------------------------------------------------------
:: Initialize session
:: ------------------------------------------------------------
%CURL% -X POST "%ENDPOINT%" ^
  -H "Content-Type: application/json" ^
  -D "%HEADERS_TMP%" ^
  -d "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"initialize\",\"params\":{\"protocolVersion\":\"2025-03-26\",\"capabilities\":{},\"clientInfo\":{\"name\":\"batch-test\",\"version\":\"1.0\"}}}" ^
  > nul 2>&1

for /f "usebackq delims=" %%A in (`powershell -NoProfile -Command ^
  "(Get-Content '%HEADERS_TMP%' | Select-String 'Mcp-Session-Id').Line -replace '.*:\s*',''"`) do set SESSION_ID=%%A

if "%SESSION_ID%"=="" (
    echo [FAIL] Could not obtain session ID. Check URL and credentials.
    goto :end
)

:: ------------------------------------------------------------
:: No argument: list abilities
:: ------------------------------------------------------------
if "%ABILITY%"=="" (
    echo Abilities on %ENDPOINT%:
    echo.
    %CURL% -X POST "%ENDPOINT%" ^
      -H "Content-Type: application/json" ^
      -H "Mcp-Session-Id: %SESSION_ID%" ^
      -d "{\"jsonrpc\":\"2.0\",\"id\":2,\"method\":\"tools/call\",\"params\":{\"name\":\"mcp-adapter-discover-abilities\",\"arguments\":{}}}" > "%RESP_TMP%"
    powershell -NoProfile -Command "$r=(Get-Content '%RESP_TMP%'|ConvertFrom-Json).result.structuredContent.abilities; $r|%%{Write-Host ('* ' + $_.name + ' : ' + $_.label);Write-Host ('  ' + $_.description);Write-Host}"
    if exist "%RESP_TMP%" del "%RESP_TMP%"
    goto :end
)

:: ------------------------------------------------------------
:: Argument given: execute that ability (optional 2nd arg = JSON params)
:: ------------------------------------------------------------
echo Executing: %ABILITY%
if not "%PARAMS%"=="" echo Params:    %PARAMS%
echo.

if "%PARAMS%"=="" set PARAMS={}

%CURL% -X POST "%ENDPOINT%" ^
  -H "Content-Type: application/json" ^
  -H "Mcp-Session-Id: %SESSION_ID%" ^
  -d "{\"jsonrpc\":\"2.0\",\"id\":2,\"method\":\"tools/call\",\"params\":{\"name\":\"mcp-adapter-execute-ability\",\"arguments\":{\"ability_name\":\"%ABILITY%\",\"parameters\":%PARAMS%}}}" > "%RESP_TMP%"

powershell -NoProfile -Command "Get-Content '%RESP_TMP%' | ConvertFrom-Json | ConvertTo-Json -Depth 10"
echo.

if exist "%RESP_TMP%" del "%RESP_TMP%"

:end
if exist "%HEADERS_TMP%" del "%HEADERS_TMP%"
endlocal
