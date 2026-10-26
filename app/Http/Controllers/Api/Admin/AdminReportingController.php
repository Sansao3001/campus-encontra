<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Claim;
use App\Models\Report;
use App\Services\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminReportingController extends Controller
{
    private const MAX_EXPORT_ROWS = 5000;

    private const MAX_PERIOD_DAYS = 366;

    public function __construct(private SettingsService $settings)
    {
    }

    public function index(Request $request)
    {
        [$from, $to, $filters] = $this->periodAndFilters($request);
        $timezone = (string) $this->settings->get('general.timezone', 'UTC');

        $items = $this->itemsQuery($from, $to, $filters);
        $claims = $this->claimsBase($from, $to, $filters);
        $completedClaims = $this->completedClaimsQuery($from, $to, $filters);
        $reports = $this->reportsQuery($from, $to, $filters);

        return response()->json([
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => (int) $from->diffInDays($to) + 1,
                'timezone' => $timezone,
            ],
            'criteria' => $filters,
            'generated_at' => CarbonImmutable::now($timezone)->toIso8601String(),
            'summary' => [
                'new_users' => DB::table('users')
                    ->whereBetween('created_at', [$from, $to])
                    ->count(),
                'items_created' => (clone $items)->count(),
                'items_returned' => (clone $items)->where('status', 'returned')->count(),
                'claims_created' => (clone $claims)->count(),
                'returns_completed' => (clone $completedClaims)->count(),
                'reports_created' => (clone $reports)->count(),
                'current_pending_claims' => DB::table('claims')
                    ->where('status', Claim::STATUS_PENDING)
                    ->count(),
                'current_open_reports' => DB::table('reports')
                    ->whereIn('status', [Report::STATUS_PENDING, Report::STATUS_REVIEWING])
                    ->count(),
            ],
            'items_by_status' => (clone $items)
                ->selectRaw('status as label, COUNT(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
                ->all(),
            'items_by_type' => (clone $items)
                ->selectRaw('type as label, COUNT(*) as total')
                ->groupBy('type')
                ->orderBy('type')
                ->get()
                ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
                ->all(),
            'items_by_category' => (clone $items)
                ->selectRaw('category as label, COUNT(*) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->orderBy('category')
                ->limit(15)
                ->get()
                ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
                ->all(),
            'claims_by_status' => (clone $claims)
                ->selectRaw('claims.status as label, COUNT(*) as total')
                ->groupBy('claims.status')
                ->orderBy('claims.status')
                ->get()
                ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
                ->all(),
            'reports_by_status' => (clone $reports)
                ->selectRaw('status as label, COUNT(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
                ->all(),
            'filter_options' => [
                'categories' => DB::table('items')
                    ->select('category')
                    ->distinct()
                    ->orderBy('category')
                    ->limit(50)
                    ->pluck('category')
                    ->all(),
            ],
        ]);
    }

    public function export(Request $request, string $dataset)
    {
        if (! in_array($dataset, ['items', 'claims', 'reports'], true)) {
            abort(404, 'Conjunto de dados desconhecido.');
        }

        [$from, $to, $filters] = $this->periodAndFilters($request);
        $query = match ($dataset) {
            'items' => $this->itemsQuery($from, $to, $filters),
            'claims' => $this->claimsQuery($from, $to, $filters),
            'reports' => $this->reportsQuery($from, $to, $filters),
        };
        $orderColumn = match ($dataset) {
            'items' => 'items.created_at',
            'claims' => 'claims.created_at',
            'reports' => 'reports.created_at',
        };

        $count = (clone $query)->count();

        if ($count > self::MAX_EXPORT_ROWS) {
            throw ValidationException::withMessages([
                'period' => 'A exportação excede o limite de '.self::MAX_EXPORT_ROWS.' linhas. Reduz o período ou aplica mais filtros.',
            ]);
        }

        AdminAction::record(
            $request->user(),
            'report.export',
            'export',
            null,
            null,
            [
                'dataset' => $dataset,
                'rows' => $count,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'filters' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
            ],
        );

        $headers = $this->headersFor($dataset);
        $filename = sprintf(
            'campus-encontra-%s-%s.csv',
            $dataset,
            CarbonImmutable::now()->toDateString(),
        );

        return response()->streamDownload(function () use ($query, $headers, $dataset, $orderColumn) {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers, ',', '"', '');

            foreach ($query->orderBy($orderColumn)->limit(self::MAX_EXPORT_ROWS)->cursor() as $row) {
                $line = $this->rowFor($dataset, $row);
                fputcsv(
                    $output,
                    array_map(fn ($value) => $this->csvSafe($value), $line),
                    ',',
                    '"',
                    '',
                );
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function periodAndFilters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', 'in:lost,found'],
            'status' => ['nullable', 'in:open,pending,returned'],
            'claim_status' => ['nullable', 'in:pending,accepted,rejected,cancelled,completed'],
            'report_status' => ['nullable', 'in:pending,reviewing,resolved,dismissed'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $from = CarbonImmutable::parse($validated['from'] ?? now()->subDays(29)->toDateString())
            ->startOfDay();
        $to = CarbonImmutable::parse($validated['to'] ?? now()->toDateString())
            ->endOfDay();

        if ($from->gt($to)) {
            throw ValidationException::withMessages([
                'from' => 'A data inicial não pode ser posterior à data final.',
            ]);
        }

        $periodDays = (int) $from->diffInDays($to) + 1;

        if ($periodDays > self::MAX_PERIOD_DAYS) {
            throw ValidationException::withMessages([
                'from' => 'O período máximo para relatórios é de '.self::MAX_PERIOD_DAYS.' dias.',
            ]);
        }

        return [
            $from,
            $to,
            [
                'type' => $validated['type'] ?? null,
                'status' => $validated['status'] ?? null,
                'claim_status' => $validated['claim_status'] ?? null,
                'report_status' => $validated['report_status'] ?? null,
                'category' => isset($validated['category'])
                    ? trim((string) $validated['category'])
                    : null,
            ],
        ];
    }

    private function itemsQuery(
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $filters,
    ): Builder {
        $query = DB::table('items')
            ->whereBetween('items.created_at', [$from, $to]);

        $this->applyItemFilters($query, $filters);

        return $query;
    }

    private function claimsBase(
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $filters,
    ): Builder {
        $query = DB::table('claims')
            ->join('items', 'claims.item_id', '=', 'items.id')
            ->whereBetween('claims.created_at', [$from, $to]);

        $this->applyItemFilters($query, $filters, 'items');

        if (! empty($filters['claim_status'])) {
            $query->where('claims.status', $filters['claim_status']);
        }

        return $query;
    }

    private function claimsQuery(
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $filters,
    ): Builder {
        return $this->claimsBase($from, $to, $filters)
            ->select('claims.*', 'items.title', 'items.category', 'items.type');
    }

    private function completedClaimsQuery(
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $filters,
    ): Builder {
        $query = DB::table('claims')
            ->join('items', 'claims.item_id', '=', 'items.id')
            ->whereBetween('claims.completed_at', [$from, $to])
            ->where('claims.status', Claim::STATUS_COMPLETED);

        $this->applyItemFilters($query, $filters, 'items');

        return $query->select('claims.*');
    }

    private function reportsQuery(
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $filters,
    ): Builder {
        $query = DB::table('reports')
            ->whereBetween('reports.created_at', [$from, $to]);

        if (! empty($filters['report_status'])) {
            $query->where('reports.status', $filters['report_status']);
        }

        return $query;
    }

    private function applyItemFilters(Builder $query, array $filters, string $prefix = 'items'): void
    {
        if (! empty($filters['type'])) {
            $query->where($prefix.'.type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where($prefix.'.status', $filters['status']);
        }

        if (! empty($filters['category'])) {
            $query->where($prefix.'.category', $filters['category']);
        }
    }

    private function headersFor(string $dataset): array
    {
        return match ($dataset) {
            'items' => [
                'ID',
                'Título',
                'Categoria',
                'Tipo',
                'Estado',
                'Local',
                'Data da ocorrência',
                'Retirado',
                'Criado em',
            ],
            'claims' => [
                'ID da solicitação',
                'Título do anúncio',
                'Categoria do anúncio',
                'Tipo do anúncio',
                'Estado da solicitação',
                'Criada em',
                'Decidida em',
                'Concluída em',
            ],
            'reports' => [
                'ID',
                'Tipo de alvo',
                'Motivo',
                'Estado',
                'Recebida em',
                'Tratada em',
            ],
        };
    }

    private function rowFor(string $dataset, object $row): array
    {
        return match ($dataset) {
            'items' => [
                $row->id,
                $row->title,
                $row->category,
                $row->type,
                $row->status,
                $row->location,
                $row->occurrence_date,
                $row->moderated_at ? 'Sim' : 'Não',
                $row->created_at,
            ],
            'claims' => [
                $row->id,
                $row->title,
                $row->category,
                $row->type,
                $row->status,
                $row->created_at,
                $row->decided_at,
                $row->completed_at,
            ],
            'reports' => [
                $row->id,
                $row->target_type,
                $row->reason,
                $row->status,
                $row->created_at,
                $row->handled_at,
            ],
        };
    }

    /**
     * Evita que valores de texto sejam interpretados como fórmulas por
     * folhas de cálculo, sem alterar o conteúdo normal da exportação.
     */
    private function csvSafe(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;

        if (preg_match('/^[=+\-@\t\r]/', $text) === 1) {
            return "'".$text;
        }

        return $text;
    }
}
