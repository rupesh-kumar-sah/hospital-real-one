param(
    [string]$BackupDirectory = "E:\HM DATA\cloud-backups",
    [int]$IntervalHours = 6,
    [string]$PhpPath = "php"
)

$ErrorActionPreference = "Stop"
$projectRoot = Split-Path -Parent $PSScriptRoot

while ($true) {
    Push-Location $projectRoot
    try {
        $env:BACKUP_DIRECTORY = $BackupDirectory
        & $PhpPath "tools\encrypted_backup.php" backup
        if ($LASTEXITCODE -ne 0) {
            throw "Encrypted backup failed with exit code $LASTEXITCODE."
        }
    } finally {
        Pop-Location
    }

    Start-Sleep -Seconds ($IntervalHours * 3600)
}
