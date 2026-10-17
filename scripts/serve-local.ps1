param([ValidateRange(1024, 65535)][int]$Port = 8000)

$projectRoot = Split-Path -Parent $PSScriptRoot
$router = Join-Path $projectRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
# Project-local PHP settings; no php.ini or credentials are changed.
Push-Location (Join-Path $projectRoot 'public')
try {
    php -d upload_max_filesize=5M -d post_max_size=8M -S "127.0.0.1:$Port" -t . $router
} finally {
    Pop-Location
}
