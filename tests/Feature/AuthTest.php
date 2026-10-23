<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registo_cria_utilizador_e_devolve_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.name', 'Ana Silva')
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
                'token',
            ]);

        $this->assertArrayNotHasKey('password', $response->json('user'));
        $this->assertNotEmpty($response->json('token'));

        $user = User::where('email', 'ana@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_registo_exige_campos_obrigatorios(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password'])
            ->assertJsonPath('errors.name.0', 'Indica o teu nome.')
            ->assertJsonPath('errors.email.0', 'Indica o teu email.')
            ->assertJsonPath('errors.password.0', 'Indica uma palavra-passe.');
    }

    public function test_registo_rejeita_email_duplicado(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Outra Ana',
            'email' => 'ana@example.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email')
            ->assertJsonPath('errors.email.0', 'Já existe uma conta com este email.');
    }

    public function test_registo_rejeita_password_com_menos_de_oito_caracteres(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'password' => '1234567',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_login_devolve_token_com_credenciais_validas(): void
    {
        $user = User::factory()->create([
            'email' => 'ana@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'ana@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonStructure(['message', 'user', 'token']);

        $this->assertArrayNotHasKey('password', $response->json('user'));
        $this->assertNotEmpty($response->json('token'));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);
    }

    public function test_login_rejeita_credenciais_invalidas(): void
    {
        User::factory()->create([
            'email' => 'ana@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'ana@example.com',
            'password' => 'password-errada',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_visitante_nao_acessa_perfil(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_token_invalido_nao_acessa_perfil(): void
    {
        $this->withHeader('Authorization', 'Bearer token-invalido')
            ->getJson('/api/user')
            ->assertUnauthorized();
    }

    public function test_utilizador_autenticado_consulta_perfil(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);

        $this->assertArrayNotHasKey('password', $response->json());
    }

    public function test_token_sanctum_real_autentica_pedido(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('teste')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user');

        $response->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_logout_revoga_apenas_o_token_utilizado(): void
    {
        $user = User::factory()->create();

        $otherToken = $user->createToken('outro-dispositivo')->plainTextToken;
        $usedToken = $user->createToken('teste');
        $usedTokenId = $usedToken->accessToken->id;

        $response = $this->withHeader('Authorization', 'Bearer '.$usedToken->plainTextToken)
            ->postJson('/api/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Sessão terminada com sucesso.');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $usedTokenId]);

        // Limpa o utilizador autenticado em cache no guard para simular
        // pedidos novos com cada um dos tokens.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$usedToken->plainTextToken)
            ->getJson('/api/user')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer '.$otherToken)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_logout_exige_autenticacao(): void
    {
        $this->postJson('/api/logout')->assertUnauthorized();
    }
}
