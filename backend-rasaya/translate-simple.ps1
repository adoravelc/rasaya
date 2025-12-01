# Script translasi views - versi sederhana
$translations = @{
    'Reset Password' = 'Atur Ulang Kata Sandi'
    'Login History' = 'Riwayat Masuk'
    'History Mood' = 'Riwayat Mood'
    'Audit Logs' = 'Log Audit'
    'Import Roster' = 'Impor Roster'
    'Backup & Restore' = 'Cadangkan & Pulihkan'
    'Mood Tracker' = 'Pelacak Suasana Hati'
    'Self-Report' = 'Laporan Diri'
    'History Data' = 'Riwayat Data'
}

$viewsPath = "resources\views"
$files = Get-ChildItem -Path $viewsPath -Filter *.blade.php -Recurse
$modifiedFiles = 0

foreach ($file in $files) {
    $content = Get-Content -Path $file.FullName -Raw -Encoding UTF8
    $originalContent = $content
    
    foreach ($english in $translations.Keys) {
        $indonesian = $translations[$english]
        $content = $content.Replace($english, $indonesian)
    }
    
    if ($content -ne $originalContent) {
        [System.IO.File]::WriteAllText($file.FullName, $content, [System.Text.Encoding]::UTF8)
        $modifiedFiles++
        Write-Host "Modified: $($file.Name)" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "Done! Modified $modifiedFiles files" -ForegroundColor Cyan
