<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KotlinProxyController;

// Kotlin service passthroughs
Route::get('/health/kotlin', [KotlinProxyController::class, 'healthKotlin']);
Route::get('/balance',       [KotlinProxyController::class, 'balance']);
Route::post('/transfer',     [KotlinProxyController::class, 'transfer']);

// NEW (and canonical for admin): audits proxy → Kotlin /audits
Route::get('/audits',        [KotlinProxyController::class, 'audits']);
