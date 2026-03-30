<?php

namespace App\Services\AiVisibility;

use App\Models\AiVisibilityAlert;
use App\Models\AiVisibilityCheck;
use App\Models\Site;
use Illuminate\Support\Collection;

class AiVisibilityAlertService
{
    public function syncForSite(Site $site, array $alerts, Collection $latestChecks): Collection
    {
        $checkIndex = $latestChecks
            ->mapWithKeys(fn (AiVisibilityCheck $check) => ["{$check->ai_prompt_id}:{$check->engine}" => $check]);
        $fingerprints = collect();

        foreach ($alerts as $alert) {
            $fingerprint = $this->fingerprint($alert);
            $fingerprints->push($fingerprint);

            /** @var AiVisibilityAlert|null $existing */
            $existing = $site->aiVisibilityAlerts()
                ->where('fingerprint', $fingerprint)
                ->first();

            $check = $checkIndex->get(($alert['prompt_id'] ?? 'site') . ':' . ($alert['engine'] ?? 'all'));

            $site->aiVisibilityAlerts()->updateOrCreate(
                ['fingerprint' => $fingerprint],
                [
                    'ai_prompt_id' => $alert['prompt_id'] ?? null,
                    'ai_visibility_check_id' => $check?->id,
                    'article_id' => $alert['article_id'] ?? null,
                    'type' => $alert['type'],
                    'severity' => $alert['severity'],
                    'title' => $alert['title'],
                    'reason' => $alert['reason'],
                    'engine' => $alert['engine'] ?? null,
                    'visibility_delta' => $alert['visibility_delta'] ?? null,
                    'related_domains' => array_values($alert['related_domains'] ?? []),
                    'status' => AiVisibilityAlert::STATUS_OPEN,
                    'metadata' => [
                        'article_title' => $alert['article_title'] ?? null,
                    ],
                    'first_detected_at' => $existing?->first_detected_at ?? now(),
                    'last_detected_at' => now(),
                    'resolved_at' => null,
                ],
            );
        }

        $site->aiVisibilityAlerts()
            ->where('status', AiVisibilityAlert::STATUS_OPEN)
            ->when($fingerprints->isNotEmpty(), fn ($query) => $query->whereNotIn('fingerprint', $fingerprints->all()))
            ->update([
                'status' => AiVisibilityAlert::STATUS_RESOLVED,
                'resolved_at' => now(),
            ]);

        return $this->historyForSite($site);
    }

    public function historyForSite(Site $site, int $limit = 12): Collection
    {
        return $site->aiVisibilityAlerts()
            ->with(['prompt', 'article'])
            ->latest('last_detected_at')
            ->take($limit)
            ->get();
    }

    private function fingerprint(array $alert): string
    {
        return sha1(implode('|', [
            $alert['type'] ?? 'alert',
            $alert['prompt_id'] ?? 'site',
            $alert['engine'] ?? 'all',
        ]));
    }
}
