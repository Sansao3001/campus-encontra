<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ItemController;

// Rotas públicas de autenticação
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rotas públicas: consultar os itens
Route::apiResource('items', ItemController::class)
    ->only(['index', 'show']);

// Rotas protegidas: exigem autenticação
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Criar, actualizar e eliminar itens
    Route::apiResource('items', ItemController::class)
        ->except(['index', 'show']);

    // Listar apenas os itens do utilizador autenticado
    Route::get('/my-items', [ItemController::class, 'mine']);

    // Consultar o utilizador autenticado
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
