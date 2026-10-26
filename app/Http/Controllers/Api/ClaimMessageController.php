<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClaimMessageResource;
use App\Models\Claim;
use Illuminate\Http\Request;

class ClaimMessageController extends Controller
{
    // Listar as mensagens privadas de uma reclamação (apenas participantes).
    public function index(Request $request, Claim $claim)
    {
        $this->ensureParticipant($request, $claim);

        $messages = $claim->messages()
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return response()->json(
            ClaimMessageResource::collection($messages)->resolve()
        );
    }

    // Enviar uma mensagem privada no âmbito de uma reclamação activa.
    public function store(Request $request, Claim $claim)
    {
        $this->ensureParticipant($request, $claim);

        if (! in_array($claim->status, Claim::ACTIVE_STATUSES, true)) {
            abort(409, 'Esta reclamação já não permite trocar mensagens.');
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ], [
            'body.required' => 'Escreve uma mensagem.',
            'body.string' => 'A mensagem deve ser texto.',
            'body.min' => 'A mensagem não pode estar vazia.',
            'body.max' => 'A mensagem não pode ter mais de 2000 caracteres.',
        ]);

        $message = $claim->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);

        $message->load('sender:id,name');

        return response()->json(
            (new ClaimMessageResource($message))->resolve(),
            201
        );
    }

    /**
     * Apenas o reclamante e o responsável pelo objecto podem ler ou escrever.
     * Qualquer outro utilizador recebe 403, mesmo que conheça o id.
     */
    private function ensureParticipant(Request $request, Claim $claim): void
    {
        $claim->loadMissing('item:id,user_id');

        $isClaimant = $claim->claimant_id === $request->user()->id;
        $isOwner = $claim->item !== null
            && $claim->item->user_id === $request->user()->id;

        abort_unless(
            $isClaimant || $isOwner,
            403,
            'Só as partes envolvidas nesta reclamação podem aceder às mensagens.'
        );
    }
}
