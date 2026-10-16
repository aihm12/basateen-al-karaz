@extends('admin.layout')
@section('title','نظرة عامة')
@section('content')
<div class="page-heading"><div><p class="eyebrow">يوم جديد، طلبات طازجة</p><h1>نظرة عامة</h1><p class="muted">تابع الطلبات وما يحتاج اهتمامك اليوم.</p></div><a class="btn" href="{{ route('admin.kitchen') }}">افتح شاشة التجهيز ←</a></div>
<div class="stats"><div class="stat"><span>طلبات جديدة</span><strong>{{ $counts['pending']??0 }}</strong><small>بانتظار التأكيد</small></div><div class="stat"><span>قيد التجهيز</span><strong>{{ $counts['preparing']??0 }}</strong><small>داخل المتجر</small></div><div class="stat"><span>جاهزة للتسليم</span><strong>{{ $counts['ready']??0 }}</strong><small>للاستلام أو التوصيل</small></div><div class="stat"><span>قيمة طلبات اليوم المدفوعة</span><strong>{{ number_format($sales,2) }} <em>ر.س</em></strong><small>حسب تاريخ إنشاء الطلب</small></div></div>
<div class="two-col"><section class="panel"><div class="section-head"><h2>آخر الطلبات</h2><a href="{{ route('admin.orders') }}">كل الطلبات ←</a></div>
@forelse($recent as $order)<a class="list-row" href="{{ route('admin.orders.show',$order) }}"><div><strong>{{ $order->user->name }}</strong><small dir="ltr">{{ $order->order_number }}</small></div><span class="badge {{ $order->status }}">{{ $labels[$order->status] }}</span><strong>{{ $order->total }} ر.س</strong></a>@empty<div class="empty">لا توجد طلبات بعد. ستظهر الطلبات الجديدة هنا تلقائيًا عند تحديث الصفحة.</div>@endforelse
</section><section class="panel"><h2>تنبيه المخزون</h2><p class="muted">أحجام يتبقى منها 5 وحدات أو أقل.</p>@forelse($lowStock as $stock)<div class="list-row"><span>{{ $stock->variant->product->name_ar }}<small>{{ $stock->variant->name_ar }} · {{ $stock->branch->name }}</small></span><strong class="danger">{{ $stock->stock_quantity }}</strong></div>@empty<div class="empty">لا توجد تنبيهات مخزون حاليًا.</div>@endforelse<div class="soft-note">{{ $products }} منتج في الكتالوج</div></section></div>
@endsection

