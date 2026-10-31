<?php

namespace App\Livewire\Portal;

use App\Models\User;
use App\Portal\VendorAccountContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::portal')]
#[Title('Profile')]
class Profile extends Component
{
    public string $name = '';

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function mount(VendorAccountContext $context): void
    {
        /** @var User $user */
        $user = auth()->user();
        $context->resolve($user);
        $this->name = $user->name;
    }

    public function updateName(VendorAccountContext $context): void
    {
        $validated = $this->validateOnly('name', ['name' => ['required', 'string', 'max:255']]);
        /** @var User $user */
        $user = auth()->user();
        $context->resolve($user);
        $user->update(['name' => $validated['name']]);
        $this->dispatch('profile-updated');
    }

    public function updatePassword(VendorAccountContext $context): void
    {
        $validated = $this->validate([
            'currentPassword' => ['required', 'current_password'],
            'password' => ['required', 'confirmed:passwordConfirmation', Password::defaults()],
        ]);
        /** @var User $user */
        $user = auth()->user();
        $context->resolve($user);
        $user->update(['password' => Hash::make($validated['password'])]);
        $this->reset('currentPassword', 'password', 'passwordConfirmation');
        $this->dispatch('password-updated');
    }

    public function render(VendorAccountContext $context): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.portal.profile', ['account' => $context->resolve($user)]);
    }
}
