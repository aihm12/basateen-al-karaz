<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title','لوحة الإدارة') · بساتين الكرز</title><link rel="stylesheet" href="/admin-assets/admin.css?v=20261013"></head>
<body>
@php($member = auth('staff')->user())
@if($member)
<aside class="sidebar"><a class="brand" href="{{ route('admin.dashboard') }}"><span class="brand-icon">🍒</span><span>بساتين الكرز<small>إدارة المتجر</small></span></a><span class="nav-label">العمل اليومي</span>
<nav>
@if($member->role === 'driver')<a href="{{ route('admin.driver.index') }}">طلبات التوصيل</a>@else
@foreach(['dashboard'=>'نظرة عامة','orders'=>'الطلبات','kitchen'=>'شاشة التجهيز'] as $link=>$label)<a class="{{ request()->routeIs('admin.'.$link.'*') ? 'active' : '' }}" href="{{ route('admin.'.$link) }}">{{ $label }}</a>@endforeach
@endif
@if($member->role === 'manager')<span class="nav-label">إدارة المتجر</span>@foreach(['products'=>'المنتجات والمخزون','categories'=>'الأقسام','delivery'=>'الفروع والتوصيل','coupons'=>'الكوبونات','team'=>'فريق العمل'] as $link=>$label)<a class="{{ request()->routeIs('admin.'.$link.'*') ? 'active' : '' }}" href="{{ route('admin.'.$link) }}">{{ $label }}</a>@endforeach @endif
</nav><div class="sidebar-bottom"><strong>{{ $member->name }}</strong><small>{{ $member->role === 'manager' ? 'مدير المتجر' : ($member->role === 'driver' ? 'مندوب توصيل' : 'موظف تجهيز') }}</small><a href="{{ route('admin.account') }}">حسابي وكلمة المرور</a><form method="post" action="{{ route('admin.logout') }}">@csrf<button class="link-btn">تسجيل الخروج</button></form></div></aside>
@endif
<main class="{{ $member ? 'main' : 'login-main' }}">
@if($member)<header class="topbar"><span>لوحة تشغيل بساتين الكرز</span><span class="muted">{{ now()->translatedFormat('Y/m/d') }} · <a href="/app/" target="_blank" rel="noopener">عرض المتجر ↗</a></span></header>@endif
@if(session('success'))<div class="notice success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert"><strong>راجع البيانات التالية:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')
<footer>بساتين الكرز · {{ app()->environment('local') ? 'بيئة محلية تجريبية' : 'لوحة الإدارة' }}</footer>
</main><script src="/admin-assets/admin.js?v=20261013" defer></script></body></html>



