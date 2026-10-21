param([ValidateRange(1024, 65535)][int]$Port = 8000, [switch]$Check, [switch]$WorkerOnly)

$projectRoot = Split-Path -Parent $PSScriptRoot
$router = Join-Path $projectRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
$publicPath = Join-Path $projectRoot 'public'
$phpPath = (Get-Command php -ErrorAction Stop).Source
$queueArguments = @('artisan', 'queue:work', 'referrals', '--queue=municipality-referrals', '--tries=3', '--timeout=45')
if ($Check) {
    Write-Output 'Server: php -d upload_max_filesize=5M -d post_max_size=8M -S 127.0.0.1:PORT -t public LARAVEL_ROUTER'
    Write-Output ('Worker: php ' + ($queueArguments -join ' '))
    Write-Output 'Legacy referrals queue remains paused. Only newly confirmed municipality referrals are processed.'
    exit 0
}
$logDirectory = Join-Path $projectRoot 'storage/app/direct-referrals'
New-Item -ItemType Directory -Path $logDirectory -Force | Out-Null
$workerProcess = $null
$serverProcess = $null
try {
    if (!$WorkerOnly) {
        $serverProcess = Start-Process -FilePath $phpPath -ArgumentList @('-d','upload_max_filesize=5M','-d','post_max_size=8M','-S',"127.0.0.1:$Port",'-t',('"' + $publicPath + '"'),('"' + $router + '"')) -WorkingDirectory $publicPath -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $logDirectory 'server-output.log') -RedirectStandardError (Join-Path $logDirectory 'server-errors.log')
    }
    Write-Output 'AquaMap municipality email worker is supervised. Ctrl+C stops the processes started by this script.'
    while ($WorkerOnly -or !$serverProcess.HasExited) {
        if ($null -eq $workerProcess -or $workerProcess.HasExited) {
            $workerProcess = Start-Process -FilePath $phpPath -ArgumentList $queueArguments -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $logDirectory 'worker-output.log') -RedirectStandardError (Join-Path $logDirectory 'worker-errors.log')
        }
        Start-Sleep -Seconds 2
        if ($null -ne $serverProcess) { $serverProcess.Refresh() }
        $workerProcess.Refresh()
    }
    if ($null -ne $serverProcess -and $serverProcess.ExitCode -ne 0) { throw 'The local server stopped. Check the private server log or whether this port is already in use.' }
} finally {
    foreach ($ownedProcess in @($workerProcess, $serverProcess)) {
        if ($null -ne $ownedProcess -and !$ownedProcess.HasExited) { $ownedProcess.Kill() }
    }
}
