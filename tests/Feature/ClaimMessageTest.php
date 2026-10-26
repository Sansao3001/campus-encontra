<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClaimMessageTest extends TestCase
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

    private function makeClaim(): array
    {
        $owner = User::factory()->create(['name' => 'Dono']);
        $claimant = User::factory()->create(['name' => 'Reclamante']);
        $item = $owner->items()->create($this->itemData());
        $claim = $item->claims()->create([
            'claimant_id' => $claimant->id,
            'message' => 'Penso que este objecto é meu.',
            'status' => Claim::STATUS_PENDING,
        ]);

        return [$owner, $claimant, $claim];
    }

    public function test_reclamante_envia_e_lista_mensagens_privadas(): void
    {
        [, $claimant, $claim] = $this->makeClaim();

        Sanctum::actingAs($claimant);

        $this->postJson('/api/claims/'.$claim->id.'/messages', [
            'body' => 'Posso passar amanhã na biblioteca?',
        ])->assertCreated()
            ->assertJsonPath('claim_id', $claim->id)
            ->assertJsonPath('body', 'Posso passar amanhã na biblioteca?')
            ->assertJsonPath('sender.id', $claimant->id)
            ->assertJsonPath('sender.name', 'Reclamante')
            ->assertJsonMissingPath('sender.email');

        $this->getJson('/api/claims/'.$claim->id.'/messages')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.sender.name', 'Reclamante')
            ->assertJsonMissingPath('0.sender.email');
    }

    public function test_dono_responde_a_mensagem(): void
    {
        [$owner, , $claim] = $this->makeClaim();

        Sanctum::actingAs($owner);

        $this->postJson('/api/claims/'.$claim->id.'/messages', [
            'body' => 'Sim, combina comigo à porta.',
        ])->assertCreated()
            ->assertJsonPath('sender.id', $owner->id);
    }

    public function test_terceiro_nao_le_nem_escreve_mensagens(): void
    {
        [, , $claim] = $this->makeClaim();
        $outsider = User::factory()->create();

        Sanctum::actingAs($outsider);

        $this->getJson('/api/claims/'.$claim->id.'/messages')->assertForbidden();
        $this->postJson('/api/claims/'.$claim->id.'/messages', [
            'body' => 'Intromissão.',
        ])->assertForbidden();

        $this->assertDatabaseCount('claim_messages', 0);
    }

    public function test_mensagem_vazia_ou_demasiado_longa_e_rejeitada(): void
    {
        [, $claimant, $claim] = $this->makeClaim();

        Sanctum::actingAs($claimant);

        $this->postJson('/api/claims/'.$claim->id.'/messages', [
            'body' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors('body');

        $this->postJson('/api/claims/'.$claim->id.'/messages', [
            'body' => str_repeat('a', 2001),
        ])->assertUnprocessable()->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('claim_messages', 0);
    }

    public function test_reclamacao_cancelada_nao_aceita_novas_mensagens(): void
    {
        [, $claimant, $claim] = $this->makeClaim();
        $claim->update(['status' => Claim::STATUS_CANCELLED]);

        Sanctum::actingAs($claimant);

        $this->postJson('/api/claims/'.$claim->id.'/messages', [
            'body' => 'Mensagem tardia.',
        ])->assertStatus(409);

        $this->getJson('/api/claims/'.$claim->id.'/messages')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_visitante_nao_accede_as_mensagens(): void
    {
        [, , $claim] = $this->makeClaim();

        $this->getJson('/api/claims/'.$claim->id.'/messages')->assertUnauthorized();
        $this->postJson('/api/claims/'.$claim->id.'/messages', [
            'body' => 'Sem sessão.',
        ])->assertUnauthorized();
    }
}
