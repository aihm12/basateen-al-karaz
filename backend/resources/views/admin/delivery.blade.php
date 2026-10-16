@extends('admin.layout')
@section('title','الفروع والتوصيل')
@section('content')
<div class="page-heading"><div><p class="eyebrow">متى وأين نوصل</p><h1>الفروع والتوصيل</h1><p class="muted">الأوقات بحسب توقيت المتجر. الأحياء تُطابق الاسم المدخل في عنوان العميل.</p></div></div>
<h2>الفروع</h2>
@php($fields=['name'=>['label'=>'اسم الفرع','required'=>true],'phone'=>['label'=>'الهاتف'],'address'=>['label'=>'العنوان'],'is_active'=>['label'=>'حالة الفرع','options'=>[1=>'فعال',0=>'موقوف']],'pickup_enabled'=>['label'=>'الاستلام من الفرع','options'=>[1=>'متاح',0=>'غير متاح']],'delivery_enabled'=>['label'=>'التوصيل','options'=>[1=>'متاح',0=>'غير متاح']]])
@foreach($branches as $record)<section class="panel"><details><summary>{{ $record->name }}</summary>@include('admin.record-form',['action'=>route('admin.branches.save',$record),'formKey'=>'branch-'.$record->id])</details></section>@endforeach
<h2>مناطق التوصيل</h2>
@php($fields=['branch_id'=>['label'=>'الفرع','options'=>$branches->pluck('name','id'),'required'=>true],'name'=>['label'=>'اسم المنطقة','required'=>true],'delivery_fee'=>['label'=>'رسوم التوصيل','type'=>'number','step'=>'0.01','min'=>0,'default'=>0,'required'=>true],'minimum_order'=>['label'=>'الحد الأدنى للطلب','type'=>'number','step'=>'0.01','min'=>0,'default'=>0,'required'=>true],'free_delivery_above'=>['label'=>'توصيل مجاني ابتداءً من (اختياري)','type'=>'number','step'=>'0.01','min'=>0],'estimated_minutes'=>['label'=>'الوقت المتوقع بالدقائق','type'=>'number','min'=>1,'default'=>60,'required'=>true],'districts_text'=>['label'=>'الأحياء — كل حي في سطر','type'=>'textarea','required'=>true],'is_active'=>['label'=>'الحالة','options'=>[1=>'فعالة',0=>'موقوفة'],'default'=>1]])
@foreach($zones->concat([new \App\Models\DeliveryZone]) as $record)
@php($record->districts_text=implode("\n",$record->districts??[]))
<section class="panel"><details><summary>{{ $record->name??'＋ إضافة منطقة توصيل' }}</summary>@include('admin.record-form',['action'=>route('admin.zones.save',$record->id),'formKey'=>'zone-'.($record->id??'new')])</details></section>@endforeach
<h2>مواعيد التوصيل الأسبوعية</h2>
@php($days=['الأحد','الإثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'])
@php($fields=['branch_id'=>['label'=>'الفرع','options'=>$branches->pluck('name','id'),'required'=>true],'day_of_week'=>['label'=>'اليوم','options'=>$days],'start_time'=>['label'=>'من','type'=>'time','required'=>true],'end_time'=>['label'=>'إلى','type'=>'time','required'=>true],'max_orders'=>['label'=>'السعة لكل تاريخ','type'=>'number','min'=>1,'default'=>15,'required'=>true],'is_active'=>['label'=>'الحالة','options'=>[1=>'متاح',0=>'موقوف'],'default'=>1]])
@foreach($slots->concat([new \App\Models\DeliverySlot]) as $record)<section class="panel"><details><summary>{{ $record->exists ? $days[$record->day_of_week].' · '.substr($record->start_time,0,5).' — '.substr($record->end_time,0,5).' · '.$record->branch->name : '＋ إضافة موعد' }} @if($record->exists)<span class="badge">{{ $record->is_active?'متاح':'موقوف' }}</span>@endif</summary>@include('admin.record-form',['action'=>route('admin.slots.save',$record->id),'formKey'=>'slot-'.($record->id??'new')])</details></section>@endforeach
@endsection

