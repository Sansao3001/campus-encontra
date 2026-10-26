<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_visitante_e_estudante_nao_acedem_as_configuracoes_administrativas(): void
    {
        $this->getJson('/api/admin/settings')->assertUnauthorized();

        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $this->getJson('/api/admin/settings')->assertForbidden();
        $this->putJson('/api/admin/settings', [
            'settings' => ['general.site_name' => 'Campus Alterado'],
        ])->assertForbidden();
    }

    public function test_administrador_consulta_as_configuracoes_sem_segredos(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/settings');

        $response->assertOk()
            ->assertJsonPath('public.site_name', 'CampusEncontra')
            ->assertJsonPath('groups.0.key', 'general')
            ->assertJsonStructure([
                'groups' => [
                    '*' => ['key', 'label', 'description', 'settings'],
                ],
                'public' => [
                    'site_name',
                    'site_description',
                    'institution',
                    'academic_year',
                    'timezone',
                    'date_format',
                    'categories',
                    'max_photo_kb',
                ],
                'updated_at',
            ]);

        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('APP_KEY', $response->getContent());
        $this->assertStringNotContainsString('MAIL_PASSWORD', $response->getContent());
    }

    public function test_configuracoes_sao_persistidas_validadas_e_auditadas(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->putJson('/api/admin/settings', [
            'settings' => [
                'general.site_name' => 'CampusEncontra Moçambique',
                'general.site_description' => 'A comunidade universitária encontra objectos.',
                'general.institution' => 'Universidade Eduardo Mondlane',
                'general.academic_year' => '2026/2027',
                'general.timezone' => 'Africa/Maputo',
                'general.date_format' => 'd/m/Y',
                'operations.categories' => ['Electrónica', 'Documentos', 'Chaves'],
                'operations.max_photo_kb' => 2048,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('public.site_name', 'CampusEncontra Moçambique')
            ->assertJsonPath('public.max_photo_kb', 2048)
            ->assertJsonPath('public.categories.1', 'Documentos');

        $this->assertDatabaseHas('settings', [
            'key' => 'general.site_name',
            'value' => 'CampusEncontra Moçambique',
            'updated_by_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'operations.max_photo_kb',
            'value' => '2048',
        ]);

        $this->assertDatabaseHas('admin_actions', [
            'admin_id' => $admin->id,
            'action' => 'settings.update',
            'target_type' => 'settings',
        ]);

        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.site_name', 'CampusEncontra Moçambique')
            ->assertJsonPath('data.max_photo_kb', 2048);
    }

    public function test_configuracoes_rejeitam_chaves_e_valores_invalidos(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/settings', [
            'settings' => ['general.unknown' => 'x'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('settings');

        $this->putJson('/api/admin/settings', [
            'settings' => [
                'general.site_name' => 'AB',
                'general.timezone' => 'Planeta/Inventado',
                'operations.max_photo_kb' => 100,
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'general.site_name',
                'general.timezone',
                'operations.max_photo_kb',
            ]);

        $this->assertDatabaseCount('admin_actions', 0);
    }

    public function test_actualizacao_parcial_mantem_os_restantes_valores(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/settings', [
            'settings' => ['general.institution' => 'Instituto Superior'],
        ])->assertOk();

        $this->putJson('/api/admin/settings', [
            'settings' => ['general.site_name' => 'CE Académico'],
        ])->assertOk()
            ->assertJsonPath('public.institution', 'Instituto Superior')
            ->assertJsonPath('public.site_name', 'CE Académico');

        $this->assertDatabaseCount('settings', 2);
        $this->assertDatabaseCount('admin_actions', 2);
    }
}
