<?php

namespace App\Filament\Cashier\Pages;

use App\Models\Category;
use App\Models\Node;
use App\Models\Product;
use App\Models\QtyType;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class PosTerminal extends Page
{
    protected string $view = 'filament.cashier.pages.pos-terminal';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'POS Terminal';
    }

    public array $cart = [];
    public string $searchQuery = '';
    public ?string $filterCategory = null;
    public string $paymentMethod = 'cash';

    /** Whether the last search resolved to a unique hit (barcode/item_code). */
    public bool $uniqueHit = false;

    #[Computed]
    public function categories(): Collection
    {
        return Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Search results — only populated when there is a query.
     * Returns QtyType collection (with product/category/discount eager-loaded).
     */
    #[Computed]
    public function results(): Collection
    {
        $search = trim($this->searchQuery);

        if ($search === '') {
            return collect();
        }

        // 1) Try sole item_code match → show that product's qty types
        try {
            $product = Product::where('item_code', $search)
                ->where('is_active', true)
                ->sole();

            $this->uniqueHit = true;
            return $product->qtyTypes()
                ->with(['product:id,name,category_id,item_code', 'product.category:id,name', 'discount'])
                ->get();
        } catch (ModelNotFoundException|MultipleRecordsFoundException) {
            // not a sole item_code — fall through to fuzzy search
        }

        // 2) Fuzzy search by name (+ optional category filter)
        $this->uniqueHit = false;

        return QtyType::query()
            ->with(['product:id,name,category_id,item_code,supplier_id', 'product.category:id,name', 'product.supplier:id,name', 'discount'])
            ->whereHas('product', function ($q) use ($search) {
                $q->where('is_active', true)
                  ->where(fn ($q) =>
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('item_code', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                  );

                if ($this->filterCategory) {
                    $q->where('category_id', $this->filterCategory);
                }
            })
            ->orWhere(function ($q) use ($search) {
                $q->where('barcode', 'like', "%{$search}%")
                  ->whereHas('product', fn ($q) => $q->where('is_active', true));
            })
            ->limit(30)
            ->get()
            ->unique('id');
    }

    public function updatedSearchQuery(): void
    {
        unset($this->results);
    }

    /**
     * Fired on Enter key — handles barcode scanner input.
     * Tries sole() on barcode, then item_code. If unique → add to cart and clear.
     */
    public function scan(): void
    {
        $search = trim($this->searchQuery);
        if ($search === '') {
            return;
        }

        // Sole barcode → instant add
        try {
            $qtyType = QtyType::where('barcode', $search)->sole();
            $this->addToCart($qtyType->id);
            $this->searchQuery = '';
            $this->uniqueHit = true;
            unset($this->results);
            return;
        } catch (ModelNotFoundException|MultipleRecordsFoundException) {
        }

        // Sole item_code → instant add (base qty type)
        try {
            $product = Product::where('item_code', $search)
                ->where('is_active', true)
                ->sole();

            $base = $product->qtyTypes()->where('is_base', true)->first()
                 ?? $product->qtyTypes()->first();

            if ($base) {
                $this->addToCart($base->id);
                $this->searchQuery = '';
                $this->uniqueHit = true;
                unset($this->results);
                return;
            }
        } catch (ModelNotFoundException|MultipleRecordsFoundException) {
        }

        // No unique hit — let the fuzzy results stay visible
        $this->uniqueHit = false;
    }

    public function updatedFilterCategory(): void
    {
        unset($this->results);
    }

    public function addToCart(string $qtyTypeId): void
    {
        if (isset($this->cart[$qtyTypeId])) {
            $this->cart[$qtyTypeId]['qty']++;
            return;
        }

        $qtyType = QtyType::with(['product:id,name', 'discount'])->findOrFail($qtyTypeId);

        $this->cart[$qtyTypeId] = [
            'qty_type_id'    => $qtyType->id,
            'product_id'     => $qtyType->product_id,
            'product_name'   => $qtyType->product->name,
            'qty_type_name'  => $qtyType->name,
            'unit_price'     => $qtyType->effectivePrice(),
            'original_price' => (float) $qtyType->selling_price,
            'qty'            => 1,
        ];
    }

    public function removeFromCart(string $qtyTypeId): void
    {
        unset($this->cart[$qtyTypeId]);
    }

    public function updateQty(string $qtyTypeId, int $qty): void
    {
        if ($qty <= 0) {
            $this->removeFromCart($qtyTypeId);
            return;
        }

        $this->cart[$qtyTypeId]['qty'] = $qty;
    }

    public function getCartTotal(): float
    {
        return collect($this->cart)->sum(fn ($item) => $item['unit_price'] * $item['qty']);
    }

    public function checkout(): void
    {
        if (empty($this->cart)) {
            Notification::make()->title('Cart is empty')->warning()->send();
            return;
        }

        $node = Node::self();

        $transaction = Transaction::create([
            'origin_node_id' => $node->id,
            'cashier_name'   => auth()->user()?->name,
            'total_amount'   => $this->getCartTotal(),
            'payment_method' => $this->paymentMethod,
        ]);

        foreach ($this->cart as $item) {
            TransactionItem::create([
                'transaction_id' => $transaction->id,
                'product_id'     => $item['product_id'],
                'product_name'   => $item['product_name'],
                'qty_type_id'    => $item['qty_type_id'],
                'qty_type_name'  => $item['qty_type_name'],
                'unit_price'     => $item['unit_price'],
                'qty'            => $item['qty'],
                'subtotal'       => $item['unit_price'] * $item['qty'],
            ]);
        }

        $this->cart = [];
        $this->paymentMethod = 'cash';

        Notification::make()
            ->title('Sale completed')
            ->body('Transaction #' . substr($transaction->id, 0, 8) . ' recorded.')
            ->success()
            ->send();
    }
}
