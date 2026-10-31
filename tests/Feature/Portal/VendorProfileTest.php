<?php

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Livewire\Portal\Profile;
use App\Models\Account;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('updates only the signed-in users name', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $account = Account::factory()->create(['account_type' => AccountType::VENDOR, 'account_status' => AccountStatus::ACTIVE]);
    $user = User::factory()->for($account)->create(['name' => 'Original Name']);
    $otherUser = User::factory()->for($account)->create(['name' => 'Other Name']);
    $user->assignRole('vendor-user');
    $this->actingAs($user);

    Livewire::test(Profile::class)->set('name', 'Updated Name')->call('updateName')->assertHasNoErrors();

    expect($user->fresh()->name)->toBe('Updated Name')
        ->and($otherUser->fresh()->name)->toBe('Other Name');
});

it('requires the current password before changing it', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $account = Account::factory()->create(['account_type' => AccountType::VENDOR, 'account_status' => AccountStatus::ACTIVE]);
    $user = User::factory()->for($account)->create();
    $user->assignRole('vendor-user');
    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->set('currentPassword', 'wrong-password')
        ->set('password', 'new-secure-password')
        ->set('passwordConfirmation', 'new-secure-password')
        ->call('updatePassword')
        ->assertHasErrors(['currentPassword']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('changes the signed-in users password after confirmation', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $account = Account::factory()->create(['account_type' => AccountType::VENDOR, 'account_status' => AccountStatus::ACTIVE]);
    $user = User::factory()->for($account)->create();
    $user->assignRole('vendor-user');
    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->set('currentPassword', 'password')
        ->set('password', 'new-secure-password')
        ->set('passwordConfirmation', 'new-secure-password')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-secure-password', $user->fresh()->password))->toBeTrue();
});
