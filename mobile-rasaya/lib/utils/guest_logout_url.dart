String buildGuestResetUrl({
  required String guestHomeUrl,
  required String flutterOrigin,
}) {
  final loginUrl = Uri.parse(
      '$flutterOrigin/?guest=1&home_url=${Uri.encodeComponent(guestHomeUrl)}');
  final next = Uri.encodeComponent(loginUrl.toString());
  return '$guestHomeUrl?next=$next';
}
