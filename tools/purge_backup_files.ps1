param(
    [string]$BackupDirectory = "E:\HM DATA\cloud-backups"
)

$ErrorActionPreference = "Stop"
$confirmation = Read-Host 'Type DELETE ALL HMS BACKUPS to permanently delete encrypted backup files'
if ($confirmation -cne 'DELETE ALL HMS BACKUPS') {
    throw 'Deletion cancelled.'
}

if (-not (Test-Path -LiteralPath $BackupDirectory -PathType Container)) {
    Write-Host "Backup directory does not exist: $BackupDirectory"
    exit 0
}

Get-ChildItem -LiteralPath $BackupDirectory -File |
    Where-Object { $_.Name -like 'hms-*.mchms' -or $_.Name -like 'hms-*.mchms.sha256' } |
    Remove-Item -Force

Write-Host "Encrypted backup files deleted from $BackupDirectory"
