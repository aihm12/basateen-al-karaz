@extends('admin.layout')
@section('title','فريق العمل')
@section('content')
<div class="page-heading"><div><p class="eyebrow">صلاحيات واضحة</p><h1>فريق العمل</h1><p class="muted">المدير يدير المتجر. الموظف يتابع الطلبات ويجهّزها ويسجّل التحصيل النقدي.</p></div></div>
@php($fields=['name'=>['label'=>'الاسم','required'=>true],'email'=>['label'=>'البريد الإلكتروني','type'=>'email','required'=>true],'role'=>['label'=>'الصلاحية','options'=>['staff'=>'موظف تجهيز','driver'=>'مندوب توصيل','manager'=>'مدير']],'is_active'=>['label'=>'الحالة','options'=>[1=>'فعال',0=>'موقوف'],'default'=>1],'password'=>['label'=>'كلمة مرور جديدة','type'=>'password','hint'=>'12 حرفًا على الأقل، تتضمن أحرفًا وأرقامًا. اتركها فارغة للإبقاء على الحالية.'],'password_confirmation'=>['label'=>'تأكيد كلمة المرور','type'=>'password']])
@foreach($team->concat([new \App\Models\Staff]) as $record)<section class="panel"><details @if(!$record->exists) open @endif><summary>{{ $record->name??'＋ إضافة عضو للفريق' }} @if($record->exists)<span class="badge">{{ $record->role==='manager'?'مدير':($record->role==='driver'?'مندوب':'موظف') }} · {{ $record->is_active?'فعال':'موقوف' }}</span>@endif</summary>@include('admin.record-form',['action'=>route('admin.team.save',$record->id),'formKey'=>'staff-'.($record->id??'new')])</details></section>@endforeach
@endsection


