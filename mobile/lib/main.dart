import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:url_launcher/url_launcher.dart';
import 'api.dart';
import 'maps_link.dart';
part 'checkout.dart';

const cherry = Color(0xFF8B263D);
const ink = Color(0xFF302B2A);
const cream = Color(0xFFFAF7F2);
const leaf = Color(0xFF46684D);

void main() => runApp(const KarazApp());

class KarazApp extends StatelessWidget {
  final StoreApi? api;
  const KarazApp({super.key, this.api});
  @override
  Widget build(BuildContext context) => MaterialApp(
    debugShowCheckedModeBanner: false, title: 'بساتين الكرز', locale: const Locale('ar'),
    supportedLocales: const [Locale('ar')], localizationsDelegates: GlobalMaterialLocalizations.delegates,
    theme: ThemeData(useMaterial3: true, fontFamily: 'Cairo', scaffoldBackgroundColor: cream,
      colorScheme: ColorScheme.fromSeed(seedColor: cherry, primary: cherry, secondary: leaf, surface: cream),
      textTheme: const TextTheme(bodyMedium: TextStyle(color: ink), titleLarge: TextStyle(fontWeight: FontWeight.w800, color: ink)),
      appBarTheme: const AppBarTheme(backgroundColor: cream, foregroundColor: ink, centerTitle: false, scrolledUnderElevation: 0),
      inputDecorationTheme: InputDecorationTheme(filled: true, fillColor: Colors.white, border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: BorderSide.none)),
      filledButtonTheme: FilledButtonThemeData(style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 18), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)))),
    ), home: StoreHome(api: api ?? StoreApi()),
  );
}

class StoreHome extends StatefulWidget {
  final StoreApi api;
  const StoreHome({super.key, required this.api});
  @override State<StoreHome> createState() => _StoreHomeState();
}

