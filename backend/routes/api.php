<?php

use App\Http\Controllers\AnnotationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClipController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\VideoUploadController;
use Illuminate\Support\Facades\Route;

// ===== 認証不要 =====
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// 共有リンク閲覧（SHARE-02）
Route::get('/share/{token}', [ShareController::class, 'show']);

// 切り抜き動画ダウンロード（LINE共有用・認証不要）
Route::get('/clips/{clip}/download', [ClipController::class, 'download']);

// ===== 認証必須 =====
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // 動画（VIDEO-01〜03）
    Route::get('/videos', [VideoController::class, 'index']);
    Route::post('/videos', [VideoController::class, 'store']);
    Route::post('/videos/upload', [VideoUploadController::class, 'store']);
    Route::delete('/videos/{video}', [VideoController::class, 'destroy']);

    // アノテーション（ANNOTATION-01〜03）
    Route::get('/videos/{video}/annotations', [AnnotationController::class, 'index']);
    Route::post('/videos/{video}/annotations', [AnnotationController::class, 'store']);
    Route::delete('/annotations/{annotation}', [AnnotationController::class, 'destroy']);

    // 共有リンク発行（SHARE-01）
    Route::post('/annotations/{annotation}/share', [ShareController::class, 'store']);

    // 切り抜き（CLIP）
    Route::post('/clips', [ClipController::class, 'store']);
    Route::get('/clips/{clip}', [ClipController::class, 'show']);
});
