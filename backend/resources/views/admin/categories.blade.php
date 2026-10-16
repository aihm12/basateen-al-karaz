@extends('admin.layout')
@section('title','الأقسام')
@section('content')
<div class="page-heading"><div><p class="eyebrow">تنظيم الكتالوج</p><h1>الأقسام</h1></div></div>
@php($fields=['name_ar'=>['label'=>'الاسم بالعربية','required'=>true],'name_en'=>['label'=>'الاسم بالإنجليزية','required'=>true],'sort_order'=>['label'=>'ترتيب العرض','type'=>'number','default'=>0,'min'=>0,'required'=>true],'is_active'=>['label'=>'الحالة','options'=>[1=>'ظاهر',0=>'مخفي'],'default'=>1]])
@foreach($categories->concat([new \App\Models\Category]) as $record)<section class="panel"><details @if(!$record->exists) open @endif><summary>{{ $record->name_ar??'＋ إضافة قسم' }}</summary>@include('admin.record-form',['action'=>route('admin.categories.save',$record->id),'formKey'=>'category-'.($record->id??'new')])</details></section>@endforeach
@endsection