class _StoreHomeState extends State<StoreHome> {
  List<dynamic> products = [], categories = [], branches = [], orders = [], addresses = [];
  Json? user, cart;
  Set<int> favorites = {};
  Json paymentConfig = {};
  int page = 0, category = 0;
  String search = '', error = '';
  bool loading = true, working = false;
  StoreApi get api => widget.api;
  @override void initState() { super.initState(); load(); }
  Future<void> load() async {
    setState(() { loading = true; error = ''; });
    try {
      final results = await Future.wait([api.call('GET','products?per_page=50'),api.call('GET','categories'),api.call('GET','branches'),api.call('GET','payment-config')]);
      if (!mounted) return;
      setState(() { products = results[0]['data']; categories = results[1]['data']; branches = results[2]['data']; paymentConfig = results[3]; });
      if (api.token != null) await refreshPrivate();
    } catch (e) { if (mounted) setState(() => error = e.toString()); }
    finally { if (mounted) setState(() => loading = false); }
  }
  Future<void> refreshPrivate() async {
    if (api.token == null) return;
    try {
      final results = await Future.wait([api.call('GET','cart'),api.call('GET','orders'),api.call('GET','addresses'),api.call('GET','favorites')]);
      if (!mounted) return;
      setState(() { cart = results[0]; orders = results[1]['data']; addresses = results[2]['data']; favorites = (results[3]['data'] as List).map((e) => e['id'] as int).toSet(); });
    } on ApiException catch (e) { if (e.status == 401 && mounted) setState(() { user = null; cart = null; orders = []; addresses = []; favorites = {}; }); rethrow; }
  }
  void notify(Object message) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message.toString()), behavior: SnackBarBehavior.floating)); }
  Future<void> action(Future<void> Function() task) async {
    if (working) return; setState(() => working = true);
    try { await task(); } catch (e) { notify(e); }
    finally { if (mounted) setState(() => working = false); }
  }
  Future<bool> login() async {
    if (api.token != null) return true;
    final result = await Navigator.of(context).push<Json>(MaterialPageRoute(builder: (_) => LoginScreen(api: api)));
    if (result == null || !mounted) return false;
    setState(() => user = result);
    try { await refreshPrivate(); } catch (e) { notify(e); }
    return api.token != null;
  }
  int get count => ((cart?['data']?['items'] ?? []) as List).fold(0,(n,e) => n + (e['quantity'] as int));
  Future<void> openProduct(Json p) async {
    final selected = await Navigator.of(context).push<Json>(MaterialPageRoute(builder: (_) => ProductScreen(product: p, api: api)));
    if (selected == null || !mounted || !await login()) return;
    await action(() async { if (branches.isEmpty) throw const ApiException('لا يوجد فرع متاح.'); await api.call('POST','cart/items',{'branch_id':branches.first['id'],...selected}); await refreshPrivate(); notify('أُضيف المنتج إلى سلتك'); });
  }
  Future<void> favorite(Json p) async {
    if (!await login()) return;
    await action(() async { await api.call(favorites.contains(p['id']) ? 'DELETE' : 'PUT','favorites/${p['id']}'); await refreshPrivate(); });
  }
  @override Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Row(children: [Container(padding: const EdgeInsets.all(9),decoration: BoxDecoration(color: cherry,borderRadius: BorderRadius.circular(14)),child: const Text('🍒',style: TextStyle(fontSize: 23))),const SizedBox(width: 12),const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start,children: [Text('بساتين الكرز',maxLines: 1,overflow: TextOverflow.ellipsis,style: TextStyle(fontWeight: FontWeight.w900,fontSize: 22)),Text('طازج تختاره ... بكل حب',style: TextStyle(fontSize: 11,color: leaf),maxLines: 1,overflow: TextOverflow.ellipsis)]))]), actions: [IconButton(tooltip: 'تحديث',onPressed: loading ? null : load,icon: const Icon(Icons.refresh_rounded)),const SizedBox(width: 8)]),
    body: SafeArea(child: Align(alignment: Alignment.topCenter,child: ConstrainedBox(constraints: const BoxConstraints(maxWidth: 1120),child: loading ? const Center(child: CircularProgressIndicator()) : error.isNotEmpty ? EmptyState(icon: Icons.wifi_off_rounded,title: 'نحتاج اتصالًا بالمتجر',subtitle: error,button: 'إعادة المحاولة',onPressed: load) : Stack(children: [IndexedStack(index: page,children: [catalog(),cartPage(),ordersPage(),accountPage()]),if (working) const Positioned(top: 0,left: 0,right: 0,child: LinearProgressIndicator())])))),
    bottomNavigationBar: NavigationBar(selectedIndex: page,onDestinationSelected: (i) async { setState(() => page = i); if (i != 0 && api.token != null) await action(refreshPrivate); },destinations: [const NavigationDestination(icon: Icon(Icons.storefront_outlined),selectedIcon: Icon(Icons.storefront),label:'المتجر'),NavigationDestination(icon: Badge(label: Text('$count'),isLabelVisible: count > 0,child: const Icon(Icons.shopping_bag_outlined)),label:'السلة'),const NavigationDestination(icon: Icon(Icons.receipt_long_outlined),label:'طلباتي'),const NavigationDestination(icon: Icon(Icons.person_outline_rounded),label:'حسابي')]),
  );
  Widget catalog() {
    final shown = products.where((p) => (category == 0 || p['category_id'] == category) && ('${p['name_ar']} ${p['name_en']}').toLowerCase().contains(search.toLowerCase())).toList();
    return RefreshIndicator(onRefresh: load,child: ListView(key: const ValueKey('catalog-scroll'),padding: const EdgeInsets.all(20),children: [
      Row(children: [const Icon(Icons.location_on_outlined,size: 18,color: leaf),const SizedBox(width: 6),Expanded(child: Text(branches.isEmpty ? 'المتجر' : '${branches.first['name']}',style: const TextStyle(fontSize: 12,color: leaf))),const Chip(label: Text('نسخة تجريبية',style: TextStyle(fontSize: 11)),visualDensity: VisualDensity.compact)]),
      const SizedBox(height: 14),Container(clipBehavior: Clip.antiAlias,decoration: BoxDecoration(color: const Color(0xFFF1E5DC),borderRadius: BorderRadius.circular(26)),child: LayoutBuilder(builder: (context,c) => Row(children: [Expanded(flex: 6,child: Padding(padding: EdgeInsets.all(c.maxWidth < 500 ? 20 : 32),child: Column(crossAxisAlignment: CrossAxisAlignment.start,children: [const Text('من بساتيننا إلى بيتك',style: TextStyle(color: cherry,fontWeight: FontWeight.w700,fontSize: 12)),const SizedBox(height: 10),Text('اختيارات طازجة،\nولحظات ألذ.',style: TextStyle(fontSize: c.maxWidth < 500 ? 26 : 38,fontWeight: FontWeight.w900,height: 1.45,color: ink)),const SizedBox(height: 12),const Text('فواكه وخضار وعصائر وضيافة\nتختارها على ذوقك.',style: TextStyle(color: Color(0xFF75665E),fontSize: 12)),const SizedBox(height: 16),FilledButton(onPressed: () => setState(() => category = categories.isEmpty ? 0 : categories.first['id']),child: const Text('اكتشف الفواكه'))]))),Expanded(flex: 4,child: AspectRatio(aspectRatio: .78,child: Photo(api: api,src: '/images/catalog/cherries.png')))]))),
      const SizedBox(height: 22),TextField(onChanged: (v) => setState(() => search = v),decoration: const InputDecoration(hintText: 'ماذا تشتهي اليوم؟',prefixIcon: Icon(Icons.search_rounded))),const SizedBox(height: 18),
      SizedBox(height: 48,child: ListView(scrollDirection: Axis.horizontal,children: [Padding(padding: const EdgeInsets.only(left: 8),child: ChoiceChip(label: const Text('الكل'),selected: category == 0,onSelected: (_) => setState(() => category = 0))),...categories.map((c) => Padding(padding: const EdgeInsets.only(left: 8),child: ChoiceChip(label: Text(c['name_ar']),selected: category == c['id'],onSelected: (_) => setState(() => category = c['id']))))])),
      const SizedBox(height: 20),Row(children: [Expanded(child: Text(category == 0 ? 'اختيارات بساتين الكرز' : categories.firstWhere((c) => c['id'] == category)['name_ar'],style: const TextStyle(fontSize: 21,fontWeight: FontWeight.w800))),const SizedBox(width: 12),Text('${shown.length} منتج',style: const TextStyle(color: Colors.grey,fontSize: 12))]),const SizedBox(height: 14),
      if (shown.isEmpty) const Padding(padding: EdgeInsets.all(30),child: Center(child: Text('لم نجد منتجات مطابقة. جرّب اسمًا آخر.'))),
      LayoutBuilder(builder: (context,c) => GridView.builder(shrinkWrap: true,physics: const NeverScrollableScrollPhysics(),itemCount: shown.length,gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: c.maxWidth > 850 ? 4 : c.maxWidth > 600 ? 3 : 2,mainAxisSpacing: 14,crossAxisSpacing: 14,mainAxisExtent: c.maxWidth < 380 ? 265 : 292),itemBuilder: (_,i) => productCard(shown[i]))),
      const Padding(padding: EdgeInsets.symmetric(vertical: 22),child: Text('الأسعار والمنتجات مقترحة للمراجعة. الصور توضيحية وليست صور المخزون الفعلي.',textAlign: TextAlign.center,style: TextStyle(fontSize: 11,color: Colors.grey))),
    ]));
  }
  Widget productCard(Json p) {
    final variants = p['variants'] as List;
    return Material(color: Colors.white,borderRadius: BorderRadius.circular(20),clipBehavior: Clip.antiAlias,child: InkWell(onTap: () => openProduct(p),child: Column(crossAxisAlignment: CrossAxisAlignment.start,children: [Expanded(child: Stack(fit: StackFit.expand,children: [Photo(api: api,src: p['main_image']),Positioned(top: 4,left: 4,child: IconButton(tooltip:'المفضلة',onPressed: () => favorite(p),style: IconButton.styleFrom(backgroundColor: Colors.white.withValues(alpha: .86)),icon: Icon(favorites.contains(p['id']) ? Icons.favorite : Icons.favorite_border,color: cherry,size: 20)))])),Padding(padding: const EdgeInsets.all(12),child: Column(crossAxisAlignment: CrossAxisAlignment.start,children: [Text(p['name_ar'],maxLines: 1,overflow: TextOverflow.ellipsis,style: const TextStyle(fontWeight: FontWeight.w800,fontSize: 15)),Text(variants.isEmpty ? '' : variants.first['name_ar'],style: const TextStyle(color: Colors.grey,fontSize: 11)),const SizedBox(height: 6),Row(children: [Expanded(child: Text(variants.isEmpty ? 'غير متوفر' : sar(variants.first['price']),style: const TextStyle(color: cherry,fontWeight: FontWeight.w800,fontSize: 13))),Container(padding: const EdgeInsets.all(6),decoration: BoxDecoration(color: cherry,borderRadius: BorderRadius.circular(10)),child: const Icon(Icons.add,color: Colors.white,size: 18))])]))])));
  }
  Widget requireLogin(String title) => EmptyState(icon: Icons.person_outline,title: title,subtitle:'سجّل دخولك برقم الجوال لعرض بياناتك وإكمال طلباتك.',button:'تسجيل الدخول',onPressed: login);
  Widget cartPage() {
    if (api.token == null) return requireLogin('سلتك بانتظارك');
    final items = (cart?['data']?['items'] ?? []) as List;
    if (items.isEmpty) return EmptyState(icon: Icons.shopping_bag_outlined,title:'سلتك تحتاج قليلًا من الطزاجة',subtitle:'اختر ما تحب من منتجات بساتين الكرز.',button:'تصفح المنتجات',onPressed: () => setState(() => page = 0));
    return ListView(padding: const EdgeInsets.all(20),children: [const Text('سلة المشتريات',style: TextStyle(fontSize: 26,fontWeight: FontWeight.w900)),const SizedBox(height: 18),...items.map((item) => Card(color: Colors.white,elevation:0,child: Padding(padding: const EdgeInsets.all(12),child: Row(children: [ClipRRect(borderRadius: BorderRadius.circular(14),child: SizedBox(width: 78,height: 86,child: Photo(api:api,src:item['product']['main_image']))),const SizedBox(width:12),Expanded(child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text(item['product']['name_ar'],style:const TextStyle(fontWeight:FontWeight.w800)),Text(item['variant']['name_ar'],style:const TextStyle(fontSize:12,color:Colors.grey)),if ((item['options'] as List).isNotEmpty) Text((item['options'] as List).map((o) => o['value']['name_ar']).join('، '),style:const TextStyle(fontSize:11,color:leaf)),Text(sar(double.parse(item['unit_price'])*item['quantity']),style:const TextStyle(color:cherry,fontWeight:FontWeight.bold))])),Column(children:[Row(mainAxisSize:MainAxisSize.min,children:[IconButton(onPressed:working || item['quantity'] >= 99 ? null : () => action(() async {await api.call('PATCH','cart/items/${item['id']}',{'quantity':item['quantity']+1});await refreshPrivate();}),icon:const Icon(Icons.add_circle_outline,size:22)),Text('${item['quantity']}'),IconButton(onPressed:working ? null : () => action(() async {if(item['quantity'] == 1) {await api.call('DELETE','cart/items/${item['id']}');} else {await api.call('PATCH','cart/items/${item['id']}',{'quantity':item['quantity']-1});}await refreshPrivate();}),icon:const Icon(Icons.remove_circle_outline,size:22))]),TextButton(onPressed:working ? null : () => action(() async {await api.call('DELETE','cart/items/${item['id']}');await refreshPrivate();}),child:const Text('إزالة'))])])))),const SizedBox(height:20),SummaryLine('قيمة المنتجات',sar(cart?['subtotal'])),const Text('تُراجع الأسعار وتُحتسب رسوم التوصيل والخصم عند تأكيد الطلب.',style:TextStyle(fontSize:12,color:Colors.grey)),const SizedBox(height:20),FilledButton(onPressed:working ? null : () async {final result=await Navigator.of(context).push<Json>(MaterialPageRoute(builder: (_) => CheckoutScreen(api:api,addresses:addresses,branches:branches,paymentConfig:paymentConfig,subtotal:cart?['subtotal']??'0.00',branchId:cart!['data']['branch_id'])));if(result!=null && mounted){await action(refreshPrivate);if(mounted)setState(() => page=2);}},child:const Text('متابعة إتمام الطلب'))]);
  }
  Widget ordersPage() {
    if(api.token==null)return requireLogin('كل طلباتك في مكان واحد');
    if(orders.isEmpty)return EmptyState(icon:Icons.receipt_long_outlined,title:'لم تطلب بعد',subtitle:'ابدأ طلبك الأول وسنتابع معك تفاصيله هنا.',button:'تسوق الآن',onPressed:()=>setState(()=>page=0));
    return RefreshIndicator(onRefresh:refreshPrivate,child:ListView(padding:const EdgeInsets.all(20),children:[const Text('طلباتي',style:TextStyle(fontSize:26,fontWeight:FontWeight.w900)),const SizedBox(height:16),...orders.map((o)=>Card(color:Colors.white,elevation:0,child:ListTile(contentPadding:const EdgeInsets.all(16),leading:const CircleAvatar(backgroundColor:Color(0xFFF6E8EB),child:Icon(Icons.shopping_bag_outlined,color:cherry)),title:Text(orderStatus(o['status']),style:const TextStyle(fontWeight:FontWeight.w800)),subtitle:Text('${o['order_number']}\n${sar(o['total'])} · ${o['payment_status']=='paid'?'مدفوع':'بانتظار الدفع'}',style:const TextStyle(fontSize:12)),trailing:const Icon(Icons.chevron_right),onTap:()async{await Navigator.of(context).push(MaterialPageRoute(builder:(_)=>OrderScreen(api:api,id:o['id'])));await action(refreshPrivate);}))) ]));
  }
  Widget accountPage() {
    if(api.token==null)return requireLogin('أهلًا بك في بساتين الكرز');
    return ListView(padding:const EdgeInsets.all(24),children:[const CircleAvatar(radius:38,backgroundColor:Color(0xFFF1E1E5),child:Icon(Icons.person_outline,size:40,color:cherry)),const SizedBox(height:12),Text(user?['name']??'عميل بساتين الكرز',textAlign:TextAlign.center,style:const TextStyle(fontSize:24,fontWeight:FontWeight.w800)),Text(user?['phone']??'',textDirection:TextDirection.ltr,textAlign:TextAlign.center,style:const TextStyle(color:Colors.grey)),const SizedBox(height:28),const Text('عناويني',style:TextStyle(fontSize:20,fontWeight:FontWeight.w800)),...addresses.map((a)=>ListTile(leading:const Icon(Icons.location_on_outlined,color:leaf),title:Text(a['label']),subtitle:Text('${a['district']}، ${a['street']}'))),OutlinedButton.icon(onPressed:()async{final added=await Navigator.of(context).push<bool>(MaterialPageRoute(builder:(_)=>AddressScreen(api:api,branches:branches)));if(added==true)await action(refreshPrivate);},icon:const Icon(Icons.add),label:const Text('إضافة عنوان')),const SizedBox(height:24),const Text('المفضلة',style:TextStyle(fontSize:20,fontWeight:FontWeight.w800)),if(favorites.isEmpty)const Padding(padding:EdgeInsets.all(12),child:Text('اضغط على القلب بجانب المنتج لحفظه هنا.')), ...products.where((p)=>favorites.contains(p['id'])).map((p)=>ListTile(title:Text(p['name_ar']),leading:const Icon(Icons.favorite,color:cherry),onTap:()=>openProduct(p),trailing:IconButton(onPressed:()=>favorite(p),icon:const Icon(Icons.close)))),const SizedBox(height:24),OutlinedButton(onPressed:working?null:()=>action(()async{await api.call('POST','auth/logout');api.token=null;if(mounted)setState((){user=null;cart=null;orders=[];addresses=[];favorites={};});}),child:const Text('تسجيل الخروج'))]);
  }
}

