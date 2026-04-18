<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        ]);

        $success = $this->syncService->receiveRecord(
            $request->string('type'),
            $request->array('data')
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
}
