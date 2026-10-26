<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCommandAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_promove_conta_existente_sem_criar_credenciais(): void
    {
        $student = User::factory()->create(['email' => 'futuro-admin@example.com']);

        $this->artisan('admin:promote', [
            'email' => 'futuro-admin@example.com',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'role' => User::ROLE_ADMIN,
        ]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('admin_actions', [
            'admin_id' => $student->id,
            'action' => 'user.role_change',
            'target_type' => 'user',
            'target_id' => $student->id,
        ]);
    }

    public function test_comando_nao_cria_conta_inexistente(): void
    {
        $this->artisan('admin:promote', [
            'email' => 'inexistente@example.com',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_comando_impede_remover_o_unico_administrador(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'unico-admin@example.com']);

        $this->artisan('admin:promote', [
            'email' => $admin->email,
            '--demote' => true,
        ])->assertFailed();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_comando_permite_remover_administrador_quando_existe_outro(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'admin-um@example.com']);
        User::factory()->admin()->create(['email' => 'admin-dois@example.com']);

        $this->artisan('admin:promote', [
            'email' => $admin->email,
            '--demote' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => User::ROLE_STUDENT,
        ]);
    }

    public function test_historico_administrativo_e_consultavel_e_nao_expoe_segredos(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin Auditora']);
        $owner = User::factory()->create();
        $item = $owner->items()->create([
            'title' => 'Objecto para moderar',
            'category' => 'Outros',
            'description' => 'Descrição',
            'location' => 'Campus',
            'occurrence_date' => '2026-10-20',
            'type' => 'found',
            'status' => 'open',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/items/'.$item->id.'/moderate', [
            'reason' => 'Conteúdo inadequado para a comunidade.',
        ])->assertOk();

        $response = $this->getJson('/api/admin/audit-logs');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.action', 'item.moderate')
            ->assertJsonPath('data.0.target_type', 'item')
            ->assertJsonPath('data.0.target_id', $item->id)
            ->assertJsonPath('data.0.admin.name', 'Admin Auditora')
            ->assertJsonMissingPath('data.0.admin.email');

        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('token', $response->getContent());

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->getJson('/api/admin/audit-logs')->assertForbidden();
    }

    public function test_registo_de_auditoria_nao_guarda_conteudo_privado(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $item = $owner->items()->create([
            'title' => 'Objecto',
            'category' => 'Outros',
            'description' => 'Descrição',
            'location' => 'Campus',
            'occurrence_date' => '2026-10-20',
            'type' => 'found',
            'status' => 'open',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/items/'.$item->id.'/moderate', [
            'reason' => 'Razão operacional.',
        ])->assertOk();

        $action = AdminAction::firstOrFail();

        $this->assertStringNotContainsString('password', (string) $action->metadata);
        $this->assertStringNotContainsString('token', (string) $action->metadata);
        $this->assertSame('Razão operacional.', $action->reason);
    }
}
