<?php

namespace App\Filament\Resources\NodeResource\Pages;

use App\Filament\Resources\NodeResource;
use App\Jobs\PushSyncJob;
use App\Jobs\SnapshotPullJob;
use App\Models\Node;
use App\Services\SyncService;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Http;

class ListNodes extends ListRecords
{
    protected static string $resource = NodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('joinWorkgroup')
                ->label('Join Workgroup')
                ->icon('heroicon-o-arrow-right-circle')
                ->form([
                    TextInput::make('peer_url')
                        ->label('Peer URL')
                        ->url()
                        ->required()
                        ->placeholder('http://192.168.1.10:8000'),
                    TextInput::make('workgroup_token')
                        ->label('Workgroup Token')
                        ->password()
                        ->revealable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $peerUrl = rtrim($data['peer_url'], '/');

                    $response = Http::timeout(10)->post("{$peerUrl}/api/sync/join", [
                        'node_id' => config('app.node_id'),
                        'node_name' => config('app.node_name'),
                        'node_url' => config('app.url'),
                        'workgroup_token' => $data['workgroup_token'],
                    ]);

                    if (! $response->successful()) {
                        Notification::make()
                            ->title('Join failed')
                            ->body($response->json('error') ?? "Could not reach {$peerUrl}.")
                            ->danger()
                            ->send();

                        return;
                    }

                    $payload = $response->json();

                    // Register the peer we joined using the identity it returned
                    $peerNode = Node::firstOrNew(['url' => $peerUrl]);
                    $peerNode->forceFill([
                        'id' => $payload['node']['id'],
                        'name' => $payload['node']['name'],
                        'url' => $peerUrl,
                        'is_self' => false,
                        'joined_at' => now(),
                        'last_seen_at' => now(),
                    ])->save();

                    // Register any additional peers the joined node knows about
                    foreach ($payload['peers'] ?? [] as $peer) {
                        if (rtrim($peer['url'], '/') === $peerUrl) {
                            continue; // already registered above
                        }

                        $node = Node::firstOrNew(['url' => rtrim($peer['url'], '/')]);
                        $node->forceFill([
                            'id' => $peer['id'],
                            'name' => $peer['name'],
                            'url' => rtrim($peer['url'], '/'),
                            'is_self' => false,
                        ])->save();
                    }

                    // Seed pending rows for our existing data so it gets pushed to the new peer
                    app(SyncService::class)->seedPendingForPeer($peerNode);

                    // Queue the bidirectional initial sync
                    SnapshotPullJob::dispatch($peerUrl);
                    PushSyncJob::dispatch();

                    Notification::make()
                        ->title('Joined workgroup')
                        ->body('Peer registered. Initial sync dispatched to queue.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
