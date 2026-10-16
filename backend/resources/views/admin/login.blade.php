@extends('admin.layout')
@section('title','دخول الإدارة')
@section('content')
<section class="login-card"><div class="brand-icon large">🍒</div><p class="eyebrow">بساتين الكرز</p><h1>أهلًا بفريق المتجر</h1><p class="muted">سجّل دخولك لإدارة الطلبات وتجهيز كل طلب بعناية.</p><form method="post" action="{{ route('admin.login.submit') }}">@csrf<label>البريد الإلكتروني<input name="email" type="email" value="{{ old('email') }}" required autocomplete="username" dir="ltr"></label><label>كلمة المرور<input name="password" type="password" required autocomplete="current-password" dir="ltr"></label><button class="btn full">دخول لوحة الإدارة</button></form><small class="muted">حساب الإدارة مستقل عن حساب العميل في التطبيق.</small></section>
@endsection

