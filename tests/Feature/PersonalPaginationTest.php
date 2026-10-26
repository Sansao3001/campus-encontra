<?php

namespace Tests\Feature;

use App\Models\Claim;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PersonalPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(User $user, string $title): Item
    {
        return $user->items()->create([
            'title' => $title,
            'category' => 'Material escolar',
            'description' => 'Descrição do objecto.',
            'location' => 'Biblioteca',
            'occurrence_date' => '2026-10-20',
            'type' => 'found',
        ]);
    }

    public function test_my_items_sem_parametros_mantem_array(): void
    {
        $user = User::factory()->create();
        $this->makeItem($user, 'Objecto 1');
        $this->makeItem($user, 'Objecto 2');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-items');

        $response->assertOk();
        $this->assertIsArray($response->json());
        $response->assertJsonCount(2);
    }

    public function test_my_items_com_parametros_devolve_envelope_paginado(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $index) {
            $this->makeItem($user, 'Objecto '.$index);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/my-items?per_page=2&page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_reclamacoes_enviadas_aceitam_paginacao_opcional(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();

        foreach (range(1, 3) as $index) {
            $item = $this->makeItem($owner, 'Objecto '.$index);
            $item->claims()->create([
                'claimant_id' => $claimant->id,
                'message' => 'Reclamação '.$index,
                'status' => Claim::STATUS_PENDING,
            ]);
        }

        Sanctum::actingAs($claimant);

        $this->getJson('/api/my-claims')
            ->assertOk()
            ->assertJsonCount(3);

        $this->getJson('/api/my-claims?per_page=1&page=3')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_parametros_de_paginacao_invalidos_sao_rejeitados(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/my-items?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');

        $this->getJson('/api/my-claims?page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('page');
    }
}
