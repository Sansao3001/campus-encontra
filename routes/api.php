<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\ClaimMessageController;
use App\Http\Controllers\Api\ItemController;
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

// Rotas protegidas: exigem autenticação
Route::middleware('auth:sanctum')->group(function () {
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

    // Consultar o utilizador autenticado
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
