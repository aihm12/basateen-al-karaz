@extends('admin.layout')
@section('title','الصفحة مخصصة لفريق الإدارة')
@section('content')
<section class="panel narrow"><h1>أنت الآن داخل حساب المندوب</h1><p>قائمة جميع الطلبات متاحة لفريق الإدارة والتجهيز. حساب المندوب يعرض الطلبات المسندة إليه فقط.</p><p class="muted">تسجيل الدخول بحساب آخر في تبويب من المتصفح يغيّر الحساب في التبويبات الأخرى أيضًا. لتجربة المدير والمندوب معًا، استخدم متصفحًا آخر أو نافذة خاصة لأحد الحسابين.</p><p><a class="btn" href="{{ route('admin.driver.index') }}">عرض طلباتي كمندوب</a></p><form method="post" action="{{ route('admin.logout') }}">@csrf<button class="btn secondary">تسجيل الخروج للدخول بحساب المدير</button></form></section>
@endsection
