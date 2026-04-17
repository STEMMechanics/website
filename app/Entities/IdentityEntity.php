<?php

namespace App\Entities;

use App\Models\User;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use OpenIDConnect\Claims\Traits\WithClaims;
use OpenIDConnect\Entities\Traits\WithCustomPermittedFor;
use OpenIDConnect\Interfaces\IdentityEntityInterface;

class IdentityEntity implements IdentityEntityInterface
{
    use EntityTrait;
    use WithClaims;
    use WithCustomPermittedFor;

    protected User $user;

    public function setIdentifier($identifier): void
    {
        $this->identifier = (string) $identifier;
        $this->user = User::query()->findOrFail($this->identifier);
    }

    /**
     * @param  string[]  $scopes
     * @return array<string, string|int|bool|array<int, string>>
     */
    public function getClaims(array $scopes = []): array
    {
        $name = trim((string) $this->user->getName());
        $username = trim((string) ($this->user->username ?? ''));
        $avatarUrl = $this->user->avatarImageUrl();

        return array_filter([
            'name' => $name !== '' ? $name : null,
            'family_name' => trim((string) ($this->user->surname ?? '')) !== '' ? trim((string) $this->user->surname) : null,
            'given_name' => trim((string) ($this->user->firstname ?? '')) !== '' ? trim((string) $this->user->firstname) : null,
            'nickname' => $username !== '' ? $username : null,
            'preferred_username' => $username !== '' ? $username : null,
            'profile' => route('account.show'),
            'groups' => $this->user->groupSlugs(),
            'picture' => is_string($avatarUrl) && $avatarUrl !== '' ? url($avatarUrl) : null,
            'updated_at' => $this->user->updated_at?->timestamp,
            'email' => trim((string) $this->user->email),
            'email_verified' => $this->user->hasVerifiedEmail(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
