<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItemPhotoTest extends TestCase
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

    public function test_dono_cria_item_com_fotografia(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/items', $this->itemData([
            'photo' => UploadedFile::fake()->image('mochila.jpg', 800, 600),
        ]), ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('has_photo', true)
            ->assertJsonPath('user_id', $user->id);

        $item = Item::firstOrFail();

        $this->assertNotNull($item->photo_path);
        $this->assertNotSame('', $response->json('photo_url'));
        $this->assertArrayNotHasKey('photo_path', $response->json());

        Storage::disk('public')->assertExists($item->photo_path);
    }

    public function test_rejeita_ficheiro_que_nao_e_imagem(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/items', $this->itemData([
            'photo' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        $this->assertDatabaseCount('items', 0);
    }

    public function test_rejeita_fotografia_com_mais_de_cinco_megabytes(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/items', $this->itemData([
            'photo' => UploadedFile::fake()->image('grande.jpg')->size(6000),
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        $this->assertDatabaseCount('items', 0);
    }

    public function test_cliente_nao_pode_indicar_photo_path_arbitrario(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->post('/api/items', $this->itemData([
            'photo_path' => '../../etc/passwd',
        ]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo_path');
    }

    public function test_dono_substitui_fotografia_e_antiga_e_apagada(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $item = $this->makeItem($user);

        Sanctum::actingAs($user);

        $this->post('/api/items/'.$item->id, [
            '_method' => 'PUT',
            'photo' => UploadedFile::fake()->image('primeira.jpg', 600, 400),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('has_photo', true);

        $item->refresh();
        $firstPath = $item->photo_path;

        Storage::disk('public')->assertExists($firstPath);

        $this->post('/api/items/'.$item->id, [
            '_method' => 'PUT',
            'photo' => UploadedFile::fake()->image('segunda.jpg', 600, 400),
        ], ['Accept' => 'application/json'])
            ->assertOk();

        $item->refresh();
        $secondPath = $item->photo_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_dono_remove_fotografia(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $item = $this->makeItem($user);
        $path = UploadedFile::fake()->image('foto.jpg', 400, 300)
            ->store('items', 'public');
        $item->update(['photo_path' => $path]);

        Sanctum::actingAs($user);

        $response = $this->post('/api/items/'.$item->id, [
            '_method' => 'PUT',
            'remove_photo' => 1,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('has_photo', false)
            ->assertJsonPath('photo_url', null);

        $this->assertArrayNotHasKey('photo_path', $response->json());

        Storage::disk('public')->assertMissing($path);
    }

    public function test_outro_utilizador_nao_altera_fotografia(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $item = $this->makeItem($owner);

        Sanctum::actingAs($other);

        $this->post('/api/items/'.$item->id, [
            '_method' => 'PUT',
            'photo' => UploadedFile::fake()->image('foto.jpg', 400, 300),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $item->refresh();
        $this->assertNull($item->photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_eliminar_item_apaga_o_ficheiro_da_fotografia(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $item = $this->makeItem($user);
        $path = UploadedFile::fake()->image('foto.jpg', 400, 300)
            ->store('items', 'public');
        $item->update(['photo_path' => $path]);

        Sanctum::actingAs($user);

        $this->deleteJson('/api/items/'.$item->id)->assertOk();

        Storage::disk('public')->assertMissing($path);
    }
}
