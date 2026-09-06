param(
    [string]$SyncDirectory = "E:\HM DATA\cloud-backups",
    [int]$RetentionDays = 30,
    [string]$PhpPath = "php"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path -LiteralPath $SyncDirectory -PathType Container)) {
    New-Item -ItemType Directory -Path $SyncDirectory -Force | Out-Null
}

$env:BACKUP_DIRECTORY = $SyncDirectory
& $PhpPath (Join-Path $PSScriptRoot "encrypted_backup.php") backup
if ($LASTEXITCODE -ne 0) {
    throw "Encrypted backup failed with exit code $LASTEXITCODE."
}

$cutoff = (Get-Date).ToUniversalTime().AddDays(-$RetentionDays)
Get-ChildItem -LiteralPath $SyncDirectory -File -Filter "hms-*.mchms" |
    Where-Object { $_.LastWriteTimeUtc -lt $cutoff } |
    Remove-Item -Force
Get-ChildItem -LiteralPath $SyncDirectory -File -Filter "hms-*.mchms.sha256" |
    Where-Object { $_.LastWriteTimeUtc -lt $cutoff } |
    Remove-Item -Force

Write-Host "Backup folder ready for Google Drive or OneDrive synchronization: $SyncDirectory"
