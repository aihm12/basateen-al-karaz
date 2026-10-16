import 'package:basateen_al_karaz/maps_link.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Accepts Google Maps share links and rejects unrelated destinations', () {
    for (final url in ['https://maps.app.goo.gl/example', 'https://www.google.com/maps?q=24.7,46.7', 'https://goo.gl/maps/example']) {
      expect(isGoogleMapsUrl(url), isTrue);
    }
    for (final url in ['https://maps.app.goo.gl.evil.test/x', 'javascript:alert(1)', 'https://www.google.com/url?q=evil', 'http://maps.app.goo.gl/example', 'https://user@maps.app.goo.gl/example']) {
      expect(isGoogleMapsUrl(url), isFalse);
    }
  });
}
