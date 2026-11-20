# Starts everything Kusina needs for local development:
#   MariaDB (port 3307), the web app (http://127.0.0.1:8000) and Reverb WebSockets (port 8080).
# Usage:  powershell -ExecutionPolicy Bypass -File .\start-dev.ps1
$ErrorActionPreference = 'Stop'
$env:Path = 'C:\php84;' + $env:Path
Set-Location $PSScriptRoot

function Test-Port($port) { [bool](Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue) }

if (-not (Test-Port 3307)) {
    Start-Process 'C:\mariadb118\bin\mysqld.exe' -ArgumentList '--defaults-file=C:\mariadb118\my.ini', '--console' -WindowStyle Hidden
    Start-Sleep 6
}
if (-not (Test-Port 8080)) { Start-Process php -ArgumentList 'artisan', 'reverb:start', '--host=127.0.0.1', '--port=8080' -WindowStyle Hidden }
if (-not (Test-Port 8000)) { Start-Process php -ArgumentList 'artisan', 'serve', '--host=127.0.0.1', '--port=8000' -WindowStyle Hidden }

Write-Host 'Kusina is running:'
Write-Host '  Site   http://127.0.0.1:8000'
Write-Host '  Admin  http://127.0.0.1:8000/login   (credentials: ~/.kusina-admin.txt)'
