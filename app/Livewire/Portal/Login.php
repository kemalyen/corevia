<?php

namespace App\Livewire\Portal;

use App\Models\User;
use App\Portal\VendorAccountContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::portal-guest')]
#[Title('Vendor sign in')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function authenticate(VendorAccountContext $context): void
    {
        $credentials = $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $key = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        if (! Auth::attempt([...$credentials, 'is_active' => true], $this->remember)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'These credentials do not match an active portal user.']);
        }

        try {
            $user = Auth::user();
            $context->resolve($user instanceof User ? $user : null);
        } catch (AuthorizationException) {
            Auth::logout();
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'This user is not authorised for the Vendor portal.']);
        }

        RateLimiter::clear($key);
        session()->regenerate();
        $this->redirectRoute('portal.dashboard', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.portal.login');
    }
}
