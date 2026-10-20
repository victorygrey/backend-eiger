<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class UserInvitation
{
    public function issue(User $user): string
    {
        $token = Str::random(64);
        $user->update([
            'invited_at' => $user->invited_at ?? now(),
            'password_setup_token_hash' => hash('sha256', $token),
            'password_setup_expires_at' => now()->addDay(),
        ]);

        return route('account.setup', ['token' => $token, 'email' => $user->email]);
    }
}
