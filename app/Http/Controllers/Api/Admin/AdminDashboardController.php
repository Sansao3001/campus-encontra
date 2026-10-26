<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminActionResource;
use App\Models\AdminAction;
use App\Models\Claim;
use App\Models\Item;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    private const ALLOWED_PERIODS = [7, 30, 90, 365];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'in:7,30,90,365'],
        ]);

        $days = (int) ($validated['days'] ?? 30);
        $end = CarbonImmutable::now()->endOfDay();
        $start = $end->subDays($days - 1)->startOfDay();

        $users = $this->userCounts();
        $items = $this->itemCounts();
        $claims = $this->claimCounts();
        $reports = $this->reportCounts();
        $series = $this->series($start, $end, $days);
        $categories = $this->categories($start, $end);

        return response()->json([
            'users' => $users,
            'items' => $items,
            'claims' => $claims,
            'reports' => $reports,
            'metrics' => [
                'recovery_rate' => $this->rate($items['returned'], $items['total']),
                'claim_completion_rate' => $this->rate($claims['completed'], $claims['total']),
                'pending_workload' => $claims['pending'] + $reports['pending'] + $reports['reviewing'],
            ],
            'period' => [
                'days' => $days,
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'period_totals' => [
                'items_created' => array_sum($series['items']),
                'claims_created' => array_sum($series['claims']),
                'returns_completed' => array_sum($series['returns']),
                'reports_created' => array_sum($series['reports']),
            ],
            'series' => $series,
            'categories' => $categories,
            'recent_actions' => AdminActionResource::collection(
                AdminAction::with('admin:id,name')
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
            )->resolve(),
        ]);
    }

    private function userCounts(): array
    {
        $row = DB::table('users')->selectRaw(
            'COUNT(*) as total,
             SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as admins,
             SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as students,
             SUM(CASE WHEN suspended_at IS NOT NULL THEN 1 ELSE 0 END) as suspended',
            [User::ROLE_ADMIN, User::ROLE_STUDENT],
        )->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'admins' => (int) ($row->admins ?? 0),
            'students' => (int) ($row->students ?? 0),
            'suspended' => (int) ($row->suspended ?? 0),
        ];
    }

    private function itemCounts(): array
    {
        $row = DB::table('items')->selectRaw(
            'COUNT(*) as total,
             SUM(CASE WHEN moderated_at IS NULL THEN 1 ELSE 0 END) as published,
             SUM(CASE WHEN moderated_at IS NOT NULL THEN 1 ELSE 0 END) as moderated,
             SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as lost,
             SUM(CASE WHEN type = ? THEN 1 ELSE 0 END) as found,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as open,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as returned',
            ['lost', 'found', 'open', 'pending', 'returned'],
        )->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'published' => (int) ($row->published ?? 0),
            'moderated' => (int) ($row->moderated ?? 0),
            'lost' => (int) ($row->lost ?? 0),
            'found' => (int) ($row->found ?? 0),
            'open' => (int) ($row->open ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'returned' => (int) ($row->returned ?? 0),
        ];
    }

    private function claimCounts(): array
    {
        $row = DB::table('claims')->selectRaw(
            'COUNT(*) as total,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as accepted,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed',
            [
                Claim::STATUS_PENDING,
                Claim::STATUS_ACCEPTED,
                Claim::STATUS_REJECTED,
                Claim::STATUS_CANCELLED,
                Claim::STATUS_COMPLETED,
            ],
        )->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'accepted' => (int) ($row->accepted ?? 0),
            'rejected' => (int) ($row->rejected ?? 0),
            'cancelled' => (int) ($row->cancelled ?? 0),
            'completed' => (int) ($row->completed ?? 0),
        ];
    }

    private function reportCounts(): array
    {
        $row = DB::table('reports')->selectRaw(
            'COUNT(*) as total,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as reviewing,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as resolved,
             SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as dismissed',
            [
                Report::STATUS_PENDING,
                Report::STATUS_REVIEWING,
                Report::STATUS_RESOLVED,
                Report::STATUS_DISMISSED,
            ],
        )->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'reviewing' => (int) ($row->reviewing ?? 0),
            'resolved' => (int) ($row->resolved ?? 0),
            'dismissed' => (int) ($row->dismissed ?? 0),
        ];
    }

    private function series(CarbonImmutable $start, CarbonImmutable $end, int $days): array
    {
        $labels = [];
        $buckets = [];

        for ($index = 0; $index < $days; $index++) {
            $date = $start->addDays($index)->toDateString();
            $labels[] = $date;
            $buckets[$date] = 0;
        }

        return [
            'labels' => $labels,
            'items' => $this->fillSeries(
                $this->dailyCounts('items', 'created_at', $start, $end),
                $buckets,
            ),
            'claims' => $this->fillSeries(
                $this->dailyCounts('claims', 'created_at', $start, $end),
                $buckets,
            ),
            'returns' => $this->fillSeries(
                $this->dailyCounts('claims', 'completed_at', $start, $end),
                $buckets,
            ),
            'reports' => $this->fillSeries(
                $this->dailyCounts('reports', 'created_at', $start, $end),
                $buckets,
            ),
        ];
    }

    private function dailyCounts(
        string $table,
        string $column,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $expression = $this->dayExpression($column);

        return DB::table($table)
            ->whereBetween($column, [$start, $end])
            ->selectRaw($expression.' as day, COUNT(*) as total')
            ->groupByRaw($expression)
            ->pluck('total', 'day')
            ->all();
    }

    private function fillSeries(array $counts, array $buckets): array
    {
        foreach ($buckets as $date => $_value) {
            $buckets[$date] = (int) ($counts[$date] ?? 0);
        }

        return array_values($buckets);
    }

    private function categories(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return DB::table('items')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('category as label, COUNT(*) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->orderBy('category')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'label' => (string) $row->label,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    private function dayExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%m-%d')",
            default => "date({$column})",
        };
    }

    private function rate(int $part, int $total): float
    {
        if ($total < 1) {
            return 0.0;
        }

        return round(($part / $total) * 100, 1);
    }
}
