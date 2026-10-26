<?php

namespace App\Livewire\Portal;

use App\Models\User;
use App\Portal\Queries\VendorCatalogueQuery;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::portal')]
#[Title('Products & Stock')]
class Products extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(VendorCatalogueQuery $catalogue): View
    {
        /** @var User $user */
        $user = auth()->user();
        $search = trim($this->search);
        $products = $catalogue->forUser($user)
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->paginate(15);

        return view('livewire.portal.products', ['products' => $products]);
    }
}
