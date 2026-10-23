<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClaimTest extends TestCase
{
    use RefreshDatabase;

    private function itemData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Mochila azul',
            'category' => 'Material escolar',
            'description' => 'Mochila encontrada na biblioteca.',
            'location' => 'Biblioteca',
            'occurrence_date' => '2026-10-20',
            'type' => 'found',
        ], $overrides);
    }

    private function makeItem(User $owner, array $overrides = []): Item
    {
        return $owner->items()->create($this->itemData($overrides));
    }

    private function makeClaim(Item $item, User $claimant, array $overrides = []): Claim
    {
        return $item->claims()->create(array_merge([
            'claimant_id' => $claimant->id,
            'message' => 'Penso que este objecto é meu.',
            'status' => Claim::STATUS_PENDING,
        ], $overrides));
    }

    public function test_visitante_nao_cria_reclamacao(): void
    {
        $owner = User::factory()->create();
        $item = $this->makeItem($owner);

        $this->postJson('/api/items/'.$item->id.'/claims', [
            'message' => 'Tentei reclamar sem sessão.',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('claims', 0);
    }

    public function test_utilizador_autenticado_cria_reclamacao_para_objecto_alheio(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner);

        Sanctum::actingAs($claimant);

        $response = $this->postJson('/api/items/'.$item->id.'/claims', [
            'message' => 'Encontrei esta mochila.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('item_id', $item->id)
            ->assertJsonPath('claimant_id', $claimant->id)
            ->assertJsonPath('status', Claim::STATUS_PENDING)
            ->assertJsonPath('item.title', 'Mochila azul')
            ->assertJsonPath('claimant.name', $claimant->name)
            ->assertJsonMissingPath('claimant.email');

        $this->assertDatabaseHas('claims', [
            'item_id' => $item->id,
            'claimant_id' => $claimant->id,
            'status' => Claim::STATUS_PENDING,
        ]);
    }

    public function test_dono_nao_pode_reclamar_o_proprio_objecto(): void
    {
        $owner = User::factory()->create();
        $item = $this->makeItem($owner);

        Sanctum::actingAs($owner);

        $this->postJson('/api/items/'.$item->id.'/claims', [
            'message' => 'Tentativa indevida.',
        ])->assertForbidden();

        $this->assertDatabaseCount('claims', 0);
    }

    public function test_reclamacao_duplicada_activa_e_impedida(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner);

        $this->makeClaim($item, $claimant);

        Sanctum::actingAs($claimant);

        $response = $this->postJson('/api/items/'.$item->id.'/claims', [
            'message' => 'Segunda tentativa.',
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('claims', 1);
    }

    public function test_reclamacao_em_objecto_indisponivel_e_impedida(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner, ['status' => 'pending']);

        Sanctum::actingAs($claimant);

        $this->postJson('/api/items/'.$item->id.'/claims', [
            'message' => 'Tentativa num objecto em recuperação.',
        ])->assertStatus(409);

        $this->assertDatabaseCount('claims', 0);
    }

    public function test_listagem_enviada_devolve_apenas_reclamacoes_do_utilizador(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $otherClaimant = User::factory()->create();
        $item = $this->makeItem($owner);
        $otherItem = $this->makeItem($owner, ['title' => 'Outro objecto']);

        $ownClaim = $this->makeClaim($item, $claimant);
        $this->makeClaim($otherItem, $otherClaimant);

        Sanctum::actingAs($claimant);

        $response = $this->getJson('/api/my-claims');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $ownClaim->id)
            ->assertJsonPath('0.item.title', 'Mochila azul')
            ->assertJsonPath('0.claimant.name', $claimant->name)
            ->assertJsonMissingPath('0.claimant.email');
    }

    public function test_listagem_recebida_devolve_apenas_reclamacoes_dos_objectos_do_dono(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner);
        $otherItem = $this->makeItem($otherOwner);

        $ownClaim = $this->makeClaim($item, $claimant);
        $this->makeClaim($otherItem, $claimant);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/received-claims');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $ownClaim->id)
            ->assertJsonPath('0.item.title', 'Mochila azul')
            ->assertJsonPath('0.claimant.name', $claimant->name)
            ->assertJsonMissingPath('0.claimant.email');
    }

    public function test_dono_lista_reclamacoes_de_um_objecto(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner);
        $claim = $this->makeClaim($item, $claimant);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/items/'.$item->id.'/claims');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $claim->id)
            ->assertJsonPath('0.claimant.name', $claimant->name)
            ->assertJsonMissingPath('0.claimant.email');
    }

    public function test_outro_utilizador_nao_lista_reclamacoes_do_objecto(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = $this->makeItem($owner);
        $this->makeClaim($item, $otherUser);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/items/'.$item->id.'/claims')->assertForbidden();
    }

    public function test_reclamante_cancela_reclamacao_pendente(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner);
        $claim = $this->makeClaim($item, $claimant);

        Sanctum::actingAs($claimant);

        $response = $this->patchJson('/api/claims/'.$claim->id.'/cancel');

        $response->assertOk()
            ->assertJsonPath('status', Claim::STATUS_CANCELLED);

        $claim->refresh();
        $item->refresh();

        $this->assertSame(Claim::STATUS_CANCELLED, $claim->status);
        $this->assertNotNull($claim->decided_at);
        $this->assertSame('open', $item->status);
    }

    public function test_outro_utilizador_nao_cancela_reclamacao(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = $this->makeItem($owner);
        $claim = $this->makeClaim($item, $claimant);

        Sanctum::actingAs($otherUser);

        $this->patchJson('/api/claims/'.$claim->id.'/cancel')->assertForbidden();

        $claim->refresh();
        $this->assertSame(Claim::STATUS_PENDING, $claim->status);
    }

    public function test_nao_cancela_reclamacao_ja_processada(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner);
        $claim = $this->makeClaim($item, $claimant, [
            'status' => Claim::STATUS_ACCEPTED,
        ]);

        Sanctum::actingAs($claimant);

        $this->patchJson('/api/claims/'.$claim->id.'/cancel')->assertStatus(409);
    }

    public function test_dono_aceita_reclamacao_e_inicia_recuperacao(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $otherClaimant = User::factory()->create();
        $item = $this->makeItem($owner);
        $claim = $this->makeClaim($item, $claimant);
        $otherClaim = $this->makeClaim($item, $otherClaimant);

        Sanctum::actingAs($owner);

        $response = $this->patchJson('/api/claims/'.$claim->id.'/accept');

        $response->assertOk()
            ->assertJsonPath('status', Claim::STATUS_ACCEPTED)
            ->assertJsonPath('completed_at', null);

        $claim->refresh();
        $otherClaim->refresh();
        $item->refresh();

        $this->assertSame(Claim::STATUS_ACCEPTED, $claim->status);
        $this->assertNotNull($claim->decided_at);
        $this->assertSame(Claim::STATUS_REJECTED, $otherClaim->status);
        $this->assertSame('pending', $item->status);
        $this->assertNotSame('returned', $item->status);
    }

    public function test_outro_utilizador_nao_aceita_reclamacao(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = $this->makeItem($owner);
        $claim = $this->makeClaim($item, $claimant);

        Sanctum::actingAs($otherUser);

        $this->patchJson('/api/claims/'.$claim->id.'/accept')->assertForbidden();

        $claim->refresh();
        $item->refresh();

        $this->assertSame(Claim::STATUS_PENDING, $claim->status);
        $this->assertSame('open', $item->status);
    }

    public function test_nao_aceita_reclamacao_ja_processada(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner);
        $claim = $this->makeClaim($item, $claimant, [
            'status' => Claim::STATUS_REJECTED,
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson('/api/claims/'.$claim->id.'/accept')->assertStatus(409);
    }

    public function test_nao_aceita_reclamacao_com_objecto_indisponivel(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner, ['status' => 'pending']);
        $claim = $this->makeClaim($item, $claimant);

        Sanctum::actingAs($owner);

        $this->patchJson('/api/claims/'.$claim->id.'/accept')->assertStatus(409);
    }

    public function test_reclamacao_rejeitada_cancelada_ou_concluida_nao_pode_ser_aceite(): void
    {
        $owner = User::factory()->create();
        $item = $this->makeItem($owner);
        $claims = [];

        foreach ([
            Claim::STATUS_REJECTED,
            Claim::STATUS_CANCELLED,
            Claim::STATUS_COMPLETED,
        ] as $status) {
            $claimant = User::factory()->create();
            $claims[$status] = $this->makeClaim($item, $claimant, ['status' => $status]);
        }

        Sanctum::actingAs($owner);

        foreach ($claims as $status => $claim) {
            $this->patchJson('/api/claims/'.$claim->id.'/accept')->assertStatus(409);

            $claim->refresh();
            $this->assertSame($status, $claim->status);
        }

        $item->refresh();
        $this->assertSame('open', $item->status);
    }

    public function test_nao_aceita_duas_reclamacoes_do_mesmo_objecto(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $otherClaimant = User::factory()->create();
        $item = $this->makeItem($owner);
        $claim = $this->makeClaim($item, $claimant);
        $otherClaim = $this->makeClaim($item, $otherClaimant);

        Sanctum::actingAs($owner);

        $this->patchJson('/api/claims/'.$claim->id.'/accept')->assertOk();
        $this->patchJson('/api/claims/'.$otherClaim->id.'/accept')->assertStatus(409);

        $claim->refresh();
        $otherClaim->refresh();
        $item->refresh();

        $this->assertSame(Claim::STATUS_ACCEPTED, $claim->status);
        $this->assertSame(Claim::STATUS_REJECTED, $otherClaim->status);
        $this->assertSame('pending', $item->status);
    }

    public function test_nao_aceita_a_mesma_reclamacao_duas_vezes(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner);
        $claim = $this->makeClaim($item, $claimant);

        Sanctum::actingAs($owner);

        $this->patchJson('/api/claims/'.$claim->id.'/accept')->assertOk();
        $this->patchJson('/api/claims/'.$claim->id.'/accept')->assertStatus(409);

        $claim->refresh();
        $item->refresh();

        $this->assertSame(Claim::STATUS_ACCEPTED, $claim->status);
        $this->assertSame('pending', $item->status);
    }

    public function test_dono_rejeita_reclamacao(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner);
        $claim = $this->makeClaim($item, $claimant);

        Sanctum::actingAs($owner);

        $response = $this->patchJson('/api/claims/'.$claim->id.'/reject');

        $response->assertOk()
            ->assertJsonPath('status', Claim::STATUS_REJECTED);

        $claim->refresh();
        $item->refresh();

        $this->assertSame(Claim::STATUS_REJECTED, $claim->status);
        $this->assertNotNull($claim->decided_at);
        $this->assertSame('open', $item->status);
    }

    public function test_outro_utilizador_nao_rejeita_reclamacao(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = $this->makeItem($owner);
        $claim = $this->makeClaim($item, $claimant);

        Sanctum::actingAs($otherUser);

        $this->patchJson('/api/claims/'.$claim->id.'/reject')->assertForbidden();

        $claim->refresh();
        $this->assertSame(Claim::STATUS_PENDING, $claim->status);
    }

    public function test_nao_rejeita_reclamacao_ja_processada(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner);
        $claim = $this->makeClaim($item, $claimant, [
            'status' => Claim::STATUS_CANCELLED,
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson('/api/claims/'.$claim->id.'/reject')->assertStatus(409);
    }

    public function test_dono_confirma_devolucao_apos_aceitacao(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner, ['status' => 'pending']);
        $claim = $this->makeClaim($item, $claimant, [
            'status' => Claim::STATUS_ACCEPTED,
            'decided_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->patchJson('/api/claims/'.$claim->id.'/confirm-return');

        $response->assertOk()
            ->assertJsonPath('status', Claim::STATUS_COMPLETED);

        $claim->refresh();
        $item->refresh();

        $this->assertSame(Claim::STATUS_COMPLETED, $claim->status);
        $this->assertNotNull($claim->completed_at);
        $this->assertSame('returned', $item->status);
    }

    public function test_reclamante_nao_confirma_devolucao(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner, ['status' => 'pending']);
        $claim = $this->makeClaim($item, $claimant, [
            'status' => Claim::STATUS_ACCEPTED,
        ]);

        Sanctum::actingAs($claimant);

        $this->patchJson('/api/claims/'.$claim->id.'/confirm-return')->assertForbidden();

        $claim->refresh();
        $item->refresh();

        $this->assertSame(Claim::STATUS_ACCEPTED, $claim->status);
        $this->assertSame('pending', $item->status);
    }

    public function test_nao_confirma_devolucao_antes_de_aceitacao(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner);
        $claim = $this->makeClaim($item, $claimant);

        Sanctum::actingAs($owner);

        $this->patchJson('/api/claims/'.$claim->id.'/confirm-return')->assertStatus(409);
    }

    public function test_nao_confirma_devolucao_duas_vezes(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner, ['status' => 'pending']);
        $claim = $this->makeClaim($item, $claimant, [
            'status' => Claim::STATUS_ACCEPTED,
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson('/api/claims/'.$claim->id.'/confirm-return')->assertOk();
        $this->patchJson('/api/claims/'.$claim->id.'/confirm-return')->assertStatus(409);
    }

    public function test_fluxo_de_objecto_perdido_nao_marca_devolvido_na_aceitacao(): void
    {
        $owner = User::factory()->create();
        $finder = User::factory()->create();
        $item = $this->makeItem($owner, ['type' => 'lost', 'title' => 'Chaves perdidas']);
        $claim = $this->makeClaim($item, $finder, [
            'message' => 'Encontrei estas chaves na cantina.',
        ]);

        Sanctum::actingAs($owner);

        $this->patchJson('/api/claims/'.$claim->id.'/accept')
            ->assertOk()
            ->assertJsonPath('status', Claim::STATUS_ACCEPTED);

        $item->refresh();
        $this->assertSame('pending', $item->status);

        $this->patchJson('/api/claims/'.$claim->id.'/confirm-return')
            ->assertOk()
            ->assertJsonPath('status', Claim::STATUS_COMPLETED);

        $item->refresh();
        $this->assertSame('returned', $item->status);
    }

    public function test_fluxo_de_objecto_encontrado_exige_confirmacao_final(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner, ['type' => 'found']);
        $claim = $this->makeClaim($item, $claimant);

        Sanctum::actingAs($owner);

        $this->patchJson('/api/claims/'.$claim->id.'/accept')
            ->assertOk()
            ->assertJsonPath('status', Claim::STATUS_ACCEPTED);

        $item->refresh();
        $this->assertSame('pending', $item->status);

        $this->patchJson('/api/claims/'.$claim->id.'/confirm-return')
            ->assertOk()
            ->assertJsonPath('status', Claim::STATUS_COMPLETED);

        $item->refresh();
        $this->assertSame('returned', $item->status);
    }
}
