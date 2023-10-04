<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('DiaGetStokListesi', [\App\Http\Controllers\DiaGetStokListesiController::class, 'index']);
Route::get('DiaGetStokBirimListesi', [\App\Http\Controllers\DiaGetStokBirimListesiController::class, 'index']);
Route::post('DataCheckandDirectProcess', [\App\Http\Controllers\DataCheckandDirectProcessController::class, 'index']);
