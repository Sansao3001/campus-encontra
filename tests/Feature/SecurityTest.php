<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RateLimiter::clear('api');
        RateLimiter::clear('auth');

        parent::tearDown();
    }

    public function test_respostas_da_api_incluem_cabecalhos_de_seguranca(): void
    {
        $response = $this->getJson('/api/items');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_cors_permite_origem_local_configurada(): void
    {
        $response = $this->getJson('/api/items', [
            'Origin' => 'http://localhost:5173',
        ]);

        $response->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_cors_nao_autoriza_origem_desconhecida(): void
    {
        $response = $this->getJson('/api/items', [
            'Origin' => 'https://atacante.example',
        ]);

        $response->assertOk();

        $this->assertNull(
            $response->headers->get('Access-Control-Allow-Origin'),
            'Uma origem desconhecida não deve receber cabeçalhos CORS.'
        );
    }

    public function test_login_e_limitado_apos_cinco_tentativas(): void
    {
        User::factory()->create([
            'email' => 'ana@example.com',
            'password' => 'password123',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/login', [
                'email' => 'ana@example.com',
                'password' => 'password-errada',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'email' => 'ana@example.com',
            'password' => 'password-errada',
        ])->assertStatus(429)
            ->assertJsonPath(
                'message',
                'Demasiados pedidos. Aguarda alguns instantes e tenta novamente.'
            );
    }

    public function test_token_sanctum_expira_ao_fim_do_periodo_configurado(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('teste')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertOk();

        // O valor por omissão é 7 dias (10080 minutos).
        $this->travel(8)->days();

        // Num teste, vários pedidos partilham a mesma instância da aplicação.
        // Esquecemos os guards resolvidos para simular um pedido novo, tal
        // como acontece em produção.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_csp_e_aplicada_as_respostas_web_em_producao(): void
    {
        $this->app['env'] = 'production';

        $response = $this->get('/up');

        $response->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
    }

    public function test_registo_normaliza_email_para_minusculas(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Ana Silva',
            'email' => 'Ana.Silva@Example.COM',
            'password' => 'password123',
        ])->assertCreated()
            ->assertJsonPath('user.email', 'ana.silva@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'ana.silva@example.com',
        ]);
    }
}
