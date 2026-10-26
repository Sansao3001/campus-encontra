<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminItemResource;
use App\Models\AdminAction;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminItemController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:lost,found'],
            'status' => ['nullable', 'in:open,pending,returned'],
            'moderation' => ['nullable', 'in:visible,moderated'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Item::query()
            ->with(['user:id,name,email,role,suspended_at'])
            ->withCount(['claims', 'reports'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $term = isset($validated['q']) ? trim($validated['q']) : null;

        if ($term !== null && $term !== '') {
            $pattern = '%'.$term.'%';

            $query->where(function ($search) use ($pattern) {
                $search->whereLike('title', $pattern)
                    ->orWhereLike('description', $pattern)
                    ->orWhereLike('category', $pattern)
                    ->orWhereLike('location', $pattern)
                    ->orWhereHas('user', function ($user) use ($pattern) {
                        $user->whereLike('name', $pattern)
                            ->orWhereLike('email', $pattern);
                    });
            });
        }

        if (isset($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (($validated['moderation'] ?? null) === 'visible') {
            $query->whereNull('moderated_at');
        }

        if (($validated['moderation'] ?? null) === 'moderated') {
            $query->whereNotNull('moderated_at');
        }

        return AdminItemResource::collection(
            $query->paginate((int) ($validated['per_page'] ?? 15))->withQueryString()
        );
    }

    public function show(Item $item)
    {
        $item->load(['user:id,name,email,role,suspended_at', 'moderatedBy:id,name'])
            ->loadCount(['claims', 'reports']);

        return response()->json(
            (new AdminItemResource($item))->resolve()
        );
    }

    public function moderate(Request $request, Item $item)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'reason.required' => 'Indica a razão da moderação.',
            'reason.string' => 'A razão da moderação deve ser texto.',
            'reason.min' => 'A razão da moderação deve ter pelo menos 3 caracteres.',
            'reason.max' => 'A razão da moderação não pode ter mais de 1000 caracteres.',
        ]);

        $item = DB::transaction(function () use ($request, $item, $validated) {
            $lockedItem = Item::whereKey($item->id)->lockForUpdate()->firstOrFail();

            if ($lockedItem->isModerated()) {
                abort(409, 'Este anúncio já foi retirado de publicação.');
            }

            $lockedItem->forceFill([
                'moderated_at' => now(),
                'moderation_reason' => $validated['reason'],
                'moderated_by_id' => $request->user()->id,
            ])->save();

            AdminAction::record(
                $request->user(),
                'item.moderate',
                'item',
                $lockedItem->id,
                $validated['reason'],
            );

            return $lockedItem;
        });

        $item->load(['user:id,name,email,role,suspended_at', 'moderatedBy:id,name'])
            ->loadCount(['claims', 'reports']);

        return response()->json([
            'message' => 'Anúncio retirado de publicação.',
            'item' => (new AdminItemResource($item))->resolve(),
        ]);
    }

    public function restore(Request $request, Item $item)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'reason.string' => 'A razão deve ser texto.',
            'reason.max' => 'A razão não pode ter mais de 1000 caracteres.',
        ]);

        $item = DB::transaction(function () use ($request, $item, $validated) {
            $lockedItem = Item::whereKey($item->id)->lockForUpdate()->firstOrFail();

            if (! $lockedItem->isModerated()) {
                abort(409, 'Este anúncio não está retirado de publicação.');
            }

            $lockedItem->forceFill([
                'moderated_at' => null,
                'moderation_reason' => null,
                'moderated_by_id' => null,
            ])->save();

            AdminAction::record(
                $request->user(),
                'item.restore',
                'item',
                $lockedItem->id,
                $validated['reason'] ?? null,
            );

            return $lockedItem;
        });

        $item->load(['user:id,name,email,role,suspended_at', 'moderatedBy:id,name'])
            ->loadCount(['claims', 'reports']);

        return response()->json([
            'message' => 'Anúncio reposto em publicação.',
            'item' => (new AdminItemResource($item))->resolve(),
        ]);
    }
}
