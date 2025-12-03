import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart' show kIsWeb;

class SplashScreen extends StatelessWidget {
  const SplashScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white, // White background
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Big logo in center
            Image.asset(
              'assets/images/logo_vertikal.png',
              width: 280,
              fit: BoxFit.contain,
            ),
            const SizedBox(height: 40),
            // Loading indicator
            const SizedBox(
              width: 40,
              height: 40,
              child: CircularProgressIndicator(
                strokeWidth: 3,
                valueColor: AlwaysStoppedAnimation<Color>(Color(0xFF073763)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
