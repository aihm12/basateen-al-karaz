@extends('admin.layout')
@section('title','طلبات التوصيل')
@section('content')
<div class="page-heading"><div><p class="eyebrow">رحلات بساتين الكرز</p><h1>طلبات التوصيل</h1><p class="muted">استلم الطلب من المتجر ثم ابدأ الرحلة. أكّد التسليم بعد وصوله للعميل.</p></div><a class="btn secondary" href="{{ route('admin.driver.index') }}">تحديث</a></div>
@forelse($orders as $order)<a class="panel" style="display:block" href="{{ route('admin.driver.show',$order) }}"><div class="section-head"><strong>{{ $order->order_number }}</strong><span class="badge">{{ $labels[$order->status] }}</span></div><p>{{ $order->branch->name }} ← {{ $order->address_snapshot['district']??'عنوان العميل' }}</p><strong>{{ $order->total }} ر.س · {{ $order->payment_status==='paid'?'مدفوع':'مطلوب تحصيله' }}</strong></a>@empty<section class="panel"><h2>لا توجد طلبات مسندة إليك بعد</h2><p>ستظهر هنا بعد إسنادها من مدير المتجر.</p></section>@endforelse
@include('admin.pagination',['records'=>$orders])
@endsection

