<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Hosted\HostedSiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PloiTenantCertificateController extends Controller
{
    public function __invoke(Request $request, HostedSiteService $hosting): JsonResponse
    {
        abort_unless(
            hash_equals((string) config('services.ploi.webhook_token'), (string) $request->query('token')),
            403
        );

        $domain = $request->input('domain')
            ?? $request->input('tenant')
            ?? data_get($request->input('data', []), 'domain')
            ?? data_get($request->input('data', []), 'tenant')
            ?? data_get($request->all(), 'tenant.domain');

        abort_unless(is_string($domain) && $domain !== '', 422, 'Domain missing from webhook payload.');

        $hosting->handleCertificateWebhook($domain, $request->all());

        return response()->json(['ok' => true]);
    }
}
