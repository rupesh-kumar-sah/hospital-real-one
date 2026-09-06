param(
    [string]$ExportDirectory = "E:\HM DATA",
    [int]$IntervalSeconds = 30,
    [string]$PhpPath = "php"
)

$ErrorActionPreference = "Stop"
$databasePath = "E:\HM DATA\hms.db"
$projectRoot = Split-Path -Parent $PSScriptRoot
$lastSignature = ""

while ($true) {
    if (Test-Path -LiteralPath $databasePath -PathType Leaf) {
        $database = Get-Item -LiteralPath $databasePath
        $walPath = "$databasePath-wal"
        $wal = if (Test-Path -LiteralPath $walPath) { Get-Item -LiteralPath $walPath } else { $null }
        $signature = "{0}:{1}:{2}:{3}" -f $database.Length, $database.LastWriteTimeUtc.Ticks, `
            $(if ($wal) { $wal.Length } else { 0 }), $(if ($wal) { $wal.LastWriteTimeUtc.Ticks } else { 0 })

        if ($signature -ne $lastSignature) {
            $env:EXPORT_DIRECTORY = $ExportDirectory
            Push-Location $projectRoot
            try {
                & $PhpPath "tools\auto_export.php"
                if ($LASTEXITCODE -ne 0) {
                    throw "Automatic export failed with exit code $LASTEXITCODE."
                }
                $lastSignature = $signature
            } finally {
                Pop-Location
            }
        }
    }

    Start-Sleep -Seconds $IntervalSeconds
}
