<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountSetupController extends Controller
{
    private function valid(?User $user, string $token): bool
    {
        return $user && $user->awaitingSetup() && $user->isActive()
            && $user->password_setup_expires_at?->isFuture()
            && is_string($user->password_setup_token_hash)
            && hash_equals($user->password_setup_token_hash, hash('sha256', $token));
    }

    public function show(Request $request, string $token)
    {
        $user = User::where('email', $request->query('email'))->first();
        abort_unless($this->valid($user, $token), 410, 'Tautan aktivasi tidak berlaku, kedaluwarsa, atau sudah digunakan. Minta tautan baru kepada SuperAdmin.');

        return response()->view('auth.account-setup', compact('user', 'token'))->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, string $token)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed']]);
        DB::transaction(function () use ($data, $token) {
            $user = User::where('email', $data['email'])->lockForUpdate()->first();
            if (! $this->valid($user, $token)) {
                throw ValidationException::withMessages(['email' => 'Tautan aktivasi tidak berlaku atau sudah digunakan.']);
            }
            $user->forceFill(['password' => $data['password'], 'invitation_accepted_at' => now(), 'email_verified_at' => now(),
                'password_setup_token_hash' => null, 'password_setup_expires_at' => null, 'remember_token' => Str::random(60)])->save();
        });

        return redirect()->route('login')->with('status', 'Password berhasil disimpan. Silakan masuk menggunakan email dan password Anda.');
    }
}