class Photo extends StatelessWidget {
  final StoreApi api; final String? src;
  const Photo({super.key,required this.api,this.src});
  @override Widget build(BuildContext context) => src==null || src!.isEmpty ? const ColoredBox(color:Color(0xFFF3EEE7),child:Center(child:Icon(Icons.eco_outlined,color:leaf,size:40))) : Image.network(api.imageUrl(src),fit:BoxFit.cover,errorBuilder:(_,error,trace)=>const ColoredBox(color:Color(0xFFF3EEE7),child:Center(child:Icon(Icons.eco_outlined,color:leaf,size:40))));
}
class EmptyState extends StatelessWidget {
  final IconData icon;final String title,subtitle,button;final VoidCallback onPressed;
  const EmptyState({super.key,required this.icon,required this.title,required this.subtitle,required this.button,required this.onPressed});
  @override Widget build(BuildContext context)=>Center(child:SingleChildScrollView(padding:const EdgeInsets.all(30),child:Column(mainAxisSize:MainAxisSize.min,children:[Icon(icon,size:66,color:cherry),const SizedBox(height:22),Text(title,textAlign:TextAlign.center,style:const TextStyle(fontSize:23,fontWeight:FontWeight.w800)),const SizedBox(height:10),Text(subtitle,textAlign:TextAlign.center,style:const TextStyle(color:Colors.grey)),const SizedBox(height:24),FilledButton(onPressed:onPressed,child:Text(button))])));
}
class SummaryLine extends StatelessWidget {
  final String label,value;const SummaryLine(this.label,this.value,{super.key});
  @override Widget build(BuildContext context)=>Padding(padding:const EdgeInsets.symmetric(vertical:8),child:Row(children:[Expanded(child:Text(label)),Text(value,style:const TextStyle(fontWeight:FontWeight.bold,color:cherry))]));
}

