<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClaimResource;
use App\Models\Claim;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClaimController extends Controller
{
    // Reclamações enviadas pelo utilizador autenticado.
    public function indexSent(Request $request)
    {
        $validated = $this->paginationRules($request);

        $query = Claim::with(['item.user:id,name', 'claimant:id,name'])
            ->where('claimant_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $this->collectionResponse($request, $query, $validated);
    }

    // Reclamações recebidas em objectos do utilizador autenticado.
    public function indexReceived(Request $request)
    {
        $validated = $this->paginationRules($request);

        $query = Claim::with(['item.user:id,name', 'claimant:id,name'])
            ->whereHas('item', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $this->collectionResponse($request, $query, $validated);
    }

    // Listar as reclamações de um objecto específico: apenas o dono.
    public function indexForItem(Request $request, Item $item)
    {
        abort_unless(
            $item->user_id === $request->user()->id,
            403,
            'Não tens permissão para consultar as reclamações deste objecto.'
        );

        $validated = $this->paginationRules($request);

        $query = Claim::with(['item.user:id,name', 'claimant:id,name'])
            ->where('item_id', $item->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return $this->collectionResponse($request, $query, $validated);
    }

    // Criar uma reclamação para um objecto alheio.
    public function store(Request $request, Item $item)
    {
        abort_if(
            $item->user_id === $request->user()->id,
            403,
            'Não podes reclamar o teu próprio objecto.'
        );

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
        ], [
            'message.string' => 'A mensagem deve ser texto.',
            'message.max' => 'A mensagem não pode ter mais de 1000 caracteres.',
        ]);

        $claim = DB::transaction(function () use ($request, $item, $validated) {
            $lockedItem = Item::whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->isModerated()) {
                abort(409, 'Este anúncio foi retirado e já não está disponível para reclamações.');
            }

            if ($lockedItem->status !== 'open') {
                abort(409, 'Este objecto já não está disponível para reclamações.');
            }

            $hasActiveClaim = Claim::where('item_id', $lockedItem->id)
                ->where('claimant_id', $request->user()->id)
                ->whereIn('status', Claim::ACTIVE_STATUSES)
                ->exists();

            if ($hasActiveClaim) {
                abort(409, 'Já tens uma reclamação activa para este objecto.');
            }

            return Claim::create([
                'item_id' => $lockedItem->id,
                'claimant_id' => $request->user()->id,
                'message' => $validated['message'] ?? null,
                'status' => Claim::STATUS_PENDING,
            ]);
        });

        return $this->respond($claim, 201);
    }

    // O reclamante cancela a sua reclamação enquanto está pendente.
    public function cancel(Request $request, Claim $claim)
    {
        $claim = DB::transaction(function () use ($request, $claim) {
            [$lockedClaim] = $this->lockClaimAndItem($claim);

            abort_unless(
                $lockedClaim->claimant_id === $request->user()->id,
                403,
                'Só o reclamante pode cancelar esta reclamação.'
            );

            if ($lockedClaim->status !== Claim::STATUS_PENDING) {
                abort(409, 'Só é possível cancelar reclamações pendentes.');
            }

            $lockedClaim->status = Claim::STATUS_CANCELLED;
            $lockedClaim->decided_at = now();
            $lockedClaim->save();

            return $lockedClaim;
        });

        return $this->respond($claim);
    }

    // O responsável pelo objecto aceita uma reclamação e inicia a recuperação.
    public function accept(Request $request, Claim $claim)
    {
        $claim = DB::transaction(function () use ($request, $claim) {
            [$lockedClaim, $item] = $this->lockClaimAndItem($claim);

            abort_unless(
                $item->user_id === $request->user()->id,
                403,
                'Apenas o responsável pelo objecto pode aceitar reclamações.'
            );

            if ($lockedClaim->status !== Claim::STATUS_PENDING) {
                abort(409, 'Esta reclamação já foi processada.');
            }

            if ($item->status !== 'open') {
                abort(409, 'O objecto já não está disponível para aceitar reclamações.');
            }

            $lockedClaim->status = Claim::STATUS_ACCEPTED;
            $lockedClaim->decided_at = now();
            $lockedClaim->save();

            $item->status = 'pending';
            $item->save();

            // Uma aceitação torna as restantes reclamações incompatíveis.
            Claim::where('item_id', $item->id)
                ->where('id', '!=', $lockedClaim->id)
                ->where('status', Claim::STATUS_PENDING)
                ->update([
                    'status' => Claim::STATUS_REJECTED,
                    'decided_at' => now(),
                    'updated_at' => now(),
                ]);

            return $lockedClaim;
        });

        return $this->respond($claim);
    }

    // O responsável pelo objecto rejeita uma reclamação pendente.
    public function reject(Request $request, Claim $claim)
    {
        $claim = DB::transaction(function () use ($request, $claim) {
            [$lockedClaim, $item] = $this->lockClaimAndItem($claim);

            abort_unless(
                $item->user_id === $request->user()->id,
                403,
                'Apenas o responsável pelo objecto pode rejeitar reclamações.'
            );

            if ($lockedClaim->status !== Claim::STATUS_PENDING) {
                abort(409, 'Esta reclamação já foi processada.');
            }

            $lockedClaim->status = Claim::STATUS_REJECTED;
            $lockedClaim->decided_at = now();
            $lockedClaim->save();

            // O objecto continua em aberto para outras reclamações.
            return $lockedClaim;
        });

        return $this->respond($claim);
    }

    // Apenas o responsável confirma a devolução/recepção do objecto.
    public function confirmReturn(Request $request, Claim $claim)
    {
        $claim = DB::transaction(function () use ($request, $claim) {
            [$lockedClaim, $item] = $this->lockClaimAndItem($claim);

            abort_unless(
                $item->user_id === $request->user()->id,
                403,
                'Apenas o responsável pelo objecto pode confirmar a devolução.'
            );

            if ($lockedClaim->status !== Claim::STATUS_ACCEPTED) {
                abort(409, 'Só é possível confirmar a devolução de uma reclamação aceite.');
            }

            if ($item->status !== 'pending') {
                abort(409, 'O objecto não está em processo de recuperação.');
            }

            $lockedClaim->status = Claim::STATUS_COMPLETED;
            $lockedClaim->completed_at = now();
            $lockedClaim->save();

            $item->status = 'returned';
            $item->save();

            return $lockedClaim;
        });

        return $this->respond($claim);
    }

    // O responsável pelo objecto reabre o anúncio, cancelando a aceitação.
    public function reopen(Request $request, Claim $claim)
    {
        $claim = DB::transaction(function () use ($request, $claim) {
            [$lockedClaim, $item] = $this->lockClaimAndItem($claim);

            abort_unless(
                $item->user_id === $request->user()->id,
                403,
                'Apenas o responsável pelo objecto pode reabrir o anúncio.'
            );

            if ($lockedClaim->status !== Claim::STATUS_ACCEPTED) {
                abort(409, 'Só é possível reabrir uma reclamação já aceite.');
            }

            if ($item->status !== 'pending') {
                abort(409, 'O objecto não está em processo de recuperação.');
            }

            $lockedClaim->status = Claim::STATUS_CANCELLED;
            $lockedClaim->decided_at = now();
            $lockedClaim->save();

            $item->status = 'open';
            $item->save();

            return $lockedClaim;
        });

        return $this->respond($claim);
    }

    /**
     * Bloqueia primeiro o objecto e depois a reclamação, mantendo a mesma
     * ordem em todas as transições para reduzir o risco de deadlocks.
     *
     * @return array{0: Claim, 1: Item}
     */
    private function lockClaimAndItem(Claim $claim): array
    {
        $item = Item::whereKey($claim->item_id)
            ->lockForUpdate()
            ->firstOrFail();

        $lockedClaim = Claim::whereKey($claim->id)
            ->lockForUpdate()
            ->firstOrFail();

        return [$lockedClaim, $item];
    }

    private function paginationRules(Request $request): array
    {
        return $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
    }

    private function collectionResponse(Request $request, $query, array $validated)
    {
        if ($request->hasAny(['page', 'per_page'])) {
            return ClaimResource::collection(
                $query->paginate((int) ($validated['per_page'] ?? 12))->withQueryString()
            );
        }

        return response()->json(
            ClaimResource::collection($query->get())->resolve()
        );
    }

    private function respond(Claim $claim, int $status = 200)
    {
        $claim->load(['item.user:id,name', 'claimant:id,name']);

        return response()->json(
            (new ClaimResource($claim))->resolve(),
            $status
        );
    }
}
