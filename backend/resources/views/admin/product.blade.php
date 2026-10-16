@extends('admin.layout')
@section('title','إدارة المنتج')
@section('content')
<div class="page-heading"><div><p class="eyebrow">المنتجات / {{ $product->exists?'تعديل':'إضافة' }}</p><h1>{{ $product->name_ar??'منتج جديد' }}</h1></div><a href="{{ route('admin.products') }}">العودة للمنتجات ←</a></div>
<section class="panel"><h2>بيانات المنتج</h2><form method="post" action="{{ route('admin.products.save',$product->id) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="_form" value="product">
@php
$record=$product; $formKey='product';
$fields=['name_ar'=>['label'=>'الاسم بالعربية','required'=>true],'name_en'=>['label'=>'الاسم بالإنجليزية','required'=>true],'sku'=>['label'=>'رمز المنتج SKU','required'=>true],'category_id'=>['label'=>'القسم','options'=>$categories->pluck('name_ar','id'),'required'=>true],'description_ar'=>['label'=>'الوصف بالعربية','type'=>'textarea'],'description_en'=>['label'=>'الوصف بالإنجليزية','type'=>'textarea'],'tax_rate'=>['label'=>'الضريبة المضمنة في السعر (%)','type'=>'number','step'=>'0.01','min'=>0,'max'=>100,'required'=>true],'is_active'=>['label'=>'الظهور للعملاء','options'=>[0=>'مخفي',1=>'ظاهر']],'is_featured'=>['label'=>'منتج مميز','options'=>[0=>'لا',1=>'نعم']]];
@endphp
<div class="form-grid">@include('admin.fields')<label>صورة رئيسية جديدة<input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small class="muted">JPG أو PNG أو WebP، حتى 5 ميجابايت.</small></label></div>
<h3>خيارات المنتج</h3><div class="option-list">@foreach($groups as $group)<label class="check"><input type="checkbox" name="option_groups[]" value="{{ $group->id }}" @checked(in_array($group->id,old('_form')==='product'?old('option_groups',[]):$product->optionGroups->pluck('id')->all()))>{{ $group->name_ar }} <small>{{ $group->values->pluck('name_ar')->join('، ') }}</small></label>@endforeach</div>
@if(!$product->exists)<p class="soft-note">احفظ المنتج مخفيًا أولًا، ثم أضف الأحجام والأسعار ومخزون الفرع، وبعدها أظهره للعملاء.</p>@endif
<button class="btn">حفظ بيانات المنتج</button></form></section>
@if($product->exists)
<section class="panel"><h2>صور المنتج</h2><div class="gallery">@forelse($product->images as $image)<div><img src="{{ $image->image_url }}" alt="صورة المنتج"><form method="post" action="{{ route('admin.images.save',[$product,$image]) }}">@csrf<button name="action" value="primary" class="btn small secondary">{{ $product->main_image===$image->image_url?'الصورة الرئيسية':'تعيين رئيسية' }}</button><button name="action" value="remove" class="link-btn danger">إزالة من المنتج</button></form></div>@empty<p class="muted">أضف صورة من النموذج أعلاه.</p>@endforelse</div></section>
<section class="panel"><h2>الأحجام والأسعار</h2><p class="muted">الأسعار شاملة الضريبة. تعديل السعر لا يغيّر الطلبات السابقة.</p>
@foreach($product->variants->concat([new \App\Models\ProductVariant(['is_active'=>true])]) as $variant)
<details @if(!$variant->exists) open @endif><summary>{{ $variant->exists?$variant->name_ar.' · '.$variant->price.' ر.س':'＋ إضافة حجم' }}</summary><form method="post" action="{{ route('admin.variants.save',$product) }}">@csrf<input type="hidden" name="id" value="{{ $variant->id }}"><input type="hidden" name="_form" value="variant-{{ $variant->id??'new' }}">
@php($fields=['name_ar'=>['label'=>'الحجم بالعربية','required'=>true],'name_en'=>['label'=>'الحجم بالإنجليزية','required'=>true],'sku'=>['label'=>'رمز الحجم SKU','required'=>true],'price'=>['label'=>'السعر (ر.س)','type'=>'number','step'=>'0.01','min'=>0,'required'=>true],'weight'=>['label'=>'الوزن أو الحجم','type'=>'number','step'=>'0.001','min'=>0],'weight_unit'=>['label'=>'الوحدة','options'=>[''=>'بدون','g'=>'جم','kg'=>'كجم','ml'=>'مل','l'=>'لتر','piece'=>'قطعة']],'is_active'=>['label'=>'حالة الحجم','options'=>[1=>'فعال',0=>'موقوف']]])
<div class="form-grid">@include('admin.fields',['record'=>$variant,'formKey'=>'variant-'.($variant->id??'new')])</div><button class="btn">حفظ الحجم</button></form></details>
@endforeach</section>
<section class="panel"><h2>مخزون الفروع</h2><p class="muted">الكمية هي عدد وحدات الحجم المتاحة. الحقل الفارغ يعني مخزونًا غير محدود. تغيير المخزون لا يغيّر الطلبات القائمة.</p>
@foreach($product->variants as $variant)@foreach($branches as $branch)
@php($stock=$stocks->get($variant->id.'-'.$branch->id))
<form class="stock-row" method="post" action="{{ route('admin.stock.save',$product) }}">@csrf<input type="hidden" name="variant_id" value="{{ $variant->id }}"><input type="hidden" name="branch_id" value="{{ $branch->id }}"><input type="hidden" name="previous" value="{{ $stock?->stock_quantity }}">
<strong>{{ $variant->name_ar }}<small>{{ $branch->name }}</small></strong><label>التوفر<select name="is_available"><option value="0" @selected(!$stock?->is_available)>غير متاح</option><option value="1" @selected($stock?->is_available)>متاح</option></select></label><label>الكمية<input name="stock_quantity" type="number" min="0" max="1000000" value="{{ $stock?->stock_quantity }}" placeholder="غير محدود"></label><button class="btn secondary">حفظ المخزون</button></form>
@endforeach @endforeach</section>
@endif
@endsection