class ProductScreen extends StatefulWidget {
  final Json product;final StoreApi api;const ProductScreen({super.key,required this.product,required this.api});
  @override State<ProductScreen> createState()=>_ProductScreenState();
}
class _ProductScreenState extends State<ProductScreen>{
  int variant=0,quantity=1;final Set<int> options={};String error='';
  Json get p=>widget.product;List get variants=>p['variants'];List get groups=>p['option_groups'];
  double get price=>variants.isEmpty?0:double.parse(variants[variant]['price'])+groups.expand((g)=>g['values'] as List).where((v)=>options.contains(v['id'])).fold<double>(0,(n,v)=>n+double.parse(v['additional_price']));
  void submit(){for(final g in groups){final n=(g['values'] as List).where((v)=>options.contains(v['id'])).length;final min=(g['required']==true && g['min_selection']==0)?1:g['min_selection'];if(n<min || n>g['max_selection']){setState(()=>error='راجع خيارات ${g['name_ar']}');return;}}Navigator.pop(context,{'variant_id':variants[variant]['id'],'quantity':quantity,'option_value_ids':options.toList()});}
  @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:Text(p['name_ar'])),body:Align(alignment:Alignment.topCenter,child:ConstrainedBox(constraints:const BoxConstraints(maxWidth:680),child:ListView(padding:const EdgeInsets.all(20),children:[ClipRRect(borderRadius:BorderRadius.circular(26),child:AspectRatio(aspectRatio:1.35,child:Photo(api:widget.api,src:p['main_image']))),const SizedBox(height:22),Text(p['name_ar'],style:const TextStyle(fontSize:28,fontWeight:FontWeight.w900)),Text(p['description_ar']??'',style:const TextStyle(height:1.8,color:Color(0xFF746A64))),const SizedBox(height:20),const Text('اختر الحجم',style:TextStyle(fontWeight:FontWeight.bold)),Wrap(spacing:8,children:[for(int i=0;i<variants.length;i++)ChoiceChip(label:Text('${variants[i]['name_ar']} · ${sar(variants[i]['price'])}'),selected:variant==i,onSelected:(_)=>setState(()=>variant=i))]),...groups.map((g)=>Padding(padding:const EdgeInsets.only(top:20),child:Column(crossAxisAlignment:CrossAxisAlignment.start,children:[Text('${g['name_ar']} ${g['required']==true?'(مطلوب)':'(اختياري)'}',style:const TextStyle(fontWeight:FontWeight.bold)),Wrap(spacing:8,children:(g['values'] as List).map((v)=>FilterChip(label:Text('${v['name_ar']}${double.parse(v['additional_price'])>0?' + ${sar(v['additional_price'])}':''}'),selected:options.contains(v['id']),onSelected:(on)=>setState((){if(g['selection_type']=='single')options.removeAll((g['values'] as List).map<int>((v)=>v['id']));if(on){options.add(v['id']);}else{options.remove(v['id']);}}))).toList())]))),const SizedBox(height:20),Row(children:[const Text('الكمية',style:TextStyle(fontWeight:FontWeight.bold)),const Spacer(),IconButton(onPressed:quantity>1?()=>setState(()=>quantity--):null,icon:const Icon(Icons.remove_circle_outline)),Text('$quantity'),IconButton(onPressed:quantity<99?()=>setState(()=>quantity++):null,icon:const Icon(Icons.add_circle_outline))]),if(error.isNotEmpty)Text(error,style:const TextStyle(color:Colors.red)),const SizedBox(height:18),FilledButton(onPressed:variants.isEmpty?null:submit,child:Text('إضافة للسلة · ${sar(price*quantity)}')),const SizedBox(height:12),const Text('صورة توضيحية · السعر المقترح شامل الضريبة',textAlign:TextAlign.center,style:TextStyle(color:Colors.grey,fontSize:11))]))));
}

