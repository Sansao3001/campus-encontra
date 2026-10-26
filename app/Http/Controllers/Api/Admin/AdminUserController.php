<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:student,admin'],
            'status' => ['nullable', 'in:active,suspended'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = User::query()
            ->withCount(['items', 'claims'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $term = isset($validated['q']) ? trim($validated['q']) : null;

        if ($term !== null && $term !== '') {
            $pattern = '%'.$term.'%';

            $query->where(function ($search) use ($pattern) {
                $search->whereLike('name', $pattern)
                    ->orWhereLike('email', $pattern);
            });
        }

        if (isset($validated['role'])) {
            $query->where('role', $validated['role']);
        }

        if (($validated['status'] ?? null) === 'active') {
            $query->whereNull('suspended_at');
        }

        if (($validated['status'] ?? null) === 'suspended') {
            $query->whereNotNull('suspended_at');
        }

        return AdminUserResource::collection(
            $query->paginate((int) ($validated['per_page'] ?? 15))->withQueryString()
        );
    }

    public function show(User $user)
    {
        $user->loadCount(['items', 'claims']);

        return response()->json(
            (new AdminUserResource($user))->resolve()
        );
    }

    public function suspend(Request $request, User $user)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'reason.required' => 'Indica a razão da suspensão.',
            'reason.string' => 'A razão da suspensão deve ser texto.',
            'reason.min' => 'A razão da suspensão deve ter pelo menos 3 caracteres.',
            'reason.max' => 'A razão da suspensão não pode ter mais de 255 caracteres.',
        ]);

        abort_if(
            $user->id === $request->user()->id,
            409,
            'Não podes suspender a tua própria conta.'
        );

        $user = DB::transaction(function () use ($request, $user, $validated) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->isSuspended()) {
                abort(409, 'Esta conta já se encontra suspensa.');
            }

            if ($lockedUser->isAdmin()) {
                $activeAdmins = User::admins()->active()->count();

                if ($activeAdmins <= 1) {
                    abort(409, 'Não é possível suspender o único administrador activo.');
                }
            }

            $lockedUser->forceFill([
                'suspended_at' => now(),
                'suspension_reason' => $validated['reason'],
            ])->save();

            // Invalida imediatamente os tokens já emitidos para esta conta.
            $lockedUser->tokens()->delete();

            AdminAction::record(
                $request->user(),
                'user.suspend',
                'user',
                $lockedUser->id,
                $validated['reason'],
            );

            return $lockedUser;
        });

        $user->loadCount(['items', 'claims']);

        return response()->json([
            'message' => 'Conta suspensa com sucesso.',
            'user' => (new AdminUserResource($user))->resolve(),
        ]);
    }

    public function reactivate(Request $request, User $user)
    {
        $user = DB::transaction(function () use ($request, $user) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (! $lockedUser->isSuspended()) {
                abort(409, 'Esta conta já se encontra activa.');
            }

            $lockedUser->forceFill([
                'suspended_at' => null,
                'suspension_reason' => null,
            ])->save();

            AdminAction::record(
                $request->user(),
                'user.reactivate',
                'user',
                $lockedUser->id,
                null,
            );

            return $lockedUser;
        });

        $user->loadCount(['items', 'claims']);

        return response()->json([
            'message' => 'Conta reactivada com sucesso.',
            'user' => (new AdminUserResource($user))->resolve(),
        ]);
    }

    public function changeRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['required', 'in:student,admin'],
        ], [
            'role.required' => 'Indica o novo papel do utilizador.',
            'role.in' => 'O papel deve ser "student" ou "admin".',
        ]);

        abort_if(
            $user->id === $request->user()->id,
            409,
            'Não podes alterar o teu próprio papel. Pede a outro administrador.'
        );

        $user = DB::transaction(function () use ($request, $user, $validated) {
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $newRole = $validated['role'];

            if ($lockedUser->role === $newRole) {
                abort(409, 'O utilizador já tem esse papel.');
            }

            if (
                $lockedUser->role === User::ROLE_ADMIN
                && $newRole === User::ROLE_STUDENT
                && ! $lockedUser->isSuspended()
            ) {
                $otherActiveAdmins = User::admins()
                    ->active()
                    ->where('id', '!=', $lockedUser->id)
                    ->count();

                if ($otherActiveAdmins < 1) {
                    abort(409, 'Não é possível remover o único administrador activo.');
                }
            }

            $oldRole = $lockedUser->role;
            $lockedUser->forceFill(['role' => $newRole])->save();

            AdminAction::record(
                $request->user(),
                'user.role_change',
                'user',
                $lockedUser->id,
                null,
                ['from' => $oldRole, 'to' => $newRole],
            );

            return $lockedUser;
        });

        $user->loadCount(['items', 'claims']);

        return response()->json([
            'message' => 'Papel actualizado com sucesso.',
            'user' => (new AdminUserResource($user))->resolve(),
        ]);
    }
}
