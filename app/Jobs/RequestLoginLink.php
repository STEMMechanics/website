<?php

namespace App\Jobs;

use App\Mail\UserLogin;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Queue\Queueable;

class RequestLoginLink implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 20;

    public function __construct(public string $login, public array $data = [])
    {
        $this->onQueue('mail');
    }

    public function handle(): void
    {
        $user = User::query()->whereNull('anonymized_at')->where('email', strtolower($this->login))->first();
        if (! $user || ! $user->canUseEmailLogin()) {
            return;
        }
        $token = $user->tokens()->create(['type' => 'login', 'data' => $this->data]);
        SendEmail::dispatch($user->email, new UserLogin($token->id, $user->getName(), $user->email))->onQueue('mail');
    }
}
