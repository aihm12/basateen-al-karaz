import 'dart:convert';
import 'package:basateen_al_karaz/api.dart';
import 'package:basateen_al_karaz/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  test('API sends bearer token and preserves checkout retry key', () async {
    final api = StoreApi(base: 'http://localhost:8000', client: MockClient((request) async {
      expect(request.headers['Authorization'], 'Bearer test-token');
      expect(request.headers['Idempotency-Key'], 'stable-order-key');
      expect(jsonDecode(request.body), {'delivery_type':'pickup','payment_method':'cash'});
      return http.Response('{"data":{"id":1}}', 201);
    }));
    api.token = 'test-token';
    final response = await api.call('POST','orders',{'delivery_type':'pickup','payment_method':'cash'},'stable-order-key');
    expect(response['data']['id'], 1);
  });

  test('Unauthorized response clears local token', () async {
    final api = StoreApi(base:'http://localhost:8000', client:MockClient((_) async => http.Response('{"message":"Unauthenticated"}',401)));
    api.token = 'expired';
    await expectLater(api.call('GET','cart'),throwsA(isA<ApiException>()));
    expect(api.token,isNull);
  });

  testWidgets('Arabic store loads catalog and filters search on a narrow screen', (tester) async {
    tester.view.physicalSize = const Size(390,844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final api = StoreApi(base:'http://localhost:8000',client:MockClient((request) async {
      final data = switch(request.url.path) {
        '/api/products' => {'data':[sampleProduct]},
        '/api/categories' => {'data':[{'id':1,'name_ar':'الفواكه'}]},
        '/api/branches' => {'data':[{'id':1,'name':'الفرع التجريبي','delivery_zones':[]}]},
        '/api/payment-config' => {'online_enabled':false},
        _ => <String,dynamic>{},
      };
      return http.Response(jsonEncode(data),200,headers:{'content-type':'application/json; charset=utf-8'});
    }));
    await tester.pumpWidget(KarazApp(api:api));
    await tester.pumpAndSettle();
    expect(find.text('بساتين الكرز'),findsOneWidget);
    final catalogScroll = find.descendant(
      of: find.byKey(const ValueKey('catalog-scroll')),
      matching: find.byType(Scrollable),
    ).first;
    await tester.scrollUntilVisible(find.text('مانجو'), 160, scrollable: catalogScroll);
    expect(find.text('مانجو'),findsOneWidget);
    await tester.scrollUntilVisible(find.byType(TextField), -160, scrollable: catalogScroll);
    await tester.enterText(find.byType(TextField),'تفاح');
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('لم نجد منتجات مطابقة. جرّب اسمًا آخر.'),
      160,
      scrollable: catalogScroll,
    );
    expect(find.text('لم نجد منتجات مطابقة. جرّب اسمًا آخر.'), findsOneWidget);
    expect(find.text('مانجو'),findsNothing);
    expect(tester.takeException(),isNull);
  });

  testWidgets('Product requires an option before adding a variant', (tester) async {
    await tester.pumpWidget(MaterialApp(home:ProductScreen(product:sampleProduct,api:StoreApi(base:'http://localhost:8000'))));
    await tester.pumpAndSettle();
    final button=find.widgetWithText(FilledButton,'إضافة للسلة · 24.95 ر.س');
    await tester.scrollUntilVisible(button, 180, scrollable: find.byType(Scrollable).first);
    await tester.tap(button);
    await tester.pumpAndSettle();
    expect(find.text('راجع خيارات درجة النضج'),findsOneWidget);
  });
}

final Json sampleProduct = {
  'id':1,'category_id':1,'name_ar':'مانجو','name_en':'Mango','description_ar':'مانجو للتجربة','main_image':null,
  'variants':[{'id':1,'name_ar':'1 كجم','price':'24.95'}],
  'option_groups':[{'id':1,'name_ar':'درجة النضج','selection_type':'single','required':true,'min_selection':1,'max_selection':1,'values':[{'id':1,'name_ar':'جاهز للأكل','additional_price':'0.00'}]}],
};

