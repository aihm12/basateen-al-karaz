bool isGoogleMapsUrl(String value) {
  final uri = Uri.tryParse(value.trim());
  if (uri == null || uri.scheme != 'https' || uri.userInfo.isNotEmpty || uri.hasPort) return false;
  final host = uri.host.toLowerCase();
  return (host == 'maps.app.goo.gl' && uri.path.length > 1) ||
      (host == 'goo.gl' && uri.path.startsWith('/maps/')) ||
      (['www.google.com', 'google.com', 'maps.google.com'].contains(host) &&
          (host == 'maps.google.com' || uri.path == '/maps' || uri.path.startsWith('/maps/')));
}
