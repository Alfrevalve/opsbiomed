param(
    [Parameter(Mandatory = $true)]
    [string] $BackupDirectory,
    [switch] $VerifyOnly
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$backupRoot = [IO.Path]::GetFullPath('C:\laragon\www\ops-biomed-staging-local-backups').TrimEnd('\')
$restoreDatabase = 'ops_biomed_restore_test'
$mysql = 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe'
$restoreStorage = 'C:\laragon\www\ops-biomed-staging-local-restore-storage'
$resolvedBackup = [IO.Path]::GetFullPath($BackupDirectory).TrimEnd('\')

if (-not $resolvedBackup.StartsWith("$backupRoot\", [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Backup must be inside the approved local backup directory.'
}

$manifestPath = Join-Path $resolvedBackup 'manifest.json'
$manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
if ($manifest.target -ne 'local-staging' -or $manifest.database -ne 'ops_biomed_staging_local' -or $manifest.contains_env -ne $false) {
    throw 'Backup manifest is not an approved synthetic local staging backup.'
}

$databaseDump = Join-Path $resolvedBackup 'database.sql'
$privateArchive = Join-Path $resolvedBackup 'private-storage.zip'
if (-not (Test-Path -LiteralPath $databaseDump -PathType Leaf) -or -not (Test-Path -LiteralPath $privateArchive -PathType Leaf)) {
    throw 'Backup payload is incomplete.'
}

$expectedFiles = Get-Content -LiteralPath (Join-Path $resolvedBackup 'checksums.sha256')
foreach ($entry in $expectedFiles) {
    if ($entry -notmatch '^([0-9a-f]{64})\s+(.+)$') {
        throw 'Checksum manifest contains an invalid entry.'
    }

    $actual = (Get-FileHash -LiteralPath (Join-Path $resolvedBackup $Matches[2]) -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actual -ne $Matches[1]) {
        throw "Checksum verification failed for $($Matches[2])."
    }
}

$connectionArgs = @('--protocol=tcp', '--host=127.0.0.1', '--port=3306', '--user=root', '--batch', '--skip-column-names')
$identity = & $mysql @connectionArgs "--database=$restoreDatabase" '--execute=SELECT @@hostname'
if ($LASTEXITCODE -ne 0 -or $identity -ne 'President-lapto') {
    throw 'MySQL identity did not match the approved local restore database.'
}

$tableCount = [int](& $mysql @connectionArgs "--database=$restoreDatabase" "--execute=SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$restoreDatabase'")
if ($LASTEXITCODE -ne 0 -or ($VerifyOnly -and $tableCount -eq 0) -or (-not $VerifyOnly -and $tableCount -ne 0)) {
    throw 'Restore target is not empty; refusing to overwrite or drop any data.'
}

if ((Test-Path -LiteralPath $restoreStorage) -and -not $VerifyOnly) {
    throw 'Restore storage destination already exists; refusing to overwrite.'
}

$watch = [Diagnostics.Stopwatch]::StartNew()
if (-not $VerifyOnly) {
    $sqlPath = $databaseDump.Replace('\', '/')
    & $mysql @connectionArgs "--database=$restoreDatabase" "--execute=source $sqlPath"
    if ($LASTEXITCODE -ne 0) {
        throw 'MySQL restore failed.'
    }

    $null = New-Item -ItemType Directory -Path $restoreStorage
    Expand-Archive -LiteralPath $privateArchive -DestinationPath $restoreStorage
}
$watch.Stop()

$counts = @(& $mysql @connectionArgs "--database=$restoreDatabase" '--execute=SELECT COUNT(*) FROM migrations; SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM products; SELECT COUNT(*) FROM inventory_lots; SELECT COUNT(*) FROM reservations; SELECT COUNT(*) FROM document_evidences; SELECT COUNT(*) FROM failures; SELECT COUNT(*) FROM case_returns; SELECT COUNT(*) FROM cache')
$restoredIdentity = $counts -join '|'
if ($LASTEXITCODE -ne 0 -or $restoredIdentity -ne '36|8|9|14|3|1|1|1|1') {
    throw "Restored data verification failed. Counts: $restoredIdentity"
}

$sentinelValue = & $mysql @connectionArgs "--database=$restoreDatabase" '--execute=SELECT value FROM cache'
if ($LASTEXITCODE -ne 0 -or $sentinelValue -ne 'FASE4-SYNTHETIC-DB-ROW-20260920') {
    throw 'Database sentinel was not restored.'
}

$restoredSentinel = Join-Path $restoreStorage 'qa-fase4-sentinel.txt'
if (-not (Test-Path -LiteralPath $restoredSentinel -PathType Leaf)) {
    throw 'Private storage sentinel was not restored.'
}

$restoredFileHash = (Get-FileHash -LiteralPath $restoredSentinel -Algorithm SHA256).Hash.ToLowerInvariant()
if ($restoredFileHash -ne (Get-FileHash -LiteralPath 'C:\laragon\www\ops-biomed-staging-local\storage\app\private\qa-fase4-sentinel.txt' -Algorithm SHA256).Hash.ToLowerInvariant()) {
    throw 'Restored private storage checksum does not match the source sentinel.'
}

$manifest.restore_verified = $true
$manifest | Add-Member -NotePropertyName restore_database -NotePropertyValue $restoreDatabase -Force
$manifest | Add-Member -NotePropertyName restore_verified_at_utc -NotePropertyValue ((Get-Date).ToUniversalTime().ToString('o')) -Force
if ($VerifyOnly) {
    $manifest.PSObject.Properties.Remove('restore_duration_seconds')
    $manifest | Add-Member -NotePropertyName restore_verification_seconds -NotePropertyValue ([Math]::Round($watch.Elapsed.TotalSeconds, 3)) -Force
} else {
    $manifest | Add-Member -NotePropertyName restore_duration_seconds -NotePropertyValue ([Math]::Round($watch.Elapsed.TotalSeconds, 3)) -Force
}
$manifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $manifestPath -Encoding utf8

Write-Output "Restore target: $restoreDatabase (President-lapto / 127.0.0.1:3306)"
Write-Output "Verified counts: migrations/users/products/lots/reservations/documents/failures/returns/cache-sentinel = $restoredIdentity"
Write-Output "Private storage sentinel SHA-256: $restoredFileHash"
if ($VerifyOnly) {
    Write-Output "Verification duration: $($watch.Elapsed.TotalSeconds.ToString('F3'))s"
} else {
    Write-Output "Restore duration: $($watch.Elapsed.TotalSeconds.ToString('F3'))s"
}
