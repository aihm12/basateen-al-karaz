<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => 'Basateen Al Karaz', 'status' => 'running', 'api' => '/api/categories', 'environment' => app()->environment()]));
require __DIR__.'/admin.php';

Route::get('/payment/return', fn () => response('<!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>العودة إلى بساتين الكرز</title><body style="font-family: sans-serif; padding: 32px; text-align: center"><h1>العودة إلى بساتين الكرز</h1><p>عد إلى التطبيق وافتح تفاصيل الطلب، ثم اضغط «التحقق من نتيجة الدفع».</p><p>إغلاق صفحة الدفع أو الوصول إلى هذه الصفحة لا يعني تأكيد الدفع؛ تُراجع النتيجة من ميسر داخل التطبيق.</p></body></html>')->header('Cache-Control', 'no-store'));
