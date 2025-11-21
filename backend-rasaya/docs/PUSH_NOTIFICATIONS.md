# Setup Push Notifications untuk Mobile RASAYA

## Overview
Sistem reminder konseling via push notification ke mobile app Flutter 1 jam sebelum jadwal konseling.

## Backend Setup (Laravel)

### 1. Add FCM Token Column to Users Table
```bash
php artisan make:migration add_fcm_token_to_users_table
```

Migration content:
```php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->text('fcm_token')->nullable()->after('remember_token');
    });
}
```

Run migration:
```bash
php artisan migrate
```

### 2. Add FCM Token to User Model
```php
// app/Models/User.php
protected $fillable = [
    // ... existing fields
    'fcm_token',
];
```

### 3. Configure Firebase Server Key
Add to `.env`:
```
FIREBASE_SERVER_KEY=your_firebase_server_key_here
```

### 4. Setup Scheduler
Add to `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    // Check every minute for konseling starting in 1 hour
    $schedule->job(new \App\Jobs\SendKonselingReminder())
        ->everyMinute()
        ->withoutOverlapping();
}
```

Run scheduler:
```bash
php artisan schedule:work
```

Or setup cron job:
```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## Mobile App Setup (Flutter)

### 1. Add Dependencies to pubspec.yaml
```yaml
dependencies:
  firebase_core: ^2.24.0
  firebase_messaging: ^14.7.6
  flutter_local_notifications: ^16.3.0
```

### 2. Configure Firebase
- Create Firebase project at https://console.firebase.google.com
- Add Android app (with package name from AndroidManifest.xml)
- Download `google-services.json` to `android/app/`
- Add iOS app (with bundle ID from Info.plist)
- Download `GoogleService-Info.plist` to `ios/Runner/`

### 3. Update android/build.gradle.kts
```kotlin
dependencies {
    classpath("com.google.gms:google-services:4.4.0")
}
```

### 4. Update android/app/build.gradle.kts
```kotlin
plugins {
    id("com.google.gms.google-services")
}
```

### 5. Create Firebase Service in Flutter

Create `lib/services/firebase_service.dart`:
```dart
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class FirebaseService {
  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications = 
      FlutterLocalNotificationsPlugin();

  Future<void> initialize() async {
    // Request permission
    NotificationSettings settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      print('User granted permission');
    }

    // Initialize local notifications
    const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosSettings = DarwinInitializationSettings();
    const initSettings = InitializationSettings(
      android: androidSettings,
      iOS: iosSettings,
    );
    await _localNotifications.initialize(initSettings);

    // Get FCM token
    String? token = await _messaging.getToken();
    if (token != null) {
      print('FCM Token: $token');
      await _sendTokenToBackend(token);
    }

    // Handle foreground messages
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      print('Got a message whilst in the foreground!');
      _showLocalNotification(message);
    });

    // Handle background messages
    FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

    // Handle notification tap
    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      _handleNotificationTap(message);
    });
  }

  Future<void> _sendTokenToBackend(String token) async {
    // Get auth token from secure storage
    final authToken = await getAuthToken(); // Implement this
    
    try {
      final response = await http.post(
        Uri.parse('YOUR_API_BASE_URL/api/user/fcm-token'),
        headers: {
          'Authorization': 'Bearer $authToken',
          'Content-Type': 'application/json',
        },
        body: jsonEncode({'fcm_token': token}),
      );
      
      if (response.statusCode == 200) {
        print('FCM token sent to backend successfully');
      }
    } catch (e) {
      print('Error sending FCM token: $e');
    }
  }

  void _showLocalNotification(RemoteMessage message) async {
    const androidDetails = AndroidNotificationDetails(
      'konseling_channel',
      'Konseling Notifications',
      channelDescription: 'Notifications for konseling appointments',
      importance: Importance.max,
      priority: Priority.high,
    );
    
    const iosDetails = DarwinNotificationDetails();
    
    const details = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );
    
    await _localNotifications.show(
      message.hashCode,
      message.notification?.title,
      message.notification?.body,
      details,
      payload: jsonEncode(message.data),
    );
  }

  void _handleNotificationTap(RemoteMessage message) {
    final data = message.data;
    if (data['type'] == 'konseling_reminder') {
      // Navigate to booking detail screen
      navigatorKey.currentState?.pushNamed(
        '/booking-detail',
        arguments: {'booking_id': data['booking_id']},
      );
    }
  }
}

// Top-level function for background messages
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  print('Handling a background message: ${message.messageId}');
}
```

### 6. Initialize in main.dart
```dart
import 'package:firebase_core/firebase_core.dart';
import 'firebase_options.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp(
    options: DefaultFirebaseOptions.currentPlatform,
  );
  
  // Initialize Firebase Service
  final firebaseService = FirebaseService();
  await firebaseService.initialize();
  
  runApp(MyApp());
}
```

### 7. Add API Endpoint to Save FCM Token

Create route in `routes/api.php`:
```php
Route::middleware('auth:sanctum')->post('/user/fcm-token', function (Request $request) {
    $request->validate([
        'fcm_token' => 'required|string',
    ]);
    
    $request->user()->update([
        'fcm_token' => $request->fcm_token,
    ]);
    
    return response()->json(['message' => 'FCM token updated successfully']);
});
```

## Testing

### 1. Test Push Notification Manually
```bash
php artisan tinker

// Send test notification
dispatch(new \App\Jobs\SendKonselingReminder());
```

### 2. Test with Actual Booking
Create a booking with start_at exactly 1 hour from now, then wait for scheduler to run.

### 3. Check Logs
```bash
tail -f storage/logs/laravel.log
```

## Production Deployment

1. Ensure cron job is setup for Laravel Scheduler
2. Configure Firebase Cloud Messaging properly
3. Test on both Android and iOS devices
4. Monitor notification delivery rates
5. Handle token refresh when user logs out/in

## Notes

- Push notifications only work on physical devices, not simulators/emulators
- iOS requires additional APNs configuration
- Test thoroughly before production deployment
- Monitor Firebase Console for delivery statistics
- Handle gracefully when FCM token is missing
