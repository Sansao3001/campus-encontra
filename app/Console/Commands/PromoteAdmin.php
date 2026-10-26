<?php

namespace App\Console\Commands;

use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PromoteAdmin extends Command
{
    protected $signature = 'admin:promote
        {email : Email da conta já existente}
        {--demote : Remove o papel de administrador}
        {--force : Confirma a operação em produção}';

    protected $description = 'Promove uma conta existente a administrador (ou remove o papel com --demote), sem criar credenciais.';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Em produção, confirma a operação com --force.');
            $this->line('Exemplo: php artisan admin:promote admin@exemplo.pt --force');

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) $this->argument('email')));
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error('Não existe nenhuma conta com esse email. Cria a conta normalmente antes de a promover.');

            return self::FAILURE;
        }

        if ($this->option('demote')) {
            return $this->demote($user);
        }

        if ($user->isSuspended()) {
            $this->error('A conta está suspensa. Reactiva-a antes de a promover a administrador.');

            return self::FAILURE;
        }

        if ($user->isAdmin()) {
            $this->info('A conta já é administradora.');

            return self::SUCCESS;
        }

        $user->forceFill(['role' => User::ROLE_ADMIN])->save();

        $admin = User::admins()->active()->orderBy('id')->first();

        if ($admin) {
            AdminAction::record(
                $admin,
                'user.role_change',
                'user',
                $user->id,
                'Promoção via comando artisan admin:promote.',
                ['from' => User::ROLE_STUDENT, 'to' => User::ROLE_ADMIN, 'source' => 'artisan'],
            );
        }

        $this->info("A conta {$user->email} foi promovida a administradora.");

        return self::SUCCESS;
    }

    private function demote(User $user): int
    {
        if (! $user->isAdmin()) {
            $this->info('A conta não é administradora.');

            return self::SUCCESS;
        }

        if (! $user->isSuspended()) {
            $otherActiveAdmins = User::admins()
                ->active()
                ->where('id', '!=', $user->id)
                ->count();

            if ($otherActiveAdmins < 1) {
                $this->error('Não é possível remover o único administrador activo.');

                return self::FAILURE;
            }
        }

        $user->forceFill(['role' => User::ROLE_STUDENT])->save();

        $admin = User::admins()->active()->orderBy('id')->first();

        if ($admin) {
            AdminAction::record(
                $admin,
                'user.role_change',
                'user',
                $user->id,
                'Remoção de papel via comando artisan admin:promote --demote.',
                ['from' => User::ROLE_ADMIN, 'to' => User::ROLE_STUDENT, 'source' => 'artisan'],
            );
        }

        $this->info("O papel de administrador foi removido de {$user->email}.");

        return self::SUCCESS;
    }
}
