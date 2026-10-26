<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminItemModerationTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(User $user, array $overrides = []): Item
    {
        return $user->items()->create(array_merge([
            'title' => 'Mochila azul',
            'category' => 'Material escolar',
            'description' => 'Mochila encontrada na biblioteca.',
            'location' => 'Biblioteca',
            'occurrence_date' => '2026-10-20',
            'type' => 'found',
            'status' => 'open',
        ], $overrides));
    }

    public function test_administrador_retira_anuncio_e_regista_auditoria(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $item = $this->makeItem($owner);

        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/admin/items/'.$item->id.'/moderate', [
            'reason' => 'Conteúdo ofensivo e inadequado.',
        ]);

        $response->assertOk()
            ->assertJsonPath('item.is_moderated', true)
            ->assertJsonPath('item.moderation_reason', 'Conteúdo ofensivo e inadequado.');

        $item->refresh();

        $this->assertNotNull($item->moderated_at);
        $this->assertSame($admin->id, $item->moderated_by_id);
        $this->assertDatabaseHas('items', ['id' => $item->id]);
        $this->assertDatabaseHas('admin_actions', [
            'admin_id' => $admin->id,
            'action' => 'item.moderate',
            'target_type' => 'item',
            'target_id' => $item->id,
        ]);
    }

    public function test_anuncio_retirado_desaparece_da_area_publica_mas_continua_na_base_de_dados(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $item = $this->makeItem($owner);

        Sanctum::actingAs($admin);
        $this->patchJson('/api/admin/items/'.$item->id.'/moderate', [
            'reason' => 'Anúncio duplicado.',
        ])->assertOk();

        Sanctum::actingAs($other);
        $this->getJson('/api/items')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/items/'.$item->id)->assertNotFound();

        Sanctum::actingAs($owner);
        $this->getJson('/api/items/'.$item->id)
            ->assertOk()
            ->assertJsonPath('is_moderated', true)
            ->assertJsonPath('moderation_reason', 'Anúncio duplicado.');

        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }

    public function test_anuncio_retirado_nao_aceita_novas_reclamacoes(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner);

        $item->forceFill([
            'moderated_at' => now(),
            'moderation_reason' => 'Conteúdo inadequado.',
            'moderated_by_id' => $admin->id,
        ])->save();

        Sanctum::actingAs($claimant);

        $this->postJson('/api/items/'.$item->id.'/claims', [
            'message' => 'Tento reclamar mesmo assim.',
        ])->assertStatus(409);

        $this->assertDatabaseCount('claims', 0);
    }

    public function test_administrador_restaura_anuncio(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $item = $this->makeItem($owner, [
            'moderated_at' => now(),
            'moderation_reason' => 'Razão anterior.',
            'moderated_by_id' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/items/'.$item->id.'/restore', [
            'reason' => 'Revisão concluída sem infracção.',
        ])->assertOk()
            ->assertJsonPath('item.is_moderated', false);

        $item->refresh();

        $this->assertNull($item->moderated_at);
        $this->assertNull($item->moderation_reason);
        $this->assertNull($item->moderated_by_id);
        $this->assertDatabaseHas('admin_actions', [
            'admin_id' => $admin->id,
            'action' => 'item.restore',
            'target_id' => $item->id,
        ]);

        $this->getJson('/api/items')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_operacoes_de_moderacao_invalidas_sao_rejeitadas(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $item = $this->makeItem($owner);
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/items/'.$item->id.'/moderate', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->patchJson('/api/admin/items/'.$item->id.'/moderate', [
            'reason' => 'x',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->patchJson('/api/admin/items/'.$item->id.'/restore')
            ->assertStatus(409);

        $this->patchJson('/api/admin/items/'.$item->id.'/moderate', [
            'reason' => 'Razão válida.',
        ])->assertOk();

        $this->patchJson('/api/admin/items/'.$item->id.'/moderate', [
            'reason' => 'Outra razão.',
        ])->assertStatus(409);
    }

    public function test_estudante_nao_modera_anuncios(): void
    {
        $owner = User::factory()->create();
        $student = User::factory()->create();
        $item = $this->makeItem($owner);

        Sanctum::actingAs($student);

        $this->patchJson('/api/admin/items/'.$item->id.'/moderate', [
            'reason' => 'Tentativa indevida.',
        ])->assertForbidden();

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'moderated_at' => null,
        ]);
    }

    public function test_autor_nao_pode_definir_campos_de_moderacao_no_proprio_anuncio(): void
    {
        $owner = User::factory()->create();
        $item = $this->makeItem($owner);
        Sanctum::actingAs($owner);

        $this->putJson('/api/items/'.$item->id, [
            'title' => 'Título actualizado',
            'moderated_at' => now()->toDateTimeString(),
            'moderation_reason' => 'Auto-moderação indevida.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['moderated_at', 'moderation_reason']);

        $item->refresh();

        $this->assertNull($item->moderated_at);
        $this->assertSame('Mochila azul', $item->title);
    }
}
