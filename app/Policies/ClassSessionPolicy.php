<?php

namespace App\Policies;

use App\Models\ClassHelpRequest;
use App\Models\ClassSession;
use App\Models\User;

class ClassSessionPolicy
{
    public function view(User $user, ClassSession $classSession): bool
    {
        return $classSession->canJoin($user) || $classSession->canManage($user);
    }

    public function join(User $user, ClassSession $classSession): bool
    {
        return $classSession->canJoin($user) || $classSession->canManage($user);
    }

    public function requestHelp(User $user, ClassSession $classSession): bool
    {
        return $classSession->canManage($user);
    }

    public function manage(User $user, ClassSession $classSession): bool
    {
        return $classSession->canManage($user);
    }

    public function approveHelp(User $user, ClassHelpRequest $helpRequest): bool
    {
        if ($helpRequest->classSession?->canManage($user) ?? false) {
            return true;
        }

        return (string) $helpRequest->user_id === (string) $user->id;
    }
}
