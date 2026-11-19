<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTestPlayer extends Command
{
    protected $signature = 'player:create-test {nickname} {--email=} {--password=}';
    protected $description = 'Cria manualmente um jogador comum de teste sem alterar contas existentes';

    public function handle(): int
    {
        $nickname = strtolower(trim((string) $this->argument('nickname')));
        $email = strtolower(trim((string) ($this->option('email') ?: $nickname.'@test.local')));
        $password = (string) $this->option('password');
        if (!preg_match('/\A[a-z0-9_-]{3,30}\z/', $nickname) || strlen($password) < 12) {
            $this->error('Informe nickname válido e senha com no mínimo 12 caracteres.');
            return self::FAILURE;
        }
        if (User::query()->whereRaw('LOWER(username) = ?', [$nickname])->orWhereRaw('LOWER(email) = ?', [$email])->exists()) {
            $this->warn('A conta ou o e-mail já existe; nenhum dado foi alterado.');
            return self::SUCCESS;
        }
        User::create(['username'=>$nickname,'display_name'=>$nickname,'email'=>$email,'email_verified_at'=>now(),'password'=>Hash::make($password)]);
        $this->info("Jogador de teste @{$nickname} criado sem privilégios administrativos.");
        return self::SUCCESS;
    }
}
