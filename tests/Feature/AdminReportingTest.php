<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Item;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-10-26 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_visitante_e_estudante_nao_acedem_aos_relatorios(): void
    {
        $this->getJson('/api/admin/reporting')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/reporting')->assertForbidden();
    }

    public function test_relatorio_agrega_apenas_o_periodo_e_respeita_filtros(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();

        $lost = $this->makeItem($owner, [
            'title' => 'Telemóvel perdido',
            'category' => 'Electrónica',
            'type' => 'lost',
            'status' => 'open',
            'created_at' => '2026-10-21 09:00:00',
        ]);
        $found = $this->makeItem($owner, [
            'title' => 'Chaves encontradas',
            'category' => 'Chaves',
            'type' => 'found',
            'status' => 'returned',
            'created_at' => '2026-10-23 10:00:00',
        ]);
        $this->makeItem($owner, [
            'title' => 'Objecto antigo',
            'category' => 'Outros',
            'status' => 'open',
            'created_at' => '2026-09-01 10:00:00',
        ]);

        $this->makeClaim($owner, $lost, [
            'status' => Claim::STATUS_PENDING,
            'created_at' => '2026-10-22 11:00:00',
        ]);
        $this->makeClaim($owner, $found, [
            'status' => Claim::STATUS_COMPLETED,
            'created_at' => '2026-10-23 11:00:00',
            'decided_at' => '2026-10-23 12:00:00',
            'completed_at' => '2026-10-24 12:00:00',
        ]);
        $this->makeClaim($owner, $lost, [
            'status' => Claim::STATUS_REJECTED,
            'created_at' => '2026-09-02 11:00:00',
        ]);

        $this->makeReport($owner, $lost, [
            'status' => Report::STATUS_PENDING,
            'created_at' => '2026-10-25 12:00:00',
        ]);
        $this->makeReport($owner, $lost, [
            'status' => Report::STATUS_RESOLVED,
            'created_at' => '2026-09-03 12:00:00',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/reporting?from=2026-10-20&to=2026-10-26');

        $response->assertOk()
            ->assertJsonPath('summary.items_created', 2)
            ->assertJsonPath('summary.items_returned', 1)
            ->assertJsonPath('summary.claims_created', 2)
            ->assertJsonPath('summary.returns_completed', 1)
            ->assertJsonPath('summary.reports_created', 1)
            ->assertJsonPath('summary.current_pending_claims', 1)
            ->assertJsonPath('summary.current_open_reports', 1)
            ->assertJsonPath('period.days', 7)
            ->assertJsonPath('period.timezone', 'UTC')
            ->assertJsonStructure([
                'period' => ['from', 'to', 'days', 'timezone'],
                'criteria' => ['type', 'status', 'claim_status', 'report_status', 'category'],
                'generated_at',
                'summary',
                'items_by_status',
                'items_by_type',
                'items_by_category',
                'claims_by_status',
                'reports_by_status',
                'filter_options' => ['categories'],
            ]);

        $body = $response->getContent();
        $this->assertStringNotContainsString($owner->email, $body);
        $this->assertStringNotContainsString('password', $body);

        $filtered = $this->getJson(
            '/api/admin/reporting?from=2026-10-20&to=2026-10-26&type=lost&category=Electr%C3%B3nica'
        );

        $filtered->assertOk()
            ->assertJsonPath('summary.items_created', 1)
            ->assertJsonPath('summary.items_returned', 0)
            ->assertJsonPath('summary.claims_created', 1);

        $this->assertSame(
            ['label' => 'lost', 'total' => 1],
            $filtered->json('items_by_type.0'),
        );
    }

    public function test_relatorio_rejeita_periodo_invalido_ou_excessivo(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/reporting?from=2024-01-01&to=2026-10-26')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');

        $this->getJson('/api/admin/reporting?from=2026-10-27&to=2026-10-26')
            ->assertUnprocessable();

        $this->getJson('/api/admin/reporting?from=2026-02-30&to=2026-10-26')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');
    }

    public function test_relatorio_devolve_estruturas_vazias_sem_dados(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/admin/reporting?from=2026-10-20&to=2026-10-26')
            ->assertOk()
            ->assertJsonPath('summary.items_created', 0)
            ->assertJsonPath('items_by_status', [])
            ->assertJsonPath('filter_options.categories', []);
    }

    private function makeItem(User $owner, array $overrides = []): Item
    {
        $attributes = [
            'title' => 'Objecto',
            'category' => 'Outros',
            'description' => 'Descrição',
            'location' => 'Campus',
            'occurrence_date' => '2026-10-20',
            'type' => 'found',
            'status' => 'open',
        ];

        $item = $owner->items()->create(array_merge(
            $attributes,
            Arr::except($overrides, ['created_at', 'updated_at']),
        ));

        $timestamps = Arr::only($overrides, ['created_at', 'updated_at']);

        if ($timestamps !== []) {
            $item->forceFill($timestamps)->saveQuietly();
        }

        return $item;
    }

    private function makeClaim(User $claimant, Item $item, array $overrides = []): Claim
    {
        $claim = Claim::query()->create([
            'item_id' => $item->id,
            'claimant_id' => $claimant->id,
            'message' => 'Solicitação de teste',
            'status' => $overrides['status'] ?? Claim::STATUS_PENDING,
            'decided_at' => $overrides['decided_at'] ?? null,
            'completed_at' => $overrides['completed_at'] ?? null,
        ]);

        $timestamps = Arr::only($overrides, ['created_at', 'updated_at', 'decided_at', 'completed_at']);

        if ($timestamps !== []) {
            $claim->forceFill($timestamps)->saveQuietly();
        }

        return $claim;
    }

    private function makeReport(User $reporter, Item $item, array $overrides = []): Report
    {
        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'target_type' => Report::TARGET_ITEM,
            'item_id' => $item->id,
            'reason' => 'spam',
            'details' => 'Detalhe privado que não deve constar no relatório.',
            'status' => $overrides['status'] ?? Report::STATUS_PENDING,
        ]);

        $timestamps = Arr::only($overrides, ['created_at', 'updated_at', 'handled_at']);

        if ($timestamps !== []) {
            $report->forceFill($timestamps)->saveQuietly();
        }

        return $report;
    }
}
