<x-filament-panels::page>
    <div class="flex gap-4 h-[calc(100vh-10rem)]">

        {{-- Left: Search & Results --}}
        <div class="flex-1 flex flex-col gap-3 overflow-hidden">

            {{-- Search bar --}}
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model.live.debounce.300ms="searchQuery"
                    wire:keydown.enter="scan"
                    placeholder="Scan barcode, enter item code, or search by name…"
                    autofocus
                />
            </x-filament::input.wrapper>

            {{-- Category filter (only visible during fuzzy search) --}}
            @if(trim($searchQuery) !== '' && !$uniqueHit)
                <div class="flex gap-2 flex-wrap">
                    <button
                        wire:click="$set('filterCategory', null)"
                        @class([
                            'px-3 py-1 rounded-full text-sm font-medium transition-colors',
                            'bg-primary-600 text-white' => $filterCategory === null,
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200' => $filterCategory !== null,
                        ])
                    >All</button>
                    @foreach($this->categories as $category)
                        <button
                            wire:click="$set('filterCategory', '{{ $category->id }}')"
                            @class([
                                'px-3 py-1 rounded-full text-sm font-medium transition-colors',
                                'bg-primary-600 text-white' => $filterCategory === $category->id,
                                'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200' => $filterCategory !== $category->id,
                            ])
                        >{{ $category->name }}</button>
                    @endforeach
                </div>
            @endif

            {{-- Results grid --}}
            <div class="overflow-y-auto flex-1">
                @if(trim($searchQuery) === '')
                    <div class="flex flex-col items-center justify-center h-full text-gray-400 dark:text-gray-500">
                        <x-heroicon-o-magnifying-glass class="w-12 h-12 mb-3 opacity-40"/>
                        <p class="text-sm">Scan a barcode or type to search</p>
                    </div>
                @else
                    @php $results = $this->results; @endphp
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        @forelse($results as $qt)
                            @php
                                $effective = $qt->effectivePrice();
                                $hasDiscount = $effective < (float) $qt->selling_price;
                            @endphp
                            <button
                                wire:click="addToCart('{{ $qt->id }}')"
                                class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-left hover:border-primary-500 hover:shadow-md transition-all active:scale-95"
                            >
                                <div class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">
                                    {{ $qt->product->category->name ?? '' }}
                                </div>
                                <div class="font-semibold text-sm text-gray-900 dark:text-white leading-tight">
                                    {{ $qt->product->name }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ $qt->name }}
                                    @if($qt->qty > 1) &middot; &times;{{ $qt->qty }} @endif
                                    <span class="text-gray-300 dark:text-gray-600">&middot; {{ $qt->barcode }}</span>
                                </div>
                                <div class="mt-2 flex items-baseline gap-1.5">
                                    <span class="text-primary-600 font-bold">${{ number_format($effective, 2) }}</span>
                                    @if($hasDiscount)
                                        <span class="text-xs text-gray-400 line-through">${{ number_format($qt->selling_price, 2) }}</span>
                                    @endif
                                </div>
                            </button>
                        @empty
                            <div class="col-span-full text-center text-gray-400 py-12">
                                No results for "{{ $searchQuery }}"
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>

        {{-- Right: Cart --}}
        <div class="w-80 flex flex-col bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 font-semibold text-gray-900 dark:text-white">
                Cart
                @if(count($cart) > 0)
                    <span class="ml-1 text-sm font-normal text-gray-400">({{ count($cart) }} lines)</span>
                @endif
            </div>

            <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($cart as $qtyTypeId => $item)
                    <div class="px-4 py-3 flex items-center gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                {{ $item['product_name'] }}
                            </div>
                            <div class="text-xs text-gray-400">
                                {{ $item['qty_type_name'] }} &middot; ${{ number_format($item['unit_price'], 2) }}
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button
                                wire:click="updateQty('{{ $qtyTypeId }}', {{ $item['qty'] - 1 }})"
                                class="w-6 h-6 flex items-center justify-center rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-600 dark:text-gray-300 text-xs font-bold"
                            >&minus;</button>
                            <span class="w-6 text-center text-sm font-medium text-gray-900 dark:text-white">{{ $item['qty'] }}</span>
                            <button
                                wire:click="updateQty('{{ $qtyTypeId }}', {{ $item['qty'] + 1 }})"
                                class="w-6 h-6 flex items-center justify-center rounded bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 text-gray-600 dark:text-gray-300 text-xs font-bold"
                            >+</button>
                        </div>
                        <div class="text-sm font-semibold text-gray-900 dark:text-white w-14 text-right">
                            ${{ number_format($item['unit_price'] * $item['qty'], 2) }}
                        </div>
                        <button wire:click="removeFromCart('{{ $qtyTypeId }}')" class="text-red-400 hover:text-red-600 ml-1">
                            <x-heroicon-s-x-mark class="w-4 h-4"/>
                        </button>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center text-gray-400 text-sm">Cart is empty</div>
                @endforelse
            </div>

            {{-- Payment + Total --}}
            <div class="border-t border-gray-200 dark:border-gray-700 p-4 space-y-3">
                <div class="flex justify-between text-lg font-bold text-gray-900 dark:text-white">
                    <span>Total</span>
                    <span>${{ number_format($this->getCartTotal(), 2) }}</span>
                </div>

                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model="paymentMethod" class="w-full">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="qr">QR / E-Wallet</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>

                <button
                    wire:click="checkout"
                    wire:loading.attr="disabled"
                    @if(empty($cart)) disabled @endif
                    class="w-full py-3 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white font-semibold rounded-xl transition-colors"
                >
                    <span wire:loading.remove wire:target="checkout">
                        Charge ${{ number_format($this->getCartTotal(), 2) }}
                    </span>
                    <span wire:loading wire:target="checkout">Processing&hellip;</span>
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
