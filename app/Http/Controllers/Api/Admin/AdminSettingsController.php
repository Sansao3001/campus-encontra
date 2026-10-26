<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function show(Request $request, SettingsService $settings)
    {
        return response()->json($settings->adminPayload($request));
    }

    public function update(Request $request, SettingsService $settings)
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
        ], [
            'settings.required' => 'Indica as configurações a guardar.',
            'settings.array' => 'As configurações devem ser enviadas como um objecto.',
        ]);

        $settings->update($validated['settings'], $request->user());

        return response()->json(array_merge(
            ['message' => 'Configurações guardadas com sucesso.'],
            $settings->adminPayload($request),
        ));
    }
}
