<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_nao_accede_a_nenhuma_rota_administrativa(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
        $this->getJson('/api/admin/users')->assertUnauthorized();
        $this->getJson('/api/admin/items')->assertUnauthorized();
        $this->getJson('/api/admin/claims')->assertUnauthorized();
        $this->getJson('/api/admin/reports')->assertUnauthorized();
        $this->getJson('/api/admin/audit-logs')->assertUnauthorized();
    }

    public function test_estudante_nao_accede_as_apis_administrativas(): void
    {
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $routes = [
            ['get', '/api/admin/dashboard'],
            ['get', '/api/admin/users'],
            ['get', '/api/admin/items'],
            ['get', '/api/admin/claims'],
            ['get', '/api/admin/reports'],
            ['get', '/api/admin/audit-logs'],
            ['patch', '/api/admin/users/'.$student->id.'/role'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->{$method.'Json'}($uri)->assertForbidden();
        }
    }

    public function test_administrador_accede_as_apis_administrativas(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')->assertOk();
        $this->getJson('/api/admin/users')->assertOk();
        $this->getJson('/api/admin/items')->assertOk();
        $this->getJson('/api/admin/claims')->assertOk();
        $this->getJson('/api/admin/reports')->assertOk();
        $this->getJson('/api/admin/audit-logs')->assertOk();
    }

    public function test_administrador_suspenso_nao_accede_com_token_antigo(): void
    {
        $admin = User::factory()->admin()->suspended()->create();
        $token = $admin->createToken('teste')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'A tua conta está suspensa. Contacta a administração do CampusEncontra.'
            );
    }

    public function test_registo_nao_permite_promocao_para_administrador(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Estudante Curioso',
            'email' => 'curioso@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'curioso@example.com',
            'role' => User::ROLE_STUDENT,
        ]);
    }

    public function test_login_de_conta_suspensa_e_rejeitado_sem_emitir_token(): void
    {
        User::factory()->suspended()->create([
            'email' => 'suspenso@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'suspenso@example.com',
            'password' => 'password123',
        ])->assertForbidden()
            ->assertJsonPath(
                'message',
                'A tua conta está suspensa. Contacta a administração do CampusEncontra.'
            );

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
