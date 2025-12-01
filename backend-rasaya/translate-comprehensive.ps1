# Script translasi comprehensive
$translations = @{
    # Buttons  
    '>Edit<' = '>Edit<'
    '>Update<' = '>Perbarui<'
    '>Delete<' = '>Hapus<'
    '>Cancel<' = '>Batal<'
    '>Close<' = '>Tutup<'
    '>Save<' = '>Simpan<'
    '>Submit<' = '>Kirim<'
    '>Reset<' = '>Atur Ulang<'
    '>Search<' = '>Cari<'
    '>Filter<' = '>Filter<'
    '>Create<' = '>Buat<'
    '>Generate<' = '>Buat<'
    'Refresh' = 'Muat Ulang'
    
    # Common Terms
    'Manajemen User' = 'Manajemen Pengguna'
    'Edit User' = 'Edit Pengguna'
    'Detail User' = 'Detail Pengguna'
    'Tambah User' = 'Tambah Pengguna'
    '>User<' = '>Pengguna<'
    'Password' = 'Kata Sandi'
    'Severity' = 'Tingkat Keparahan'
    'Monitoring' = 'Pemantauan'
    'Reminder' = 'Pengingat'
    'System' = 'Sistem'
    '>Review<' = '>Tinjauan<'
    
    # Status
    '>Completed<' = '>Selesai<'
    '>Canceled<' = '>Dibatalkan<'
    '>No Show<' = '>Tidak Hadir<'
    '>Booked<' = '>Terpesan<'
    'badge bg-success">Booked' = 'badge bg-success">Terpesan'
    'badge bg-primary">Completed' = 'badge bg-primary">Selesai'
    'badge bg-danger">Canceled' = 'badge bg-danger">Dibatalkan'
    'badge bg-secondary">No Show' = 'badge bg-secondary">Tidak Hadir'
    
    # Low/Medium/High
    'value="low">low' = 'value="low">rendah'
    'value="medium">medium' = 'value="medium">sedang'
    'value="high">high' = 'value="high">tinggi'
    
    # Pagination
    '>Prev<' = '>Sebelumnya<'
    '>Next<' = '>Berikutnya<'
    'Loading...' = 'Memuat...'
}

$viewsPath = "resources\views"
$files = Get-ChildItem -Path $viewsPath -Filter *.blade.php -Recurse
$modifiedFiles = 0

Write-Host "Processing $($files.Count) files..." -ForegroundColor Cyan

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
        Write-Host "✓ $($file.Name)" -ForegroundColor Green
    }
}

Write-Host ""
Write-Host "Done! Modified $modifiedFiles files" -ForegroundColor Yellow
