# Script untuk mentranslasi teks Inggris ke Indonesia di file Blade
# Usage: .\translate-views.ps1

$translations = @{
    # Common UI Elements
    'Reset Password' = 'Atur Ulang Kata Sandi'
    '>Reset Password<' = '>Atur Ulang Kata Sandi<'
    'Login History' = 'Riwayat Masuk'
    'History Mood' = 'Riwayat Mood'
    'History Refleksi' = 'Riwayat Refleksi'
    'Audit Logs' = 'Log Audit'
    'Backup & Restore' = 'Cadangkan & Pulihkan'
    'Import Roster' = 'Impor Roster'
    '>Dashboard<' = '>Dasbor<'
    '>Home<' = '>Beranda<'
    
    # Buttons & Actions
    '>Edit<' = '>Edit<'
    '>Delete<' = '>Hapus<'
    '>Update<' = '>Perbarui<'
    '>Detail<' = '>Detail<'
    '>Save<' = '>Simpan<'
    '>Cancel<' = '>Batal<'
    '>Close<' = '>Tutup<'
    '>Filter<' = '>Filter<'
    '>Search<' = '>Cari<'
    '>Reset<' = '>Atur Ulang<'
    '>Submit<' = '>Kirim<'
    '>Create<' = '>Buat<'
    '>Generate<' = '>Buat<'
    'Refresh' = 'Muat Ulang'
    
    # Form & Table Headers
    '>User<' = '>Pengguna<'
    'Manajemen User' = 'Manajemen Pengguna'
    'Edit User' = 'Edit Pengguna'
    'Detail User' = 'Detail Pengguna'
    'Tambah User' = 'Tambah Pengguna'
    '>Password<' = '>Kata Sandi<'
    'Password' = 'Kata Sandi'
    'Identifier' = 'Identifier'
    
    # Status Labels
    '>Completed<' = '>Selesai<'
    '>Canceled<' = '>Dibatalkan<'
    '>No Show<' = '>Tidak Hadir<'
    '>Booked<' = '>Terpesan<'
    '"available"' = '"tersedia"'
    '"booked"' = '"terpesan"'
    
    # Common Words
    '>Review<' = '>Tinjauan<'
    'Monitoring' = 'Pemantauan'
    'Mood Tracker' = 'Pelacak Suasana Hati'
    'Self-Report' = 'Laporan Diri'
    'History Data' = 'Riwayat Data'
    'Reminder' = 'Pengingat'
    'System' = 'Sistem'
    'low' = 'rendah'
    'medium' = 'sedang'
    'high' = 'tinggi'
    'Severity' = 'Tingkat Keparahan'
    
    # Pagination & Navigation
    '>Prev<' = '>Sebelumnya<'
    '>Next<' = '>Berikutnya<'
    'Loading...' = 'Memuat...'
    'Memuat…' = 'Memuat...'
}

$viewsPath = "resources\views"
$files = Get-ChildItem -Path $viewsPath -Filter *.blade.php -Recurse

$totalFiles = $files.Count
$processedFiles = 0
$modifiedFiles = 0

Write-Host "Memulai translasi $totalFiles file blade..." -ForegroundColor Cyan

foreach ($file in $files) {
    $processedFiles++
    $content = Get-Content -Path $file.FullName -Raw -Encoding UTF8
    $originalContent = $content
    
    foreach ($english in $translations.Keys) {
        $indonesian = $translations[$english]
        $content = $content -replace [regex]::Escape($english), $indonesian
    }
    
    if ($content -ne $originalContent) {
        Set-Content -Path $file.FullName -Value $content -Encoding UTF8 -NoNewline
        $modifiedFiles++
        Write-Host "[$processedFiles/$totalFiles] ✓ $($file.Name)" -ForegroundColor Green
    } else {
        Write-Host "[$processedFiles/$totalFiles] - $($file.Name)" -ForegroundColor Gray
    }
}

Write-Host "" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Selesai!" -ForegroundColor Green
Write-Host "Total file diproses: $totalFiles" -ForegroundColor White
Write-Host "File dimodifikasi: $modifiedFiles" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan
