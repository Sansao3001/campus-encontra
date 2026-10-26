<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;

class PublicSettingsController extends Controller
{
    public function show(SettingsService $settings)
    {
        return response()->json([
            'data' => $settings->publicValues(),
        ]);
    }
}
