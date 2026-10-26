<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file(public_path('app/index.html'))) {
            $this->markTestSkipped('A build da SPA não está presente em public/app.');
        }
    }

    public function test_raiz_encaminha_para_a_spa_quando_a_build_existe(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('/app/', false)
            ->assertSee('CampusEncontra', false);
    }

    public function test_deep_links_da_spa_devolvem_o_index(): void
    {
        foreach ([
            '/app/',
            '/app/objectos/1',
            '/app/entrar',
            '/app/reclamacoes',
            '/app/rota/que/nao/existe',
        ] as $path) {
            $response = $this->get($path);

            $response
                ->assertOk()
                ->assertHeader('Content-Type', 'text/html; charset=UTF-8');

            $this->assertSame(
                public_path('app/index.html'),
                $response->baseResponse->getFile()->getPathname()
            );
        }
    }

    public function test_rotas_desconhecidas_fora_da_spa_continuam_a_devolver_404(): void
    {
        $this->get('/rota-inexistente')->assertNotFound();
        $this->get('/api/rota-inexistente', ['Accept' => 'application/json'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Recurso não encontrado.');
    }
}
