<?php

namespace App\Events;

use App\Models\Site;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SiteCrawlProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Site $site,
        public string $status,
        public int $pagesCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('site.' . $this->site->id)];
    }

    public function broadcastAs(): string
    {
        return 'SiteCrawlProgress';
    }

    public function broadcastWith(): array
    {
        return [
            'site_id' => $this->site->id,
            'status' => $this->status,
            'pagesCount' => $this->pagesCount,
        ];
    }
}
