@extends('admin.layout')
@section('title','تفاصيل الطلب')
@section('content')
<div class="page-heading"><div><p class="eyebrow">الطلب #{{ $order->id }}</p><h1>{{ $order->user->name }}</h1><small dir="ltr">{{ $order->order_number }}</small></div><span class="badge {{ $order->status }}">{{ $labels[$order->status] }}</span></div>
<div class="two-col"><section class="panel"><h2>محتويات الطلب</h2>@foreach($order->items as $item)<div class="list-row"><div><strong>{{ $item->quantity }} × {{ $item->product_name }}</strong><small>{{ $item->variant_name }}</small>@foreach($item->options as $option)<small>{{ $option->option_name }}: {{ $option->value_name }}</small>@endforeach</div><strong>{{ $item->total_price }} ر.س</strong></div>@endforeach
<div class="totals">@foreach(['subtotal'=>'قيمة المنتجات','discount'=>'الخصم','delivery_fee'=>'التوصيل','tax'=>'الضريبة المضمنة','total'=>'الإجمالي'] as $key=>$label)<div class="list-row"><span>{{ $label }}</span><strong>{{ $order->$key }} ر.س</strong></div>@endforeach</div></section>
<section class="panel"><h2>التسليم والدفع</h2><p>{{ $order->delivery_type==='pickup'?'استلام من المتجر':'توصيل للعنوان' }} · {{ $order->branch->name }}</p><p dir="ltr">{{ $order->user->phone }}</p>
@if($order->address_snapshot)<div class="soft-note">@foreach(['district'=>'الحي','street'=>'الشارع','building_number'=>'المبنى','floor'=>'الطابق','apartment'=>'الشقة','instructions'=>'تعليمات'] as $key=>$label)@if($order->address_snapshot[$key]??null)<div>{{ $label }}: {{ $order->address_snapshot[$key] }}</div>@endif @endforeach</div>@endif
@if($order->delivery_date)<p>{{ $order->delivery_date->format('Y/m/d') }} · {{ $order->delivery_slot_snapshot['start_time']??'' }} — {{ $order->delivery_slot_snapshot['end_time']??'' }}</p>@endif
@if($order->address_snapshot['maps_url']??null)<p><a class="btn" target="_blank" rel="noopener noreferrer" href="{{ $order->address_snapshot['maps_url'] }}">فتح موقع العميل في Google Maps ↗</a></p>@endif
@if($order->customer_notes)<h3>ملاحظات العميل</h3><p>{{ $order->customer_notes }}</p>@endif
<p><strong>{{ $order->payment_method==='cash'?'نقدي':'إلكتروني' }}</strong> · {{ $order->payment_status==='paid'?'تم الدفع':'لم يؤكد الدفع / يحتاج مراجعة' }}</p>
@if($order->payment_method==='cash' && $order->payment_status==='pending' && in_array($order->status,['ready','assigned','out_for_delivery']))<form method="post" action="{{ route('admin.orders.collect',$order) }}">@csrf<label class="check"><input type="checkbox" name="confirmed" value="1" required>أؤكد استلام {{ $order->total }} ر.س نقدًا</label><button class="btn secondary">تسجيل التحصيل النقدي</button></form>@endif
</section></div>
<section class="panel" id="assign-driver"><h2>إسناد مندوب التوصيل</h2>
@if($order->delivery_type==='pickup')
<p class="muted">هذا الطلب للاستلام من المتجر، ولا يحتاج إلى مندوب توصيل.</p>
@elseif(auth('staff')->user()->role!=='manager')
<p class="muted">إسناد المندوب متاح لمدير المتجر بعد اكتمال تجهيز الطلب.</p>
@elseif(in_array($order->status,['pending','confirmed','preparing']))
<p>يظهر اختيار المندوب عندما تصبح حالة الطلب «جاهز».</p><p class="muted">استخدم «تحديث الحالة» أدناه بالترتيب: مؤكد ← قيد التجهيز ← جاهز، وفق تقدم العمل الفعلي.</p>
@elseif(in_array($order->status,['ready','assigned']))
@if($drivers->isEmpty())
<p>لا يوجد مندوب فعال حاليًا. <a href="{{ route('admin.team') }}">أضف حساب مندوب من فريق العمل</a> ثم عد إلى الطلب.</p>
@else
<form method="post" action="{{ route('admin.orders.assign',$order) }}" class="filters">@csrf<input type="hidden" name="expected_status" value="{{ $order->status }}"><input type="hidden" name="expected_driver" value="{{ $order->delivery?->driver_staff_id }}"><select name="driver_staff_id" required><option value="">اختر مندوبًا</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" @selected($order->delivery?->driver_staff_id===$driver->id)>{{ $driver->name }}</option>@endforeach</select><button class="btn">إسناد الطلب</button></form>
<p class="muted">يمكن تغيير المندوب قبل بدء الرحلة فقط.</p>
@endif
@else
<p class="muted">لا يمكن تغيير المندوب بعد بدء الرحلة أو انتهاء الطلب. الحالة الحالية: {{ $labels[$order->status] }}.</p>
@endif
</section>
<section class="panel"><h2>تحديث الحالة</h2>@if(count($allowed))<form method="post" action="{{ route('admin.orders.status',$order) }}" class="filters">@csrf<input type="hidden" name="expected_status" value="{{ $order->status }}"><select name="status">@foreach($allowed as $status)<option value="{{ $status }}">{{ $labels[$status] }}</option>@endforeach</select><input name="reason" placeholder="سبب الإلغاء أو ملاحظة (اختياري للحالات الأخرى)" maxlength="500"><button class="btn">تأكيد التحديث</button></form>@else<p class="muted">لا توجد انتقالات متاحة لهذا الطلب.</p>@endif
<p class="muted">لا يُجهّز الطلب الإلكتروني قبل تأكيد الدفع. الإلغاء من اللوحة متاح للمدير للطلبات النقدية غير المحصلة فقط؛ الاسترداد الإلكتروني يحتاج ربطًا لاحقًا.</p></section>
<section class="panel"><h2>سجل إجراءات الفريق</h2>@forelse($events as $event)<div class="list-row"><span>{{ $event->details }}<small>{{ $event->name }}</small></span><small>{{ $event->created_at }}</small></div>@empty<p class="muted">لم تُسجّل إجراءات بعد.</p>@endforelse</section>
@endsection



