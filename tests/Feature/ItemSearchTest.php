<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ItemSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

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

    public function test_listagem_devolve_formato_paginado_com_metadados(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem($user);

        $response = $this->getJson('/api/items');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ]);
    }

    public function test_listagem_sem_filtros_inclui_os_tres_estados(): void
    {
        $user = User::factory()->create();
        $this->makeItem($user, ['title' => 'Em aberto', 'status' => 'open']);
        $this->makeItem($user, ['title' => 'Em recuperação', 'status' => 'pending']);
        $this->makeItem($user, ['title' => 'Devolvido', 'status' => 'returned']);

        $response = $this->getJson('/api/items');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);

        $this->assertEqualsCanonicalizing(
            ['open', 'pending', 'returned'],
            collect($response->json('data'))->pluck('status')->all()
        );
    }

    public function test_pesquisa_encontra_titulo_e_descricao(): void
    {
        $user = User::factory()->create();
        $titleMatch = $this->makeItem($user, [
            'title' => 'Mochila azul',
            'description' => 'Objecto deixado numa sala.',
        ]);
        $descriptionMatch = $this->makeItem($user, [
            'title' => 'Telefone',
            'description' => 'Carteira encontrada no corredor.',
        ]);

        $this->getJson('/api/items?q=mochila')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $titleMatch->id);

        $this->getJson('/api/items?q=carteira')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $descriptionMatch->id);
    }

    public function test_pesquisa_abrange_categoria_localizacao_e_autor(): void
    {
        $author = User::factory()->create(['name' => 'Ana Silva']);
        $item = $this->makeItem($author, [
            'title' => 'Objecto',
            'category' => 'Material escolar',
            'location' => 'Biblioteca Central',
        ]);

        $this->getJson('/api/items?q=material')
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->id);

        $this->getJson('/api/items?q=biblioteca')
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->id);

        $this->getJson('/api/items?q=ana')
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->id);
    }

    public function test_pesquisa_nao_distingue_maiusculas_minusculas(): void
    {
        $user = User::factory()->create();
        $item = $this->makeItem($user, ['title' => 'Mochila Azul']);

        $this->getJson('/api/items?q=MOCHILA')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $item->id);
    }

    public function test_pesquisa_sem_resultados_devolve_lista_vazia(): void
    {
        $user = User::factory()->create();
        $this->makeItem($user);

        $this->getJson('/api/items?q=inexistente')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_filtro_por_tipo(): void
    {
        $user = User::factory()->create();
        $lost = $this->makeItem($user, ['title' => 'Perdido', 'type' => 'lost']);
        $found = $this->makeItem($user, ['title' => 'Encontrado', 'type' => 'found']);

        $this->getJson('/api/items?type=lost')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $lost->id);

        $this->getJson('/api/items?type=found')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $found->id);
    }

    public function test_filtro_por_estado(): void
    {
        $user = User::factory()->create();
        $open = $this->makeItem($user, ['title' => 'Aberto', 'status' => 'open']);
        $pending = $this->makeItem($user, ['title' => 'Pendente', 'status' => 'pending']);
        $returned = $this->makeItem($user, ['title' => 'Devolvido', 'status' => 'returned']);

        $this->getJson('/api/items?status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $open->id);

        $this->getJson('/api/items?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id);

        $this->getJson('/api/items?status=returned')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $returned->id);
    }

    public function test_combinacao_de_pesquisa_e_filtros(): void
    {
        $user = User::factory()->create();
        $target = $this->makeItem($user, [
            'title' => 'Mochila azul',
            'type' => 'found',
            'status' => 'open',
        ]);
        $this->makeItem($user, [
            'title' => 'Mochila azul',
            'description' => 'Objecto diferente.',
            'type' => 'lost',
            'status' => 'open',
        ]);
        $this->makeItem($user, [
            'title' => 'Mochila azul',
            'description' => 'Objecto diferente.',
            'type' => 'found',
            'status' => 'pending',
        ]);
        $this->makeItem($user, [
            'title' => 'Telefone',
            'description' => 'Objecto diferente.',
            'type' => 'found',
            'status' => 'open',
        ]);

        $this->getJson('/api/items?q=mochila&type=found&status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_paginacao_usa_12_por_defeito_e_respeita_per_page(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 15) as $index) {
            $this->makeItem($user, ['title' => 'Objecto '.$index]);
        }

        $default = $this->getJson('/api/items');
        $default->assertOk()
            ->assertJsonCount(12, 'data')
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2);

        $custom = $this->getJson('/api/items?per_page=5&page=2');
        $custom->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_pagina_fora_do_intervalo_devolve_lista_vazia(): void
    {
        $user = User::factory()->create();
        $this->makeItem($user);

        $this->getJson('/api/items?page=99')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_ordenacao_por_defeito_e_mais_recente_primeiro(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-10-20 10:00:00');
        $older = $this->makeItem($user, ['title' => 'Mais antigo']);

        Carbon::setTestNow('2026-10-20 10:01:00');
        $newer = $this->makeItem($user, ['title' => 'Mais recente']);

        $this->getJson('/api/items')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_ordenacao_usa_id_como_desempate_estavel(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-10-20 10:00:00');
        $first = $this->makeItem($user, ['title' => 'Primeiro']);
        $second = $this->makeItem($user, ['title' => 'Segundo']);

        $this->getJson('/api/items')
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.1.id', $first->id);
    }

    public function test_paginacao_preserva_filtros_nos_links(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 13) as $index) {
            $this->makeItem($user, [
                'title' => 'Mochila '.$index,
                'type' => 'found',
            ]);
        }

        $response = $this->getJson('/api/items?q=mochila&type=found&per_page=5');

        $response->assertOk()
            ->assertJsonPath('meta.total', 13);

        $nextLink = $response->json('links.next');

        $this->assertIsString($nextLink);
        $this->assertStringContainsString('q=mochila', $nextLink);
        $this->assertStringContainsString('type=found', $nextLink);
        $this->assertStringContainsString('per_page=5', $nextLink);
    }

    public function test_parametros_invalidos_devolvem_422_com_mensagens_em_portugues(): void
    {
        $cases = [
            ['type=outro', 'type'],
            ['status=outro', 'status'],
            ['page=0', 'page'],
            ['page=texto', 'page'],
            ['per_page=0', 'per_page'],
            ['per_page=51', 'per_page'],
            ['per_page=texto', 'per_page'],
            ['q='.str_repeat('a', 101), 'q'],
        ];

        foreach ($cases as [$query, $field]) {
            $this->getJson('/api/items?'.$query)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        $this->getJson('/api/items?type=outro')
            ->assertJsonPath('errors.type.0', 'O tipo deve ser "perdido" ou "encontrado".');

        $this->getJson('/api/items?status=outro')
            ->assertJsonPath(
                'errors.status.0',
                'O estado deve ser "em aberto", "em recuperação" ou "devolvido".'
            );

        $this->getJson('/api/items?page=0')
            ->assertJsonPath('errors.page.0', 'A página deve ser pelo menos 1.');

        $this->getJson('/api/items?per_page=51')
            ->assertJsonPath(
                'errors.per_page.0',
                'O número de resultados por página não pode ser superior a 50.'
            );

        $this->getJson('/api/items?q='.str_repeat('a', 101))
            ->assertJsonPath(
                'errors.q.0',
                'O termo de pesquisa não pode ter mais de 100 caracteres.'
            );
    }
}
