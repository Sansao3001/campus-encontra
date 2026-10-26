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

class AdminExportTest extends TestCase
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

    public function test_visitante_e_estudante_nao_exportam_dados(): void
    {
        $this->getJson('/api/admin/exports/items')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/exports/items')->assertForbidden();
        $this->getJson('/api/admin/exports/nao-existe')->assertForbidden();
    }

    public function test_exportacao_de_anuncios_e_segura_e_auditada(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();

        $this->makeItem($owner, [
            'title' => 'Mochila azul',
            'category' => 'Outros',
            'created_at' => '2026-10-21 09:00:00',
        ]);
        $this->makeItem($owner, [
            'title' => '=SUM(1,1)',
            'category' => 'Outros',
            'created_at' => '2026-10-22 09:00:00',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->get('/api/admin/exports/items?from=2026-10-20&to=2026-10-26');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('.csv', $response->headers->get('content-disposition'));

        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('ID,Título,Categoria,Tipo,Estado', $csv);
        $this->assertStringContainsString('Mochila azul', $csv);
        $this->assertStringContainsString("'=SUM(1,1)", $csv);
        $this->assertStringNotContainsString($owner->email, $csv);
        $this->assertStringNotContainsString('password', $csv);

        $this->assertDatabaseHas('admin_actions', [
            'admin_id' => $admin->id,
            'action' => 'report.export',
            'target_type' => 'export',
        ]);
    }

    public function test_exportacao_de_solicitacoes_e_denuncias_nao_expoe_dados_privados(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $claimant = User::factory()->create();

        $item = $this->makeItem($owner, [
            'title' => 'Carteira encontrada',
            'created_at' => '2026-10-21 09:00:00',
        ]);

        $claim = Claim::query()->create([
            'item_id' => $item->id,
            'claimant_id' => $claimant->id,
            'message' => 'Mensagem privada da solicitação',
            'status' => Claim::STATUS_COMPLETED,
            'completed_at' => '2026-10-24 10:00:00',
        ]);
        $claim->forceFill(['created_at' => '2026-10-22 10:00:00'])->saveQuietly();

        $report = Report::query()->create([
            'reporter_id' => $claimant->id,
            'target_type' => Report::TARGET_ITEM,
            'item_id' => $item->id,
            'reason' => 'spam',
            'details' => 'Detalhe privado da denúncia',
            'status' => Report::STATUS_PENDING,
        ]);
        $report->forceFill(['created_at' => '2026-10-23 10:00:00'])->saveQuietly();

        Sanctum::actingAs($admin);

        $claimsCsv = $this->get('/api/admin/exports/claims?from=2026-10-20&to=2026-10-26')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Carteira encontrada', $claimsCsv);
        $this->assertStringContainsString('completed', $claimsCsv);
        $this->assertStringNotContainsString('Mensagem privada da solicitação', $claimsCsv);
        $this->assertStringNotContainsString($claimant->email, $claimsCsv);

        $reportsCsv = $this->get('/api/admin/exports/reports?from=2026-10-20&to=2026-10-26')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('spam', $reportsCsv);
        $this->assertStringContainsString('pending', $reportsCsv);
        $this->assertStringNotContainsString('Detalhe privado da denúncia', $reportsCsv);
        $this->assertStringNotContainsString($claimant->email, $reportsCsv);

        $this->assertDatabaseCount('admin_actions', 2);
    }

    public function test_exportacao_rejeita_periodo_excessivo_e_dataset_desconhecido(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->get('/api/admin/exports/items?from=2024-01-01&to=2026-10-26')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');

        $this->get('/api/admin/exports/items?from=2026-10-27&to=2026-10-26')
            ->assertUnprocessable();

        $this->get('/api/admin/exports/utilizadores')->assertNotFound();
    }

    private function makeItem(User $owner, array $overrides = []): Item
    {
        $item = $owner->items()->create(array_merge([
            'title' => 'Objecto',
            'category' => 'Outros',
            'description' => 'Descrição',
            'location' => 'Campus',
            'occurrence_date' => '2026-10-20',
            'type' => 'found',
            'status' => 'open',
        ], Arr::except($overrides, ['created_at', 'updated_at'])));

        $timestamps = Arr::only($overrides, ['created_at', 'updated_at']);

        if ($timestamps !== []) {
            $item->forceFill($timestamps)->saveQuietly();
        }

        return $item;
    }
}
