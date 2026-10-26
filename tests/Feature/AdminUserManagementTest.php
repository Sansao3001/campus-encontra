<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_listagem_de_utilizadores_pesquisa_e_filtra_sem_expor_segredos(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Administradora']);
        User::factory()->create(['name' => 'Ana Silva', 'email' => 'ana@example.com']);
        User::factory()->suspended()->create(['name' => 'Bruno Costa', 'email' => 'bruno@example.com']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users?q=Ana&role=student&status=active');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'ana@example.com')
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.remember_token');

        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('token', $response->getContent());

        $this->getJson('/api/admin/users?status=suspended')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'bruno@example.com');
    }

    public function test_administrador_consulta_detalhe_com_contagens(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();

        $student->items()->create([
            'title' => 'Mochila',
            'category' => 'Material',
            'description' => 'Descrição',
            'location' => 'Campus',
            'occurrence_date' => '2026-10-20',
            'type' => 'found',
            'status' => 'open',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users/'.$student->id)
            ->assertOk()
            ->assertJsonPath('id', $student->id)
            ->assertJsonPath('items_count', 1)
            ->assertJsonPath('claims_count', 0)
            ->assertJsonMissingPath('password');
    }

    public function test_administrador_suspende_e_reactiva_conta_com_auditoria(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create(['email' => 'suspender@example.com']);
        $student->createToken('teste');

        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/admin/users/'.$student->id.'/suspend', [
            'reason' => 'Incumprimento das regras da comunidade.',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.suspension_reason', 'Incumprimento das regras da comunidade.');

        $this->assertNotNull($response->json('user.suspended_at'));

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'suspension_reason' => 'Incumprimento das regras da comunidade.',
        ]);
        $this->assertSame(0, $student->tokens()->count());
        $this->assertDatabaseHas('admin_actions', [
            'admin_id' => $admin->id,
            'action' => 'user.suspend',
            'target_type' => 'user',
            'target_id' => $student->id,
        ]);

        $this->postJson('/api/login', [
            'email' => 'suspender@example.com',
            'password' => 'password',
        ])->assertForbidden();

        $this->patchJson('/api/admin/users/'.$student->id.'/reactivate')
            ->assertOk()
            ->assertJsonPath('user.suspended_at', null)
            ->assertJsonPath('user.suspension_reason', null);

        $this->assertDatabaseHas('admin_actions', [
            'admin_id' => $admin->id,
            'action' => 'user.reactivate',
            'target_id' => $student->id,
        ]);

        $this->postJson('/api/login', [
            'email' => 'suspender@example.com',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_administrador_nao_suspende_a_propria_conta(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/users/'.$admin->id.'/suspend', [
            'reason' => 'Tentativa indevida.',
        ])->assertStatus(409);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'suspended_at' => null,
        ]);
    }

    public function test_operacoes_repetidas_sao_rejeitadas(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/users/'.$student->id.'/reactivate')
            ->assertStatus(409);

        $this->patchJson('/api/admin/users/'.$student->id.'/suspend', [
            'reason' => 'Razão válida.',
        ])->assertOk();

        $this->patchJson('/api/admin/users/'.$student->id.'/suspend', [
            'reason' => 'Outra razão.',
        ])->assertStatus(409);
    }

    public function test_suspensao_exige_razao_valida(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/users/'.$student->id.'/suspend', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->patchJson('/api/admin/users/'.$student->id.'/suspend', [
            'reason' => 'x',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_administrador_altera_papeis_com_auditoria(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/users/'.$student->id.'/role', [
            'role' => 'admin',
        ])->assertOk()
            ->assertJsonPath('user.role', 'admin');

        $this->assertDatabaseHas('admin_actions', [
            'admin_id' => $admin->id,
            'action' => 'user.role_change',
            'target_id' => $student->id,
        ]);

        $this->patchJson('/api/admin/users/'.$student->id.'/role', [
            'role' => 'student',
        ])->assertOk()
            ->assertJsonPath('user.role', 'student');
    }

    public function test_administrador_nao_altera_o_proprio_papel(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/users/'.$admin->id.'/role', [
            'role' => 'student',
        ])->assertStatus(409);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
        ]);
    }

    public function test_papel_invalido_e_rejeitado(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/users/'.$student->id.'/role', [
            'role' => 'superadmin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'role' => 'student',
        ]);
    }

    public function test_estudante_nao_pode_suspender_nem_alterar_papeis(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($student);

        $this->patchJson('/api/admin/users/'.$other->id.'/suspend', [
            'reason' => 'Tentativa indevida.',
        ])->assertForbidden();

        $this->patchJson('/api/admin/users/'.$other->id.'/role', [
            'role' => 'admin',
        ])->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $other->id,
            'role' => 'student',
            'suspended_at' => null,
        ]);
    }

    public function test_estudante_suspenso_nao_reutiliza_token_antigo(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $token = $student->createToken('teste')->plainTextToken;

        Sanctum::actingAs($admin);
        $this->patchJson('/api/admin/users/'.$student->id.'/suspend', [
            'reason' => 'Suspensão de teste.',
        ])->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/my-items')
            ->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame(
            $admin->id,
            (int) AdminAction::where('action', 'user.suspend')->value('admin_id')
        );
    }
}
