@extends('admin.layout')
@section('title','تفاصيل التوصيل')
@section('content')
<a href="{{ route('admin.driver.index') }}">← طلباتي</a><div class="page-heading"><div><h1>{{ $labels[$order->status] }}</h1><small>{{ $order->order_number }}</small></div><span class="badge">{{ $order->total }} ر.س</span></div>
<section class="panel"><h2>من المتجر إلى العميل</h2><p>الفرع: {{ $order->branch->name }}</p><strong>{{ $order->user->name }}</strong><p><a class="btn secondary" href="tel:{{ $order->user->phone }}">اتصال بالعميل</a></p>
@foreach(['district'=>'الحي','street'=>'الشارع','building_number'=>'المبنى','floor'=>'الطابق','apartment'=>'الشقة','instructions'=>'تعليمات'] as $key=>$label)@if($order->address_snapshot[$key]??null)<p>{{ $label }}: {{ $order->address_snapshot[$key] }}</p>@endif @endforeach
@php($lat=$order->address_snapshot['latitude']??null) @php($lng=$order->address_snapshot['longitude']??null)
@if(is_numeric($lat) && is_numeric($lng) && abs($lat)<=90 && abs($lng)<=180)<a class="btn secondary" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($lat.','.$lng) }}">فتح الموقع في الخرائط ↗</a>@endif
@if($order->address_snapshot['maps_url']??null)<p><a class="btn" target="_blank" rel="noopener noreferrer" href="{{ $order->address_snapshot['maps_url'] }}">فتح موقع العميل في Google Maps ↗</a></p>@endif
@if($order->customer_notes)<p>ملاحظات العميل: {{ $order->customer_notes }}</p>@endif
@if($order->delivery_date)<p>الموعد: {{ $order->delivery_date->format('Y/m/d') }} · {{ $order->delivery_slot_snapshot['start_time']??'' }} — {{ $order->delivery_slot_snapshot['end_time']??'' }}</p>@endif</section>
<section class="panel"><h2>محتويات الطلب</h2>@foreach($order->items as $item)<div class="list-row"><div><strong>{{ $item->quantity }} × {{ $item->product_name }}</strong><small>{{ $item->variant_name }}</small>@foreach($item->options as $option)<small>{{ $option->value_name }}</small>@endforeach</div></div>@endforeach</section>
<section class="panel"><h2>الدفع والتسليم</h2><p>{{ $order->payment_status==='paid'?'تم الدفع':'المبلغ المطلوب: '.$order->total.' ر.س' }}</p>
@if($order->status==='out_for_delivery' && $order->payment_method==='cash' && $order->payment_status==='pending')<form method="post" action="{{ route('admin.driver.collect',$order) }}">@csrf<label class="check"><input type="checkbox" required name="confirmed" value="1">استلمت {{ $order->total }} ر.س نقدًا من العميل</label><button class="btn secondary">تسجيل استلام النقد</button></form>@endif
@foreach($allowed as $next)<form method="post" action="{{ route('admin.driver.status',$order) }}" style="margin-top:20px">@csrf<input type="hidden" name="expected_status" value="{{ $order->status }}"><input type="hidden" name="status" value="{{ $next }}"><button class="btn">{{ $next==='out_for_delivery'?'استلمت الطلب من المتجر — بدء التوصيل':'أؤكد تسليم الطلب للعميل' }}</button></form>@endforeach</section>
@endsection

