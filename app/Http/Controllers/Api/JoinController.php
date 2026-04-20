<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JoinController extends Controller
{
    public function join(Request $request): JsonResponse
    {
        $request->validate([
            'node_id' => 'required|uuid',
            'node_name' => 'required|string|max:255',
            'node_url' => 'required|url',
            'workgroup_token' => 'required|string',
        ]);

        $configured = config('app.workgroup_token');

        if (empty($configured) || ! hash_equals($configured, $request->string('workgroup_token')->toString())) {
            return response()->json(['error' => 'Invalid workgroup token.'], 401);
        }

        $node = Node::firstOrNew(['id' => $request->string('node_id')->toString()]);
        $node->forceFill([
            'name' => $request->string('node_name')->toString(),
            'url' => rtrim($request->string('node_url')->toString(), '/'),
            'is_self' => false,
            'joined_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        $peers = Node::where('is_self', false)
            ->get(['id', 'name', 'url', 'joined_at'])
            ->toArray();

        return response()->json([
            'status' => 'joined',
            'node' => [
                'id' => config('app.node_id'),
                'name' => config('app.node_name'),
                'url' => config('app.url'),
            ],
            'peers' => $peers,
        ]);
    }
}
