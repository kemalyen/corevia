<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

it('shows only password fields on the profile page', function (): void {
    seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('account-manager');

    actingAs($user)
        ->get(route('filament.management.auth.profile'))
        ->assertOk();

    Livewire::test(EditProfile::class)
        ->assertFormFieldExists('currentPassword')
        ->assertFormFieldExists('password')
        ->assertFormFieldExists('passwordConfirmation')
        ->assertFormFieldDoesNotExist('name')
        ->assertFormFieldDoesNotExist('email');
});

it('updates only the authenticated users password', function (): void {
    seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@corevia.test',
    ]);
    $user->assignRole('account-manager');
    actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'currentPassword' => 'password',
            'password' => 'NewPassword123!',
            'passwordConfirmation' => 'NewPassword123!',
        ])
        ->set('data.name', 'Changed Name')
        ->set('data.email', 'changed@corevia.test')
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect(Hash::check('NewPassword123!', $user->password))->toBeTrue()
        ->and($user->name)->toBe('Original Name')
        ->and($user->email)->toBe('original@corevia.test');
});

it('rejects an incorrect current password', function (): void {
    seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('order-manager');
    $originalPasswordHash = $user->password;
    actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'currentPassword' => 'incorrect-password',
            'password' => 'NewPassword123!',
            'passwordConfirmation' => 'NewPassword123!',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    expect($user->fresh()->password)->toBe($originalPasswordHash);
});
