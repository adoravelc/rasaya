# Setup Logo RASAYA

## Color Palette
- **Navy**: `#073763` (primary)
- **Pink/Rose**: `#EBDAE3` (secondary)
- **Bright Pink**: `#EC4899` (accent)
- **Broken White**: `#F7F7F2` (background)

## Step 1: Save Logo Files

Dari attachment yang kamu berikan:

1. **Logo Horizontal** (dengan teks "RASAYA"):
   - Save sebagai: `c:\Users\Chavel\rasaya\mobile-rasaya\assets\images\logo_horizontal.png`
   - Rekomendasi: transparant background, minimal 300px width untuk retina display

2. **App Icon** (logo icon saja tanpa teks):
   - Save sebagai: `c:\Users\Chavel\rasaya\mobile-rasaya\assets\images\app_icon.png`
   - Rekomendasi: 1024x1024px, transparant atau solid background

## Step 2: Update Launcher Icons (Android & iOS)

### Option A: Manual Update

**Android:**
```powershell
# Prepare different sizes manually
# Place in: android/app/src/main/res/
# - mipmap-mdpi/ic_launcher.png (48x48)
# - mipmap-hdpi/ic_launcher.png (72x72)
# - mipmap-xhdpi/ic_launcher.png (96x96)
# - mipmap-xxhdpi/ic_launcher.png (144x144)
# - mipmap-xxxhdpi/ic_launcher.png (192x192)
```

**iOS:**
```bash
# Di macOS, buka Xcode:
# ios/Runner.xcworkspace
# Assets.xcassets > AppIcon
# Drag & drop icon di berbagai ukuran
```

### Option B: Automated with flutter_launcher_icons (Recommended)

1. Install package:
```powershell
cd c:\Users\Chavel\rasaya\mobile-rasaya
flutter pub add --dev flutter_launcher_icons
```

2. Sudah ada config di `pubspec.yaml` (lihat bagian bawah file ini)

3. Generate icons:
```powershell
flutter pub get
dart run flutter_launcher_icons
```

## Step 3: Verify

Run app untuk test logo muncul di login page:
```powershell
flutter run
```

Check launcher icon:
- Android: install APK dan lihat app drawer
- iOS: build dan check di home screen

## flutter_launcher_icons Config

Tambahkan ke `pubspec.yaml`:

```yaml
flutter_launcher_icons:
  android: true
  ios: true
  image_path: "assets/images/app_icon.png"
  adaptive_icon_background: "#073763" # Navy background
  adaptive_icon_foreground: "assets/images/app_icon.png"
  remove_alpha_ios: true
```

Lalu run:
```powershell
flutter pub get
dart run flutter_launcher_icons
```
