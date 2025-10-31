# RASAYA Mobile/Web App (Siswa)

Flutter application for siswa to access RASAYA system via mobile or web browser.

## Features

- 📱 **Multi-platform**: Mobile (Android/iOS) dan Web
- 🔐 **Authentication**: Login dengan Laravel Sanctum API
- 📝 **Refleksi Harian**: Input refleksi dan mood tracking
- 🤝 **Laporan Teman**: Laporkan masalah teman ke guru BK
- 📅 **Booking Konseling**: Jadwalkan sesi konseling dengan guru BK
- 📊 **Dashboard**: Lihat statistik mood dan aktivitas

## Development

### Prerequisites

- Flutter SDK 3.24.0+
- Dart 3.0+
- Laravel Backend running (default: `http://127.0.0.1:8000`)

### Run Mobile (Android/iOS)

```bash
# Get dependencies
flutter pub get

# Run on connected device/emulator
flutter run

# Or specify device
flutter run -d <device_id>
```

### Run Web (Chrome/Edge)

```bash
# Run in Chrome
flutter run -d chrome

# Run in Edge
flutter run -d edge

# Run with specific port
flutter run -d chrome --web-port=8080
```

### Build Production

**Mobile (APK):**
```bash
flutter build apk --release
# Output: build/app/outputs/flutter-apk/app-release.apk
```

**Mobile (App Bundle for Play Store):**
```bash
flutter build appbundle --release
# Output: build/app/outputs/bundle/release/app-release.aab
```

**Web:**
```bash
flutter build web --release
# Output: build/web/
```

## Deployment

### Web Deployment

**Firebase Hosting:**
```bash
# Build
flutter build web --release

# Deploy
firebase deploy
```

**Vercel:**
```bash
flutter build web --release
vercel build/web
```

**Netlify:**
```bash
flutter build web --release
# Upload folder `build/web` ke Netlify dashboard
```

See [DEPLOYMENT.md](../DEPLOYMENT.md) for full deployment guide.

## Configuration

### API Base URL

Edit `lib/api/api_client.dart`:

```dart
// Development
final baseUrl = 'http://127.0.0.1:8000/api';

// Production
final baseUrl = 'https://api.rasaya.app/api';
```

### Laravel Backend

Pastikan Laravel backend sudah running dan CORS dikonfigurasi untuk allow Flutter Web.

## Project Structure

```
lib/
├── main.dart              # Entry point
├── api/
│   └── api_client.dart    # API service
├── auth/
│   ├── auth_controller.dart
│   ├── auth_repository.dart
│   └── auth_state.dart
├── screens/              # All app screens
│   ├── booking_page.dart
│   ├── dashboard_page.dart
│   └── ...
└── widgets/              # Reusable widgets
```

## Testing

```bash
# Run tests
flutter test

# Test web build locally
flutter build web
cd build/web
python -m http.server 8080
# Open: http://localhost:8080
```

## Resources

- [Lab: Write your first Flutter app](https://docs.flutter.dev/get-started/codelab)
- [Cookbook: Useful Flutter samples](https://docs.flutter.dev/cookbook)
- [Online documentation](https://docs.flutter.dev/)
