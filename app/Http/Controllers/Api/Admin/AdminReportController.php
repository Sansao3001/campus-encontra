<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminReportResource;
use App\Models\AdminAction;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,reviewing,resolved,dismissed'],
            'target_type' => ['nullable', 'in:item,user'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Report::query()
            ->with([
                'reporter:id,name,email',
                'item.user:id,name,email',
                'reportedUser:id,name,email,role,suspended_at',
                'handledBy:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $term = isset($validated['q']) ? trim($validated['q']) : null;

        if ($term !== null && $term !== '') {
            $pattern = '%'.$term.'%';

            $query->where(function ($search) use ($pattern) {
                $search->whereHas('reporter', function ($reporter) use ($pattern) {
                    $reporter->whereLike('name', $pattern)
                        ->orWhereLike('email', $pattern);
                })->orWhereHas('item', function ($item) use ($pattern) {
                    $item->whereLike('title', $pattern);
                })->orWhereHas('reportedUser', function ($reportedUser) use ($pattern) {
                    $reportedUser->whereLike('name', $pattern)
                        ->orWhereLike('email', $pattern);
                });
            });
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['target_type'])) {
            $query->where('target_type', $validated['target_type']);
        }

        return AdminReportResource::collection(
            $query->paginate((int) ($validated['per_page'] ?? 15))->withQueryString()
        );
    }

    public function show(Report $report)
    {
        $report->load([
            'reporter:id,name,email',
            'item.user:id,name,email',
            'reportedUser:id,name,email,role,suspended_at',
            'handledBy:id,name',
        ]);

        return response()->json(
            (new AdminReportResource($report))->resolve()
        );
    }

    public function update(Request $request, Report $report)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,reviewing,resolved,dismissed'],
            'resolution_note' => [
                'nullable',
                'string',
                'max:2000',
                'required_if:status,resolved,dismissed',
            ],
        ], [
            'status.required' => 'Indica o estado da denúncia.',
            'status.in' => 'O estado da denúncia é inválido.',
            'resolution_note.string' => 'A decisão deve ser texto.',
            'resolution_note.max' => 'A decisão não pode ter mais de 2000 caracteres.',
            'resolution_note.required_if' => 'Indica a decisão administrativa antes de encerrar a denúncia.',
        ]);

        $report = DB::transaction(function () use ($request, $report, $validated) {
            $lockedReport = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();
            $terminal = in_array($validated['status'], [
                Report::STATUS_RESOLVED,
                Report::STATUS_DISMISSED,
            ], true);
            $note = $validated['resolution_note'] ?? null;

            $lockedReport->forceFill([
                'status' => $validated['status'],
                'resolution_note' => $note,
                'handled_by_id' => $terminal ? $request->user()->id : null,
                'handled_at' => $terminal ? now() : null,
            ])->save();

            AdminAction::record(
                $request->user(),
                'report.update',
                'report',
                $lockedReport->id,
                $note,
                ['status' => $validated['status']],
            );

            return $lockedReport;
        });

        $report->load([
            'reporter:id,name,email',
            'item.user:id,name,email',
            'reportedUser:id,name,email,role,suspended_at',
            'handledBy:id,name',
        ]);

        return response()->json([
            'message' => 'Denúncia actualizada com sucesso.',
            'report' => (new AdminReportResource($report))->resolve(),
        ]);
    }
}
