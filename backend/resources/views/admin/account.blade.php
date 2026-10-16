@extends('admin.layout')
@section('title','حسابي')
@section('content')
<div class="page-heading"><div><p class="eyebrow">حساب الفريق</p><h1>تغيير كلمة المرور</h1></div></div><section class="panel narrow"><form method="post" action="{{ route('admin.password') }}">@csrf<label>كلمة المرور الحالية<input name="current_password" type="password" required autocomplete="current-password"></label><label>كلمة المرور الجديدة<input name="password" type="password" required minlength="12" autocomplete="new-password"></label><label>تأكيد كلمة المرور<input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"></label><p class="muted">12 حرفًا على الأقل تتضمن أحرفًا وأرقامًا.</p><button class="btn">حفظ كلمة المرور</button></form></section>
@endsection

