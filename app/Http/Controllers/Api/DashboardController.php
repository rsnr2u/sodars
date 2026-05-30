<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsCache;
use App\Models\ProviderStaff;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use RespondsWithApi;

    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    public function adminMetrics(): JsonResponse
    {
        $cache = AnalyticsCache::where('analytics_type', 'admin_dashboard')->first();

        $metrics = $cache ? $cache->cache_data : $this->analyticsService->compileAdminDashboard();

        return $this->success([
            'metrics' => $metrics,
        ], 'Admin dashboard metrics fetched successfully.');
    }

    public function providerMetrics(Request $request): JsonResponse
    {
        $providerId = null;
        $user = $request->user();

        if ($user instanceof ProviderStaff) {
            $providerId = $user->provider_id;
        } elseif ($request->filled('provider_id')) {
            $providerId = $request->integer('provider_id');
        }

        abort_unless($providerId, 400, 'Provider ID is required.');

        $cache = AnalyticsCache::where('analytics_type', "provider_dashboard_{$providerId}")->first();

        $metrics = $cache ? $cache->cache_data : $this->analyticsService->compileProviderDashboard($providerId);

        return $this->success([
            'metrics' => $metrics,
        ], 'Provider dashboard metrics fetched successfully.');
    }

    public function agentMetrics(Request $request): JsonResponse
    {
        $agentId = $request->user()->id ?? $request->integer('agent_id');

        abort_unless($agentId, 400, 'Agent ID is required.');

        $cache = AnalyticsCache::where('analytics_type', "agent_dashboard_{$agentId}")->first();

        $metrics = $cache ? $cache->cache_data : $this->analyticsService->compileAgentDashboard($agentId);

        return $this->success([
            'metrics' => $metrics,
        ], 'Agent dashboard metrics fetched successfully.');
    }
}
