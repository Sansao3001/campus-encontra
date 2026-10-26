<?php

use App\Http\Controllers\Api\Admin\AdminAuditController;
use App\Http\Controllers\Api\Admin\AdminClaimController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminItemController;
use App\Http\Controllers\Api\Admin\AdminReportController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\ClaimMessageController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rotas públicas de autenticação, com limitação de tentativas.
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:auth');
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:auth');

// Rotas públicas: consultar os itens
Route::apiResource('items', ItemController::class)
    ->only(['index', 'show']);

// Rotas protegidas: exigem autenticação e uma conta activa.
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Criar, actualizar e eliminar itens (inclui upload de fotografia)
    Route::apiResource('items', ItemController::class)
        ->except(['index', 'show']);

    // Listar apenas os itens do utilizador autenticado
    Route::get('/my-items', [ItemController::class, 'mine']);

    // Reclamações enviadas e recebidas
    Route::get('/my-claims', [ClaimController::class, 'indexSent']);
    Route::get('/received-claims', [ClaimController::class, 'indexReceived']);
    Route::get('/items/{item}/claims', [ClaimController::class, 'indexForItem']);
    Route::post('/items/{item}/claims', [ClaimController::class, 'store']);

    // Transições de estado de uma reclamação
    Route::patch('/claims/{claim}/cancel', [ClaimController::class, 'cancel']);
    Route::patch('/claims/{claim}/accept', [ClaimController::class, 'accept']);
    Route::patch('/claims/{claim}/reject', [ClaimController::class, 'reject']);
    Route::patch('/claims/{claim}/reopen', [ClaimController::class, 'reopen']);
    Route::patch('/claims/{claim}/confirm-return', [ClaimController::class, 'confirmReturn']);

    // Mensagens privadas entre as partes de uma reclamação
    Route::get('/claims/{claim}/messages', [ClaimMessageController::class, 'index']);
    Route::post('/claims/{claim}/messages', [ClaimMessageController::class, 'store'])
        ->middleware('throttle:messages');

    // Denúncias de anúncios ou comportamentos inadequados
    Route::post('/reports', [ReportController::class, 'store'])
        ->middleware('throttle:reports');

    // Consultar o utilizador autenticado
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

// Área administrativa: autenticação, conta activa e papel validado no servidor.
Route::middleware(['auth:sanctum', 'active', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{user}', [AdminUserController::class, 'show']);
        Route::patch('/users/{user}/suspend', [AdminUserController::class, 'suspend']);
        Route::patch('/users/{user}/reactivate', [AdminUserController::class, 'reactivate']);
        Route::patch('/users/{user}/role', [AdminUserController::class, 'changeRole']);

        Route::get('/items', [AdminItemController::class, 'index']);
        Route::get('/items/{item}', [AdminItemController::class, 'show']);
        Route::patch('/items/{item}/moderate', [AdminItemController::class, 'moderate']);
        Route::patch('/items/{item}/restore', [AdminItemController::class, 'restore']);

        Route::get('/claims', [AdminClaimController::class, 'index']);
        Route::get('/claims/{claim}', [AdminClaimController::class, 'show']);

        Route::get('/reports', [AdminReportController::class, 'index']);
        Route::get('/reports/{report}', [AdminReportController::class, 'show']);
        Route::patch('/reports/{report}', [AdminReportController::class, 'update']);

        Route::get('/audit-logs', [AdminAuditController::class, 'index']);
    });
