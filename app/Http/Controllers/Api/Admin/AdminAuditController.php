<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminActionResource;
use App\Models\AdminAction;
use Illuminate\Http\Request;

class AdminAuditController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = AdminAction::query()
            ->with('admin:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (isset($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        return AdminActionResource::collection(
            $query->paginate((int) ($validated['per_page'] ?? 20))->withQueryString()
        );
    }
}
