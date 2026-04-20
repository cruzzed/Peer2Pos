<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\QtyType;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Services\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(private SyncService $syncService) {}

    /**
     * Receive a record pushed from a peer node.
     */
    public function receive(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string',
            'data' => 'required|array',
            'sender_node_id' => 'nullable|uuid',
        ]);

        $success = $this->syncService->receiveRecord(
            $request->string('type'),
            $request->array('data'),
            $request->string('sender_node_id')->toString() ?: null,
        );

        if (! $success) {
            return response()->json(['error' => 'Unknown or disallowed record type.'], 422);
        }

        return response()->json(['status' => 'accepted']);
    }

    /**
     * Manually trigger a full sync push to all peers.
     */
    public function push(): JsonResponse
    {
        $results = $this->syncService->syncAll();

        return response()->json(['results' => $results]);
    }

    /**
     * Return a full snapshot of all syncable records for initial peer sync.
     */
    public function snapshot(): JsonResponse
    {
        $records = [];

        foreach ([Category::class, Supplier::class, Location::class] as $class) {
            foreach ($class::all() as $record) {
                $records[] = ['type' => $class, 'data' => $record->toArray()];
            }
        }

        foreach (Product::with('qtyTypes.discount')->get() as $product) {
            $data = $product->toArray();
            $data['qty_types'] = $product->qtyTypes->map(function (QtyType $qt) {
                return array_merge($qt->toArray(), ['discount' => $qt->discount?->toArray()]);
            })->all();
            $records[] = ['type' => Product::class, 'data' => $data];
        }

        foreach (Transaction::with('items')->get() as $transaction) {
            $data = $transaction->toArray();
            $data['transaction_items'] = $transaction->items->toArray();
            $records[] = ['type' => Transaction::class, 'data' => $data];
        }

        return response()->json(['records' => $records]);
    }
}
