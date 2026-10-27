<?php

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Livewire\Portal\Assistant;
use App\Models\Account;
use App\Models\AssistantConversation;
use App\Models\Product;
use App\Models\User;
use App\Portal\Assistant\Contracts\AiProvider;
use App\Portal\Assistant\Data\AiProviderResponse;
use App\Portal\Orders\VendorOrderDraft;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

class ConversationFakeProvider implements AiProvider
{
    /** @param list<array<string, mixed>> $messages */
    public function complete(array $messages, array $tools): AiProviderResponse
    {
        return new AiProviderResponse('I found the trusted account information.');
    }
}

function assistantConversationVendor(?Account $account = null): User
{
    $account ??= Account::factory()->withAllProducts()->create([
        'account_type' => AccountType::VENDOR,
        'account_status' => AccountStatus::ACTIVE,
    ]);
    $user = User::factory()->for($account)->create();
    $user->assignRole('vendor-user');

    return $user;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('persists messages only for the authenticated user and active account', function (): void {
    $user = assistantConversationVendor();
    app()->instance(AiProvider::class, new ConversationFakeProvider);
    $this->actingAs($user);

    Livewire::test(Assistant::class)
        ->set('message', 'Find my recent orders')
        ->call('send')
        ->assertSet('message', '')
        ->assertSee('I found the trusted account information.');

    $conversation = AssistantConversation::query()->sole();
    expect($conversation->user_id)->toBe($user->getKey())
        ->and($conversation->account_id)->toBe($user->account_id)
        ->and($conversation->messages()->pluck('role')->all())->toBe(['user', 'assistant']);
});

it('returns not found when selecting another users conversation', function (): void {
    $user = assistantConversationVendor();
    $other = assistantConversationVendor();
    $conversation = AssistantConversation::factory()->create([
        'user_id' => $other->getKey(),
        'account_id' => $other->account_id,
    ]);
    $this->actingAs($user);

    Livewire::test(Assistant::class)
        ->call('selectConversation', $conversation->getKey())
        ->assertNotFound();
});

it('clears the selected conversation and shared draft when the active account changes', function (): void {
    $user = assistantConversationVendor();
    $newAccount = Account::factory()->withAllProducts()->create([
        'account_type' => AccountType::VENDOR,
        'account_status' => AccountStatus::ACTIVE,
    ]);
    $product = Product::factory()->create(['price' => '5.00', 'currency' => 'GBP']);
    $this->actingAs($user);
    app(VendorOrderDraft::class)->addOrUpdate($user, $product->getKey(), '1');
    $conversation = AssistantConversation::factory()->create([
        'user_id' => $user->getKey(),
        'account_id' => $user->account_id,
    ]);
    $component = Livewire::test(Assistant::class)->call('selectConversation', $conversation->getKey());

    $user->update(['account_id' => $newAccount->getKey()]);

    $component->call('newConversation')
        ->assertSet('activeAccountId', $newAccount->getKey())
        ->assertSet('conversationId', null);
    expect(app(VendorOrderDraft::class)->current($user))->toBe([]);
});

it('rechecks pricing before confirmation and prevents duplicate submission', function (): void {
    $user = assistantConversationVendor();
    $product = Product::factory()->create(['price' => '10.00', 'currency' => 'GBP']);
    $this->actingAs($user);
    app(VendorOrderDraft::class)->addOrUpdate($user, $product->getKey(), '2');
    $component = Livewire::test(Assistant::class)->call('reviewDraft');

    $product->update(['price' => '12.00']);

    $component->call('confirmDraft')->assertHasErrors(['draft']);
    $this->assertDatabaseCount('sales_orders', 0);

    $component->call('reviewDraft')
        ->call('confirmDraft')
        ->assertHasNoErrors()
        ->call('confirmDraft');

    $this->assertDatabaseCount('sales_orders', 1);
    $this->assertDatabaseHas('sales_orders', ['account_id' => $user->account_id, 'total_amount' => 24]);
});

it('forbids assistant actions after portal access is revoked', function (): void {
    $user = assistantConversationVendor();
    $this->actingAs($user);
    $component = Livewire::test(Assistant::class);

    $user->update(['is_active' => false]);

    $component->call('newConversation')->assertForbidden();
});
