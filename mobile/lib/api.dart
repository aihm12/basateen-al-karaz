import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

typedef Json = Map<String, dynamic>;

class ApiException implements Exception {
  final String message;
  final int status;
  const ApiException(this.message, [this.status = 0]);
  @override
  String toString() => message;
}

class StoreApi {
  final http.Client client;
  final String base;
  String? token;
  StoreApi({http.Client? client, String? base})
      : client = client ?? http.Client(),
        base = base ?? const String.fromEnvironment('API_URL', defaultValue: '') {
    // Tokens stay in memory in this preview; no bearer secrets in browser storage.
  }
  String get origin => base.isNotEmpty
      ? base.replaceAll(RegExp(r'/$'), '')
      : kIsWeb ? Uri.base.origin : 'http://10.0.2.2:8000';
  String imageUrl(String? image) => image == null || image.isEmpty ? '' : Uri.parse('$origin/').resolve(image).toString();
  Future<dynamic> call(String method, String route, [Json? data, String? key]) async {
    final request = http.Request(method, Uri.parse('$origin/api/$route'));
    request.headers.addAll({'Accept': 'application/json', 'Content-Type': 'application/json', if (token != null) 'Authorization': 'Bearer $token', if (key != null) 'Idempotency-Key': key});
    if (data != null) request.body = jsonEncode(data);
    http.Response response;
    try {
      response = await http.Response.fromStream(await client.send(request)).timeout(const Duration(seconds: 25));
    } catch (_) {
      throw const ApiException('تعذّر الاتصال بالمتجر. تحقق من تشغيل الخادم ثم حاول مجددًا.');
    }
    if (response.statusCode == 204) return <String, dynamic>{};
    dynamic decoded;
    try { decoded = jsonDecode(utf8.decode(response.bodyBytes)); } catch (_) { throw const ApiException('استجابة غير متوقعة من المتجر. حاول مجددًا.'); }
    if (response.statusCode >= 400) {
      if (response.statusCode == 401) { token = null; throw const ApiException('انتهت الجلسة. سجّل دخولك مجددًا.', 401); }
      if (response.statusCode == 429) throw const ApiException('محاولات كثيرة. انتظر دقيقة ثم حاول مجددًا.', 429);
      final errors = decoded['errors'];
      throw ApiException(errors is Map && errors.isNotEmpty ? (errors.values.first as List).first.toString() : (decoded['message'] ?? 'تعذّر تنفيذ الطلب.').toString(), response.statusCode);
    }
    return decoded;
  }
}

String sar(dynamic value) => '${(double.tryParse(value.toString()) ?? 0).toStringAsFixed(2)} ر.س';
String orderStatus(dynamic status) => const {'pending':'تم استلام الطلب','confirmed':'تم التأكيد','preparing':'جاري التجهيز','ready':'جاهز للاستلام','assigned':'تم تعيين المندوب','out_for_delivery':'في الطريق إليك','delivered':'تم التسليم','cancelled':'ملغي'}[status] ?? '$status';
