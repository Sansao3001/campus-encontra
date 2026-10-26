<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminClaimResource;
use App\Models\Claim;
use Illuminate\Http\Request;

class AdminClaimController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,accepted,rejected,cancelled,completed'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Claim::query()
            ->with(['item.user:id,name,email', 'claimant:id,name,email'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $term = isset($validated['q']) ? trim($validated['q']) : null;

        if ($term !== null && $term !== '') {
            $pattern = '%'.$term.'%';

            $query->where(function ($search) use ($pattern) {
                $search->whereHas('item', function ($item) use ($pattern) {
                    $item->whereLike('title', $pattern)
                        ->orWhereHas('user', function ($user) use ($pattern) {
                            $user->whereLike('name', $pattern)
                                ->orWhereLike('email', $pattern);
                        });
                })->orWhereHas('claimant', function ($claimant) use ($pattern) {
                    $claimant->whereLike('name', $pattern)
                        ->orWhereLike('email', $pattern);
                });
            });
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return AdminClaimResource::collection(
            $query->paginate((int) ($validated['per_page'] ?? 15))->withQueryString()
        );
    }

    public function show(Claim $claim)
    {
        $claim->load(['item.user:id,name,email', 'claimant:id,name,email']);

        return response()->json(
            (new AdminClaimResource($claim))->resolve()
        );
    }
}
