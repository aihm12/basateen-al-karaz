<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => 'Basateen Al Karaz', 'status' => 'running', 'api' => '/api/categories', 'environment' => app()->environment()]));
require __DIR__.'/admin.php';
