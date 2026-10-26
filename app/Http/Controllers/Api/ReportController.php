<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Item;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'target_type' => ['required', 'in:item,user'],
            'target_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'in:inappropriate,spam,harassment,fraud,other'],
            'details' => ['nullable', 'string', 'max:2000'],
            'item_id' => ['nullable', 'integer', 'min:1', 'exists:items,id'],
        ], [
            'target_type.required' => 'Indica o tipo de denúncia.',
            'target_type.in' => 'O tipo de denúncia é inválido.',
            'target_id.required' => 'Indica o alvo da denúncia.',
            'target_id.integer' => 'O alvo da denúncia é inválido.',
            'target_id.min' => 'O alvo da denúncia é inválido.',
            'reason.required' => 'Indica o motivo da denúncia.',
            'reason.in' => 'O motivo da denúncia é inválido.',
            'details.string' => 'Os detalhes devem ser texto.',
            'details.max' => 'Os detalhes não podem ter mais de 2000 caracteres.',
            'item_id.integer' => 'O anúncio indicado é inválido.',
            'item_id.exists' => 'O anúncio indicado não existe.',
        ]);

        $reporter = $request->user();
        $targetType = $validated['target_type'];
        $targetId = (int) $validated['target_id'];
        $itemId = null;
        $reportedUserId = null;

        if ($targetType === Report::TARGET_ITEM) {
            $item = Item::findOrFail($targetId);

            if ($item->user_id === $reporter->id) {
                throw ValidationException::withMessages([
                    'target_id' => ['Não podes denunciar o teu próprio anúncio.'],
                ]);
            }

            $itemId = $item->id;
        } else {
            $reportedUser = User::findOrFail($targetId);

            if ($reportedUser->id === $reporter->id) {
                throw ValidationException::withMessages([
                    'target_id' => ['Não podes denunciar-te a ti próprio.'],
                ]);
            }

            if (isset($validated['item_id'])) {
                $contextItem = Item::findOrFail($validated['item_id']);

                if ($contextItem->user_id !== $reportedUser->id) {
                    throw ValidationException::withMessages([
                        'item_id' => ['O anúncio indicado não pertence ao utilizador denunciado.'],
                    ]);
                }

                $itemId = $contextItem->id;
            }

            $reportedUserId = $reportedUser->id;
        }

        $duplicate = Report::query()
            ->where('reporter_id', $reporter->id)
            ->open();

        if ($targetType === Report::TARGET_ITEM) {
            $duplicate->where('item_id', $itemId)->whereNull('reported_user_id');
        } else {
            $duplicate->where('reported_user_id', $reportedUserId);
        }

        if ($duplicate->exists()) {
            abort(409, 'Já existe uma denúncia em aberto para este alvo.');
        }

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'target_type' => $targetType,
            'item_id' => $itemId,
            'reported_user_id' => $reportedUserId,
            'reason' => $validated['reason'],
            'details' => isset($validated['details']) ? trim($validated['details']) : null,
            'status' => Report::STATUS_PENDING,
        ]);

        $report->load(['item', 'reportedUser:id,name']);

        return response()->json([
            'message' => 'Denúncia registada. A administração irá analisá-la.',
            'report' => (new ReportResource($report))->resolve(),
        ], 201);
    }
}
