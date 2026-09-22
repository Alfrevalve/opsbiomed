Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$projectRoot = 'C:\laragon\www\ops-biomed-staging-local'
$backupRoot = 'C:\laragon\www\ops-biomed-staging-local-backups'
$databaseName = 'ops_biomed_staging_local'
$mysql = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$mysqldump = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe'

if ([IO.Path]::GetFullPath($projectRoot) -ne $projectRoot) {
    throw 'Unexpected staging project path.'
}

if (-not (Test-Path -LiteralPath $projectRoot -PathType Container)) {
    throw 'The isolated staging project does not exist.'
}

$resolvedBackupRoot = [IO.Path]::GetFullPath($backupRoot).TrimEnd('\')
$projectPrefix = [IO.Path]::GetFullPath($projectRoot).TrimEnd('\') + '\'
if ($resolvedBackupRoot.StartsWith($projectPrefix, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Backup directory must remain outside the application tree.'
}

$envLines = [IO.File]::ReadAllLines((Join-Path $projectRoot '.env'))
if ($envLines -notcontains "APP_ENV=staging" -or $envLines -notcontains "APP_DEBUG=false" -or $envLines -notcontains "DB_DATABASE=$databaseName") {
    throw 'Staging environment does not match the approved local settings.'
}

$privatePath = Join-Path $projectRoot 'storage\app\private'
$privateFiles = @(Get-ChildItem -LiteralPath $privatePath -File -Recurse)
if ($privateFiles.Count -ne 2 -or @($privateFiles.Name | Where-Object { $_ -notin @('.gitignore', 'qa-fase4-sentinel.txt') }).Count -ne 0) {
    throw 'Private storage must contain only the synthetic Fase 4 sentinel.'
}

$connectionArgs = @('--protocol=tcp', '--host=127.0.0.1', '--port=3306', '--user=root', '--batch', '--skip-column-names')
$identity = & $mysql @connectionArgs "--database=$databaseName" '--execute=SELECT @@hostname'
if ($LASTEXITCODE -ne 0 -or $identity -ne 'President-lapto') {
    throw 'MySQL identity did not match the local staging host and exact database.'
}

$migrationCount = [int](& $mysql @connectionArgs "--database=$databaseName" '--execute=SELECT COUNT(*) FROM migrations')
if ($LASTEXITCODE -ne 0 -or $migrationCount -lt 1) {
    throw 'Staging schema is not migrated.'
}

$null = New-Item -ItemType Directory -Path $resolvedBackupRoot -Force
$backupId = (Get-Date).ToUniversalTime().ToString('yyyyMMddTHHmmssfffZ')
$backupDirectory = Join-Path $resolvedBackupRoot $backupId
if (Test-Path -LiteralPath $backupDirectory) {
    throw 'Backup destination already exists.'
}

$null = New-Item -ItemType Directory -Path $backupDirectory
$databaseDump = Join-Path $backupDirectory 'database.sql'
$privateArchive = Join-Path $backupDirectory 'private-storage.zip'
$watch = [Diagnostics.Stopwatch]::StartNew()

& $mysqldump --protocol=tcp --host=127.0.0.1 --port=3306 --user=root --single-transaction --routines --triggers --no-tablespaces --result-file=$databaseDump $databaseName
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $databaseDump -PathType Leaf) -or (Get-Item -LiteralPath $databaseDump).Length -lt 1) {
    throw 'MySQL dump failed or produced an empty file.'
}

Compress-Archive -Path (Join-Path $privatePath '*') -DestinationPath $privateArchive -CompressionLevel Optimal
if (-not (Test-Path -LiteralPath $privateArchive -PathType Leaf) -or (Get-Item -LiteralPath $privateArchive).Length -lt 1) {
    throw 'Private storage archive is missing or empty.'
}

$databaseHash = (Get-FileHash -LiteralPath $databaseDump -Algorithm SHA256).Hash.ToLowerInvariant()
$privateHash = (Get-FileHash -LiteralPath $privateArchive -Algorithm SHA256).Hash.ToLowerInvariant()
@("$databaseHash  database.sql", "$privateHash  private-storage.zip") | Set-Content -LiteralPath (Join-Path $backupDirectory 'checksums.sha256') -Encoding ascii
$watch.Stop()

$manifest = [ordered]@{
    backup_id = $backupId
    target = 'local-staging'
    database = $databaseName
    database_host = '127.0.0.1:3306 / President-lapto'
    release_id = '10a43743'
    schema_migrations = $migrationCount
    created_at_utc = (Get-Date).ToUniversalTime().ToString('o')
    duration_seconds = [Math]::Round($watch.Elapsed.TotalSeconds, 3)
    database_bytes = (Get-Item -LiteralPath $databaseDump).Length
    private_storage_bytes = (Get-Item -LiteralPath $privateArchive).Length
    database_sha256 = $databaseHash
    private_storage_sha256 = $privateHash
    contains_env = $false
    synthetic_private_files = @('qa-fase4-sentinel.txt')
    restore_verified = $false
}
$manifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $backupDirectory 'manifest.json') -Encoding utf8

$actualNames = @(Get-ChildItem -LiteralPath $backupDirectory -File | ForEach-Object Name | Sort-Object)
$expectedNames = @('checksums.sha256', 'database.sql', 'manifest.json', 'private-storage.zip')
if (Compare-Object $expectedNames $actualNames) {
    throw 'Backup contents differ from the expected synthetic-only artifact set.'
}

Write-Output "Backup created: $backupDirectory"
Write-Output "Database bytes: $($manifest.database_bytes); private storage bytes: $($manifest.private_storage_bytes); duration: $($manifest.duration_seconds)s"
Write-Output "SHA-256 database: $databaseHash"
Write-Output "SHA-256 private storage: $privateHash"
Write-Output 'Environment file included: false'
