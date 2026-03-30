<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebhookEndpointController extends Controller
{
    public const AVAILABLE_EVENTS = [
        'article.generated',
        'article.ready_for_review',
        'article.approved',
        'article.published',
        'approval.requested',
        'approval.approved',
        'approval.rejected',
        'refresh.detected',
        'refresh.executed',
        'refresh.ready_for_review',
        'ai_visibility.changed',
        'campaign.completed',
    ];

    public function store(Request $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        $this->ensureCanManage($request);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(self::AVAILABLE_EVENTS)],
            'secret' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $team->webhookEndpoints()->create([
            'url' => $validated['url'],
            'events' => $validated['events'],
            'secret' => $validated['secret'] ?: Str::random(40),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return back()->with('success', 'Webhook endpoint added.');
    }

    public function update(Request $request, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_unless($webhookEndpoint->team_id === $request->user()->currentTeam?->id, 404);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:255'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(self::AVAILABLE_EVENTS)],
            'secret' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $webhookEndpoint->update([
            'url' => $validated['url'],
            'events' => $validated['events'],
            'secret' => filled($validated['secret'] ?? null) ? $validated['secret'] : $webhookEndpoint->secret,
            'is_active' => $validated['is_active'] ?? $webhookEndpoint->is_active,
        ]);

        return back()->with('success', 'Webhook endpoint updated.');
    }

    public function destroy(Request $request, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_unless($webhookEndpoint->team_id === $request->user()->currentTeam?->id, 404);

        $webhookEndpoint->delete();

        return back()->with('success', 'Webhook endpoint removed.');
    }

    public function test(Request $request, WebhookEndpoint $webhookEndpoint): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_unless($webhookEndpoint->team_id === $request->user()->currentTeam?->id, 404);

        try {
            DeliverWebhookJob::dispatchSync($webhookEndpoint, 'webhook.test', [
                'team_id' => $webhookEndpoint->team_id,
                'message' => 'Test delivery from SEO Autopilot',
            ]);
        } catch (\Throwable $exception) {
            return back()->with('error', 'Test webhook failed: ' . $exception->getMessage());
        }

        return back()->with('success', 'Test webhook delivered.');
    }

    private function ensureCanManage(Request $request): void
    {
        abort_unless(
            $request->user()->currentTeam && $request->user()->isOwnerOrAdminOfTeam($request->user()->currentTeam),
            403
        );
    }
}
