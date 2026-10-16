@extends('admin.layout')
@section('title','المنتجات والمخزون')
@section('content')
<div class="page-heading"><div><p class="eyebrow">كتالوج المتجر</p><h1>المنتجات والمخزون</h1><p class="muted">راجع الأسعار والصور والتوفر لكل فرع.</p></div><a class="btn" href="{{ route('admin.products.create') }}">＋ إضافة منتج</a></div>
<form class="filters"><input name="q" placeholder="ابحث بالاسم أو رمز المنتج" value="{{ request('q') }}"><button class="btn secondary">بحث</button></form>
<div class="product-grid">@forelse($products as $product)<a class="product-card" href="{{ route('admin.products.edit',$product) }}">@if($product->main_image)<img src="{{ $product->main_image }}" alt="{{ $product->name_ar }}" loading="lazy">@else<div class="photo-placeholder">🍃</div>@endif<div class="product-card-body"><span class="badge {{ $product->is_active?'delivered':'cancelled' }}">{{ $product->is_active?'ظاهر للعملاء':'مخفي' }}</span><h2>{{ $product->name_ar }}</h2><p class="muted">{{ $product->category->name_ar }} · {{ $product->variants->count() }} أحجام</p><div class="section-head"><strong>{{ $product->variants->min('price')??'—' }} ر.س</strong><span>إدارة المنتج ←</span></div></div></a>@empty<div class="empty">لا توجد منتجات مطابقة.</div>@endforelse</div>
@include('admin.pagination',['records'=>$products])
@endsection

