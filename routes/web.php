<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Quando o frontend está compilado em public/app, a raiz encaminha o
// utilizador para a SPA sem depender de JavaScript para a navegação básica.
Route::get('/', function () {
    if (is_file(public_path('app/index.html'))) {
        return response()->view('spa-redirect');
    }

    return view('welcome');
});

$serveSpa = function () {
    $index = public_path('app/index.html');

    abort_unless(
        is_file($index),
        404,
        'O frontend ainda não foi compilado. Executa "npm run build" na pasta frontend.'
    );

    return response()->file($index, [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Cache-Control' => 'no-cache, must-revalidate',
    ]);
};

// Fallback da SPA: qualquer rota interna do Vue (ex.: /app/objectos/1)
// devolve o index.html compilado, permitindo actualizar a página.
Route::get('/app/{any?}', $serveSpa)
    ->where('any', '.*')
    ->name('spa');

// O servidor built-in do PHP (php artisan serve) reescreve /app/<rota> para
// /app/index.html e entrega ao Laravel apenas /<rota>. Este fallback garante
// que os deep links também funcionam em desenvolvimento, sem interferir com
// as rotas da API (o fallback só corre depois de todas as rotas falharem).
Route::fallback(function (Request $request) use ($serveSpa) {
    $uri = $request->getRequestUri();
    $scriptName = (string) $request->server('SCRIPT_NAME');

    abort_unless(
        str_starts_with($uri, '/app/') || str_ends_with($scriptName, '/app/index.html'),
        404
    );

    return $serveSpa();
})->name('spa.fallback');
