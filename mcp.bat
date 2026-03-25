@echo off
setlocal enabledelayedexpansion

:: ============================================================
::  MCP Editor Abilities - Test (pure batch)
::  Usage:
::    mcp.bat                                              -> list abilities
::    mcp.bat mcp-editor/list-posts                        -> execute (no params)
::    mcp.bat mcp-editor/get-post-field id 3989 field title
::    mcp.bat mcp-editor/update-site-field field name value "CNXR - Conseil National"
::
::  Parameters are key/value pairs after the ability name.
::  Wrap values containing spaces in double quotes.
:: ============================================================

:: Load config
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
set "ENDPOINT=!URL!/wp-json/mcp/mcp-adapter-default-server"
set "HEADERS_TMP=%TEMP%\mcp_headers.tmp"
set "RESP_TMP=%TEMP%\mcp_resp.tmp"
set "BODY_TMP=%TEMP%\mcp_body.tmp"

:: ------------------------------------------------------------
:: Build JSON parameters object from key/value pairs
:: Writes directly to BODY_TMP via a subroutine
:: Integer values are unquoted, string values are quoted.
:: ------------------------------------------------------------
set "_argn=0"
set "_key="
set "_paramcount=0"
:: Collect pairs into numbered vars to avoid quote issues in for loop
for %%A in (%*) do (
    set /a _argn+=1
    if !_argn! gtr 1 (
        if not defined _key (
            set "_key=%%~A"
        ) else (
            set /a _paramcount+=1
            set "_pk!_paramcount!=!_key!"
            set "_pv!_paramcount!=%%~A"
            set "_key="
        )
    )
)

:: ------------------------------------------------------------
:: Initialize session
:: ------------------------------------------------------------
curl -k -s -u "!USERNAME!:!PASSWORD!" -X POST "!ENDPOINT!" ^
  -H "Content-Type: application/json" ^
  -D "!HEADERS_TMP!" ^
  -d "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"initialize\",\"params\":{\"protocolVersion\":\"2025-03-26\",\"capabilities\":{},\"clientInfo\":{\"name\":\"batch-test\",\"version\":\"1.0\"}}}" ^
  > nul 2>&1

set "SESSION_ID="
for /f "tokens=2 delims= " %%A in ('findstr /i "Mcp-Session-Id:" "!HEADERS_TMP!" 2^>nul') do set "SESSION_ID=%%A"
if defined SESSION_ID set "SESSION_ID=!SESSION_ID: =!"

if not defined SESSION_ID (
    echo [FAIL] Could not obtain session ID. Check URL and credentials.
    if exist "!HEADERS_TMP!" type "!HEADERS_TMP!"
    goto :end
)

echo Session: !SESSION_ID!
echo.

:: ------------------------------------------------------------
:: No argument: list abilities (raw JSON)
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
:: Execute ability
:: ------------------------------------------------------------
echo Executing: !ABILITY!

:: Build params JSON string
set "PJSON="
for /l %%I in (1,1,!_paramcount!) do (
    set "_k=!_pk%%I!"
    set "_v=!_pv%%I!"
    :: Detect integer
    set "_isnum=1"
    for /f "delims=0123456789" %%C in ("!_v!") do set "_isnum=0"
    if "!_v!"=="" set "_isnum=0"
    if "!_isnum!"=="1" (
        if defined PJSON (
            set "PJSON=!PJSON!,\q!_k!\q:!_v!"
        ) else (
            set "PJSON=\q!_k!\q:!_v!"
        )
    ) else (
        if defined PJSON (
            set "PJSON=!PJSON!,\q!_k!\q:\q!_v!\q"
        ) else (
            set "PJSON=\q!_k!\q:\q!_v!\q"
        )
    )
)

:: Build full body with \q placeholders, then write to file replacing \q with "
set "FULLBODY={\qjsonrpc\q:\q2.0\q,\qid\q:2,\qmethod\q:\qtools/call\q,\qparams\q:{\qname\q:\qmcp-adapter-execute-ability\q,\qarguments\q:{\qability_name\q:\q!ABILITY!\q,\qparameters\q:{!PJSON!}}}}"

:: Replace \q with " by writing via cmd set substitution
set "FULLBODY=!FULLBODY:\q="!"

:: Write to file using a temp script to preserve quotes
>"!BODY_TMP!.cmd" echo @echo off
>>"!BODY_TMP!.cmd" echo ^<nul set /p="!FULLBODY!" ^>"!BODY_TMP!"
call "!BODY_TMP!.cmd"
del "!BODY_TMP!.cmd"

if defined PJSON echo Params:    {!PJSON:\q="!}
echo.

curl -k -s -u "!USERNAME!:!PASSWORD!" -X POST "!ENDPOINT!" ^
  -H "Content-Type: application/json" ^
  -H "Mcp-Session-Id: !SESSION_ID!" ^
  -d @"!BODY_TMP!" > "!RESP_TMP!"
if exist "!BODY_TMP!" del "!BODY_TMP!"

type "!RESP_TMP!"
echo.

if exist "!RESP_TMP!" del "!RESP_TMP!"

:end
if exist "!HEADERS_TMP!" del "!HEADERS_TMP!"
endlocal
