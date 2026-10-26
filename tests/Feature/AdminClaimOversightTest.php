<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminClaimOversightTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(User $user, array $overrides = []): Item
    {
        return $user->items()->create(array_merge([
            'title' => 'Telemóvel perdido',
            'category' => 'Electrónica',
            'description' => 'Telemóvel encontrado no átrio.',
            'location' => 'Átrio',
            'occurrence_date' => '2026-10-20',
            'type' => 'found',
            'status' => 'open',
        ], $overrides));
    }

    public function test_administrador_consulta_reclamacoes_com_filtros(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['name' => 'Dono do Objecto']);
        $claimant = User::factory()->create(['name' => 'Reclamante']);
        $item = $this->makeItem($owner);

        Claim::create([
            'item_id' => $item->id,
            'claimant_id' => $claimant->id,
            'message' => 'Descrevo o objecto.',
            'status' => Claim::STATUS_PENDING,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/claims?status=pending&q=Telemóvel');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.item.title', 'Telemóvel perdido')
            ->assertJsonPath('data.0.claimant.name', 'Reclamante')
            ->assertJsonPath('data.0.item.user.name', 'Dono do Objecto')
            ->assertJsonMissingPath('data.0.messages');

        $this->getJson('/api/admin/claims?status=completed')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_administrador_consulta_detalhe_sem_mensagens_privadas(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($owner);

        $claim = Claim::create([
            'item_id' => $item->id,
            'claimant_id' => $claimant->id,
            'message' => 'Mensagem inicial da reclamação.',
            'status' => Claim::STATUS_ACCEPTED,
        ]);

        $claim->messages()->create([
            'sender_id' => $claimant->id,
            'body' => 'CONTEUDO-PRIVADO-NAO-DEVIA-APARECER',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/claims/'.$claim->id);

        $response->assertOk()
            ->assertJsonPath('id', $claim->id)
            ->assertJsonPath('message', 'Mensagem inicial da reclamação.')
            ->assertJsonMissingPath('messages');

        $this->assertStringNotContainsString(
            'CONTEUDO-PRIVADO-NAO-DEVIA-APARECER',
            $response->getContent()
        );

        $this->getJson('/api/claims/'.$claim->id.'/messages')
            ->assertForbidden();
    }

    public function test_estudante_nao_consulta_area_administrativa_de_reclamacoes(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->getJson('/api/admin/claims')->assertForbidden();
    }
}
