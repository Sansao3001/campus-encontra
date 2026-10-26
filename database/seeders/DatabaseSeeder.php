<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Popula a base de dados apenas em ambientes não produtivos.
     *
     * Nunca cria contas com palavras-passe conhecidas em produção. Para criar
     * um utilizador de demonstração local, define SEED_DEMO_USER=true e
     * SEED_DEMO_PASSWORD no .env antes de executar o seeder.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn(
                'Seeder de demonstração ignorado em produção. Cria utilizadores pelo registo da aplicação.'
            );

            return;
        }

        if (! filter_var(env('SEED_DEMO_USER', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->info(
                'Nenhum utilizador de demonstração criado. Define SEED_DEMO_USER=true e SEED_DEMO_PASSWORD no .env para o criar.'
            );

            return;
        }

        $password = (string) env('SEED_DEMO_PASSWORD', '');

        if (Str::length($password) < 8) {
            $this->command?->warn(
                'SEED_DEMO_PASSWORD deve ter pelo menos 8 caracteres. Utilizador de demonstração não criado.'
            );

            return;
        }

        $email = Str::lower((string) env('SEED_DEMO_EMAIL', 'demo@campus-encontra.test'));

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) env('SEED_DEMO_NAME', 'Utilizador de demonstração'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info(
            'Utilizador de demonstração disponível: '.$user->email.' (apenas ambiente local).'
        );
    }
}
