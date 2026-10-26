<?php

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

// Em local/testes, permite as origens habituais do Vite e do Laravel.
// Em produção, a lista tem de ser definida explicitamente; caso contrário,
// nenhuma origem cruzada é autorizada (a SPA servida pelo Laravel continua a
// funcionar por ser same-origin).
if ($origins === [] && env('APP_ENV', 'production') !== 'production') {
    $origins = [
        'http://localhost',
        'http://localhost:8000',
        'http://localhost:5173',
        'http://127.0.0.1',
        'http://127.0.0.1:8000',
        'http://127.0.0.1:5173',
    ];
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | A SPA usa autenticação por token Bearer, por isso as credenciais de
    | cookies não são necessárias. Em produção define CORS_ALLOWED_ORIGINS
    | com os domínios reais (separados por vírgulas).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => false,

];
