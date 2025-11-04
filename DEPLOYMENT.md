# RASAYA Deployment Guide

## Architecture Overview

- **Backend Laravel** (`backend-rasaya`): API server untuk semua role (Admin, Guru, Siswa)
- **Frontend Web Laravel** (`backend-rasaya/resources/views`): Dashboard untuk Admin & Guru
- **Flutter Mobile/Web** (`mobile-rasaya`): Aplikasi untuk Siswa (Mobile & Web)

## Siswa Access Flow

**Siswa** menggunakan **Flutter app** (mobile atau web), tidak menggunakan Laravel Blade views.

Ketika siswa login via Laravel web (`/siswa`):
- Laravel akan **redirect otomatis** ke Flutter Web app
- Flutter Web akan menggunakan **Laravel API** untuk semua data
- Token authentication tetap via Laravel Sanctum API

## Setup Development

### 1. Laravel Backend (Port 8000)

```bash
cd backend-rasaya
php artisan serve
```

URL: `http://127.0.0.1:8000`

### 2. Flutter Web (Port 8080)

```bash
cd mobile-rasaya

# Test di Chrome dulu
flutter run -d chrome

# Atau build production
flutter build web

# Serve hasil build (untuk test lokal)
cd build/web
python -m http.server 8080
```

URL: `http://localhost:8080`

### 3. Update .env

Di `backend-rasaya/.env`, set URL Flutter Web:

```env
# Development (lokal)
FLUTTER_WEB_URL=http://localhost:8080

# Production (setelah deploy)
FLUTTER_WEB_URL=https://siswa.rasaya.app
```

## Deployment Production

### Backend Laravel

Deploy ke VPS/hosting dengan:
- PHP 8.2+
- MySQL
- Nginx/Apache

### Flutter Web

**Option 1: Firebase Hosting (Recommended)**

```bash
cd mobile-rasaya

# Build production
flutter build web --release

# Install Firebase CLI
npm install -g firebase-tools

# Login & setup
firebase login
firebase init hosting

# Deploy
firebase deploy
```

**Option 2: Vercel**

```bash
cd mobile-rasaya
flutter build web --release

# Install Vercel CLI
npm i -g vercel

# Deploy
vercel build/web
```

**Option 3: Netlify**

```bash
cd mobile-rasaya
flutter build web --release

# Drag & drop folder `build/web` ke Netlify dashboard
# Atau gunakan Netlify CLI
```

### Update Production .env

Setelah deploy Flutter Web, update `backend-rasaya/.env`:

```env
FLUTTER_WEB_URL=https://your-flutter-web-url.com
```

## Auto-Deploy with GitHub Actions

Create `.github/workflows/deploy-flutter-web.yml`:

```yaml
name: Deploy Flutter Web

on:
  push:
    branches: [ main ]
    paths:
      - 'mobile-rasaya/**'

jobs:
  build-and-deploy:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - uses: subosito/flutter-action@v2
      with:
        flutter-version: '3.24.0'
    
    - name: Get dependencies
      run: |
        cd mobile-rasaya
        flutter pub get
    
    - name: Build web
      run: |
        cd mobile-rasaya
        flutter build web --release
    
    - name: Deploy to Firebase
      uses: FirebaseExtended/action-hosting-deploy@v0
      with:
        repoToken: '${{ secrets.GITHUB_TOKEN }}'
        firebaseServiceAccount: '${{ secrets.FIREBASE_SERVICE_ACCOUNT }}'
        channelId: live
        projectId: your-firebase-project-id
        entryPoint: mobile-rasaya
```

## Testing

### Test Siswa Login Flow

1. Start Laravel: `php artisan serve` (port 8000)
2. Start Flutter Web: `flutter run -d chrome` (port 8080)
3. Login sebagai siswa di `http://127.0.0.1:8000/login`
4. Seharusnya auto-redirect ke `http://localhost:8080`

### Test API Integration

Flutter Web menggunakan API Laravel:

```dart
// mobile-rasaya/lib/api/api_client.dart
final baseUrl = 'http://127.0.0.1:8000/api'; // Development
// final baseUrl = 'https://api.rasaya.app/api'; // Production
```

Update `baseUrl` sesuai environment.

## ML Service (Python) & Taxonomy

Folder `ml-rasaya` berisi service Python (Flask) untuk analisis teks.

- Konfigurasi taxonomy masalah: `ml-rasaya/taxonomy.json`
- Service otomatis membaca file ini saat start untuk klasifikasi Topik/Subtopik
- Jalankan lokal:

```bash
cd ml-rasaya
python app.py  # runs on http://127.0.0.1:5001
```

Deploy gratis (opsi):

- Render.com (Free tier) atau Railway.app
- Start command:

```bash
uvicorn app:app --host 0.0.0.0 --port $PORT
```

Environment variables yang didukung:

```env
ML_API_KEY=optional-secret-key
ML_FEEDBACK_FILE=feedback_weights.json
ML_TAXONOMY_FILE=taxonomy.json
ML_ENABLE_BERT=false               # set true untuk mengaktifkan IndoBERT embeddings
ML_BERT_MODEL=indobenchmark/indobert-base-p1
ML_LEXICON_DIR=lexicons            # folder lokal untuk inset.tsv, barasa.csv, sentimen-bahasa.json
```

### Indonesian Lexicons (optional)

Letakkan file berikut (jika tersedia) di folder `ml-rasaya/lexicons/`:

- `inset.tsv` (format: `word\tscore`) dari InSet
- `barasa.csv` (format: `word,score`) dari Barasa
- `sentimen-bahasa.json` (format: `{word: score}`) dari onpilot

Service akan otomatis memuat dan menggabungkan ketiganya (dengan normalisasi skor). Jika tidak ada, service akan fallback ke kamus internal ringan + VADER.

## Troubleshooting

### Siswa tidak redirect?

Check:
1. `.env` sudah ada `FLUTTER_WEB_URL`
2. Cache Laravel: `php artisan config:clear`
3. Route siswa: `php artisan route:list | grep siswa`

### Flutter Web tidak connect ke API?

Check:
1. CORS settings di Laravel (`config/cors.php`)
2. API base URL di Flutter (`lib/api/api_client.dart`)
3. Token authentication (Sanctum)

### Build Flutter Web error?

Check:
1. Package web support: `flutter pub get`
2. Flutter version: `flutter doctor -v`
3. Clear cache: `flutter clean && flutter pub get`

## Monitoring

- **Admin Dashboard**: Monitor login siswa di `/admin/dashboard/login-history`
- **Activity Logs**: Track aktivitas siswa di `/admin/dashboard/user-activity/{userId}`
- **Audit Trail**: Cek perubahan data di `/admin/dashboard/audit-logs`

## Notes

- Siswa **TIDAK** punya Blade views di Laravel
- Siswa **HANYA** menggunakan Flutter (mobile/web)
- Admin & Guru tetap pakai Laravel Blade views
- Semua role menggunakan **Laravel API yang sama** untuk data
