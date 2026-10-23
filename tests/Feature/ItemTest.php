<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItemTest extends TestCase
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

    private function makeItem(User $user, array $overrides = []): Item
    {
        return $user->items()->create($this->itemData($overrides));
    }

    public function test_listagem_publica_nao_expoe_email_do_autor(): void
    {
        $user = User::factory()->create(['name' => 'Autor Público']);
        $item = $this->makeItem($user);

        $response = $this->getJson('/api/items');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('data.0.user.id', $user->id)
            ->assertJsonPath('data.0.user.name', 'Autor Público')
            ->assertJsonMissingPath('data.0.user.email')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ]);

        $this->assertIsArray($response->json('data'));
        $this->assertEqualsCanonicalizing(
            ['id', 'name'],
            array_keys($response->json('data.0.user'))
        );
    }

    public function test_detalhe_publico_nao_expoe_email_do_autor(): void
    {
        $user = User::factory()->create(['name' => 'Autor Público']);
        $item = $this->makeItem($user);

        $response = $this->getJson('/api/items/'.$item->id);

        $response->assertOk()
            ->assertJsonPath('id', $item->id)
            ->assertJsonPath('user.name', 'Autor Público')
            ->assertJsonMissingPath('user.email');

        $this->assertEqualsCanonicalizing(
            ['id', 'name'],
            array_keys($response->json('user'))
        );
    }

    public function test_listagem_pessoal_devolve_apenas_itens_do_utilizador_autenticado(): void
    {
        $user = User::factory()->create(['name' => 'Dono dos Objectos']);
        $otherUser = User::factory()->create();
        $ownItem = $this->makeItem($user, ['title' => 'Objecto do dono']);
        $this->makeItem($otherUser, ['title' => 'Objecto alheio']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-items');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $ownItem->id)
            ->assertJsonPath('0.title', 'Objecto do dono')
            ->assertJsonPath('0.user.name', 'Dono dos Objectos')
            ->assertJsonMissingPath('0.user.email');
    }

    public function test_listagem_pessoal_vazia_devolve_array(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-items');

        $response->assertOk();
        $this->assertIsArray($response->json());
        $response->assertJsonCount(0);
    }

    public function test_visitante_nao_acessa_listagem_pessoal(): void
    {
        $this->getJson('/api/my-items')->assertUnauthorized();
    }

    public function test_item_eliminado_desaparece_da_listagem_pessoal(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem($user);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/items/'.$item->id)->assertOk();

        $this->getJson('/api/my-items')
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_visitante_nao_cria_item(): void
    {
        $this->postJson('/api/items', $this->itemData())
            ->assertUnauthorized();

        $this->assertDatabaseCount('items', 0);
    }

    public function test_utilizador_autenticado_cria_item_como_proprio_autor(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', $this->itemData());

        $response->assertCreated()
            ->assertJsonPath('title', 'Mochila azul')
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('status', 'open')
            ->assertJsonMissingPath('user.email');

        $this->assertDatabaseHas('items', [
            'user_id' => $user->id,
            'title' => 'Mochila azul',
            'status' => 'open',
        ]);
    }

    public function test_token_sanctum_real_cria_item(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('teste')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/items', $this->itemData());

        $response->assertCreated()
            ->assertJsonPath('user_id', $user->id);
    }

    public function test_cliente_nao_pode_atribuir_user_id(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', $this->itemData([
            'user_id' => $otherUser->id,
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('user_id')
            ->assertJsonPath('errors.user_id.0', 'O autor do objecto é definido pelo servidor.');

        $this->assertDatabaseCount('items', 0);
    }

    public function test_cliente_nao_pode_criar_item_com_estado_returned(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', $this->itemData([
            'status' => 'returned',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('status')
            ->assertJsonPath('errors.status.0', 'O estado do objecto é definido pelo servidor.');

        $this->assertDatabaseCount('items', 0);
    }

    public function test_criacao_valida_campos_obrigatorios(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'category',
                'description',
                'location',
                'occurrence_date',
                'type',
            ])
            ->assertJsonPath('errors.title.0', 'Indica o título do objecto.')
            ->assertJsonPath('errors.category.0', 'Indica a categoria do objecto.')
            ->assertJsonPath('errors.location.0', 'Indica o local onde o objecto foi perdido ou encontrado.')
            ->assertJsonPath('errors.occurrence_date.0', 'Indica a data da ocorrência.')
            ->assertJsonPath('errors.type.0', 'Indica se o objecto foi perdido ou encontrado.');
    }

    public function test_tipo_invalido_e_rejeitado(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/items', $this->itemData([
            'type' => 'desconhecido',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('type')
            ->assertJsonPath('errors.type.0', 'O tipo deve ser "perdido" ou "encontrado".');
    }

    public function test_dono_actualiza_item_sem_alterar_estado(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem($user);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/items/'.$item->id, [
            'title' => 'Mochila azul actualizada',
        ]);

        $response->assertOk()
            ->assertJsonPath('title', 'Mochila azul actualizada')
            ->assertJsonPath('status', 'open');

        $item->refresh();

        $this->assertSame('Mochila azul actualizada', $item->title);
        $this->assertSame('open', $item->status);
    }

    public function test_actualizacao_devolve_mensagens_de_validacao_em_portugues(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem($user);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/items/'.$item->id, [
            'title' => '',
            'type' => 'outro',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'type'])
            ->assertJsonPath('errors.title.0', 'Indica o título do objecto.')
            ->assertJsonPath('errors.type.0', 'O tipo deve ser "perdido" ou "encontrado".');
    }

    public function test_dono_nao_pode_alterar_estado_directamente(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem($user);

        Sanctum::actingAs($user);

        foreach (['pending', 'returned'] as $status) {
            $response = $this->putJson('/api/items/'.$item->id, [
                'status' => $status,
            ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors('status');

            $item->refresh();

            $this->assertSame('open', $item->status);
        }
    }

    public function test_dono_nao_pode_alterar_user_id(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = $this->makeItem($user);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/items/'.$item->id, [
            'user_id' => $otherUser->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $item->refresh();

        $this->assertSame($user->id, $item->user_id);
    }

    public function test_outro_utilizador_nao_actualiza_item(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = $this->makeItem($owner);

        Sanctum::actingAs($otherUser);

        $response = $this->putJson('/api/items/'.$item->id, [
            'title' => 'Alteração indevida',
        ]);

        $response->assertForbidden();

        $item->refresh();

        $this->assertSame('Mochila azul', $item->title);
    }

    public function test_outro_utilizador_nao_elimina_item(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $item = $this->makeItem($owner);

        Sanctum::actingAs($otherUser);

        $this->deleteJson('/api/items/'.$item->id)
            ->assertForbidden();

        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }

    public function test_token_real_de_outro_utilizador_nao_actualiza_nem_elimina_item(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $token = $otherUser->createToken('teste')->plainTextToken;
        $item = $this->makeItem($owner);

        $this->withToken($token)
            ->putJson('/api/items/'.$item->id, [
                'title' => 'Alteração indevida',
            ])
            ->assertForbidden();

        $this->withToken($token)
            ->deleteJson('/api/items/'.$item->id)
            ->assertForbidden();

        $item->refresh();

        $this->assertSame('Mochila azul', $item->title);
        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }

    public function test_dono_elimina_item(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem($user);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/items/'.$item->id);

        $response->assertOk()
            ->assertJsonPath('message', 'Item eliminado com sucesso.');

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    public function test_visitante_nao_actualiza_nem_elimina_item(): void
    {
        $owner = User::factory()->create();
        $item = $this->makeItem($owner);

        $this->putJson('/api/items/'.$item->id, [
            'title' => 'Alteração indevida',
        ])->assertUnauthorized();

        $this->deleteJson('/api/items/'.$item->id)
            ->assertUnauthorized();

        $this->assertDatabaseHas('items', ['id' => $item->id]);
    }

    public function test_item_em_recuperacao_nao_pode_ser_actualizado(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem($user, ['status' => 'pending']);

        Sanctum::actingAs($user);

        $this->putJson('/api/items/'.$item->id, [
            'title' => 'Alteração durante a recuperação',
        ])->assertStatus(409);

        $item->refresh();
        $this->assertSame('Mochila azul', $item->title);
        $this->assertSame('pending', $item->status);
    }

    public function test_item_com_reclamacao_activa_nao_pode_ser_eliminado(): void
    {
        $user = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($user);
        $item->claims()->create([
            'claimant_id' => $claimant->id,
            'status' => Claim::STATUS_PENDING,
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/items/'.$item->id)->assertStatus(409);

        $this->assertDatabaseHas('items', ['id' => $item->id]);
        $this->assertDatabaseHas('claims', ['item_id' => $item->id]);
    }

    public function test_tipo_nao_pode_ser_alterado_com_reclamacao_activa(): void
    {
        $user = User::factory()->create();
        $claimant = User::factory()->create();
        $item = $this->makeItem($user, ['type' => 'found']);
        $item->claims()->create([
            'claimant_id' => $claimant->id,
            'status' => Claim::STATUS_PENDING,
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/items/'.$item->id, [
            'type' => 'lost',
        ])->assertStatus(409);

        $item->refresh();
        $this->assertSame('found', $item->type);

        $this->putJson('/api/items/'.$item->id, [
            'title' => 'Título actualizado sem mudar o tipo',
        ])->assertOk()
            ->assertJsonPath('title', 'Título actualizado sem mudar o tipo')
            ->assertJsonPath('type', 'found');
    }

    public function test_item_inexistente_devolve_404(): void
    {
        $this->getJson('/api/items/999999')->assertNotFound();
    }
}
