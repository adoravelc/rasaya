import 'dart:html' as html;

Future<void> openExternalUrl(String url) async {
  html.window.location.href = url;
}