class LoginScreen extends StatefulWidget {
  final StoreApi api;const LoginScreen({super.key,required this.api});@override State<LoginScreen> createState()=>_LoginScreenState();
}
class _LoginScreenState extends State<LoginScreen>{
  final phone=TextEditingController(),name=TextEditingController(),code=TextEditingController();bool sent=false,busy=false;String error='',devCode='',canonicalPhone='';
  @override void dispose(){phone.dispose();name.dispose();code.dispose();super.dispose();}
  Future<void> submit()async{setState((){busy=true;error='';});try{if(!sent){var p=phone.text.replaceAll(RegExp(r'\s'),'');if(p.startsWith('05'))p='+966${p.substring(1)}';if(!RegExp(r'^\+9665\d{8}$').hasMatch(p))throw const ApiException('أدخل جوالًا سعوديًا صحيحًا مثل 05xxxxxxxx.');final res=await widget.api.call('POST','auth/send-otp',{'phone':p});if(mounted)setState((){sent=true;canonicalPhone=p;devCode=res['development_code']??'';});}else{final res=await widget.api.call('POST','auth/verify-otp',{'phone':canonicalPhone,'code':code.text.trim(),'name':name.text.trim().isEmpty?'عميل بساتين الكرز':name.text.trim()});widget.api.token=res['token'];if(mounted)Navigator.pop(context,res['user']);}}catch(e){if(mounted)setState(()=>error=e.toString());}finally{if(mounted)setState(()=>busy=false);}}
  @override Widget build(BuildContext context)=>Scaffold(appBar:AppBar(title:const Text('تسجيل الدخول')),body:Center(child:ConstrainedBox(constraints:const BoxConstraints(maxWidth:480),child:ListView(shrinkWrap:true,padding:const EdgeInsets.all(28),children:[const Text('🍒',textAlign:TextAlign.center,style:TextStyle(fontSize:60)),const SizedBox(height:18),Text(sent?'رمز التحقق':'أهلًا بك في بساتين الكرز',textAlign:TextAlign.center,style:const TextStyle(fontSize:26,fontWeight:FontWeight.w800)),const SizedBox(height:12),Text(sent?'أدخل الرمز المرسل إلى $canonicalPhone':'رقم جوالك يكفي لتبدأ طلبك.',textAlign:TextAlign.center,style:const TextStyle(color:Colors.grey)),const SizedBox(height:26),if(!sent)...[TextField(controller:name,decoration:const InputDecoration(labelText:'الاسم (اختياري)')),const SizedBox(height:14),TextField(controller:phone,textDirection:TextDirection.ltr,keyboardType:TextInputType.phone,decoration:const InputDecoration(labelText:'رقم الجوال',hintText:'05xxxxxxxx'))]else...[TextField(controller:code,textDirection:TextDirection.ltr,keyboardType:TextInputType.number,maxLength:6,decoration:const InputDecoration(labelText:'رمز التحقق')),if(devCode.isNotEmpty)Container(padding:const EdgeInsets.all(14),decoration:BoxDecoration(color:const Color(0xFFEAF0E7),borderRadius:BorderRadius.circular(12)),child:Text('للتجربة المحلية فقط: $devCode\nلم تُرسل رسالة SMS.',textAlign:TextAlign.center,style:const TextStyle(color:leaf))),TextButton(onPressed:busy?null:()=>setState((){sent=false;code.clear();devCode='';}),child:const Text('تغيير الرقم أو طلب رمز جديد'))],if(error.isNotEmpty)Padding(padding:const EdgeInsets.all(12),child:Text(error,style:const TextStyle(color:Colors.red))),const SizedBox(height:20),FilledButton(onPressed:busy?null:submit,child:Text(busy?'جارٍ التنفيذ…':sent?'تأكيد الدخول':'إرسال رمز التحقق'))]))));
}


