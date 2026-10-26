<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(User $user, array $overrides = []): Item
    {
        return $user->items()->create(array_merge([
            'title' => 'Câmara fotográfica',
            'category' => 'Electrónica',
            'description' => 'Câmara encontrada no jardim.',
            'location' => 'Jardim',
            'occurrence_date' => '2026-10-20',
            'type' => 'found',
            'status' => 'open',
        ], $overrides));
    }

    public function test_estudante_denuncia_anuncio(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $item = $this->makeItem($owner);

        Sanctum::actingAs($reporter);

        $response = $this->postJson('/api/reports', [
            'target_type' => 'item',
            'target_id' => $item->id,
            'reason' => 'inappropriate',
            'details' => 'O anúncio contém conteúdo ofensivo.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('report.status', Report::STATUS_PENDING)
            ->assertJsonPath('report.target_type', Report::TARGET_ITEM)
            ->assertJsonPath('report.item.title', 'Câmara fotográfica')
            ->assertJsonMissingPath('report.item.user')
            ->assertJsonMissingPath('report.reporter');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'target_type' => Report::TARGET_ITEM,
            'item_id' => $item->id,
            'reported_user_id' => null,
            'status' => Report::STATUS_PENDING,
        ]);
    }

    public function test_estudante_denuncia_comportamento_do_autor_com_contexto(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $item = $this->makeItem($owner);

        Sanctum::actingAs($reporter);

        $this->postJson('/api/reports', [
            'target_type' => 'user',
            'target_id' => $owner->id,
            'item_id' => $item->id,
            'reason' => 'harassment',
            'details' => 'Comportamento inadequado nas mensagens.',
        ])->assertCreated()
            ->assertJsonPath('report.reported_user.id', $owner->id)
            ->assertJsonPath('report.item.id', $item->id)
            ->assertJsonMissingPath('report.reported_user.email');
    }

    public function test_denuncias_invalidas_sao_rejeitadas(): void
    {
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $item = $this->makeItem($owner);

        Sanctum::actingAs($reporter);

        $this->postJson('/api/reports', [
            'target_type' => 'item',
            'target_id' => $item->id,
            'reason' => 'motivo-invalido',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson('/api/reports', [
            'target_type' => 'item',
            'target_id' => $item->id,
            'reason' => 'spam',
        ])->assertCreated();

        $this->postJson('/api/reports', [
            'target_type' => 'item',
            'target_id' => $item->id,
            'reason' => 'spam',
        ])->assertStatus(409);

        $this->postJson('/api/reports', [
            'target_type' => 'user',
            'target_id' => $reporter->id,
            'reason' => 'spam',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('target_id');
    }

    public function test_autor_nao_pode_denunciar_o_proprio_anuncio(): void
    {
        $owner = User::factory()->create();
        $item = $this->makeItem($owner);

        Sanctum::actingAs($owner);

        $this->postJson('/api/reports', [
            'target_type' => 'item',
            'target_id' => $item->id,
            'reason' => 'other',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('target_id');
    }

    public function test_visitante_nao_cria_denuncia(): void
    {
        $owner = User::factory()->create();
        $item = $this->makeItem($owner);

        $this->postJson('/api/reports', [
            'target_type' => 'item',
            'target_id' => $item->id,
            'reason' => 'spam',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_administrador_consulta_e_actualiza_denuncia_com_auditoria(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $item = $this->makeItem($owner);

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'target_type' => Report::TARGET_ITEM,
            'item_id' => $item->id,
            'reason' => 'spam',
            'details' => 'Conteúdo repetido.',
            'status' => Report::STATUS_PENDING,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/reports?status=pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $report->id)
            ->assertJsonPath('data.0.reporter.email', $reporter->email);

        $this->patchJson('/api/admin/reports/'.$report->id, [
            'status' => 'resolved',
            'resolution_note' => 'Anúncio revisto e corrigido pelo autor.',
        ])->assertOk()
            ->assertJsonPath('report.status', 'resolved')
            ->assertJsonPath('report.resolution_note', 'Anúncio revisto e corrigido pelo autor.')
            ->assertJsonPath('report.handled_by.id', $admin->id);

        $report->refresh();

        $this->assertNotNull($report->handled_at);
        $this->assertSame($admin->id, $report->handled_by_id);
        $this->assertDatabaseHas('admin_actions', [
            'admin_id' => $admin->id,
            'action' => 'report.update',
            'target_type' => 'report',
            'target_id' => $report->id,
        ]);
    }

    public function test_decisao_terminal_exige_nota_e_estado_valido(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $reporter = User::factory()->create();
        $item = $this->makeItem($owner);

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'target_type' => Report::TARGET_ITEM,
            'item_id' => $item->id,
            'reason' => 'spam',
            'status' => Report::STATUS_PENDING,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/reports/'.$report->id, [
            'status' => 'resolved',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('resolution_note');

        $this->patchJson('/api/admin/reports/'.$report->id, [
            'status' => 'invalido',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $report->refresh();
        $this->assertSame(Report::STATUS_PENDING, $report->status);
    }

    public function test_estudante_nao_acede_gestao_de_denuncias(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->getJson('/api/admin/reports')->assertForbidden();
    }
}
