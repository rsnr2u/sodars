<?php

namespace App\Services;

use App\Models\AnalyticsCache;
use App\Models\Booking;
use App\Models\Commission;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\ProviderPayout;

class AnalyticsService
{
    public function compileAdminDashboard(): array
    {
        $total_revenue = floatval(Payment::where('payment_status', 'Paid')->sum('amount'));
        $total_bookings = Booking::count();
        $confirmed_bookings = Booking::where('booking_status', 'Confirmed')->count();
        $total_providers = Provider::count();
        $approved_providers = Provider::where('status', 'Approved')->count();
        $total_leads = Lead::count();
        $converted_leads = Lead::where('status', 'Converted')->count();
        $conversion_rate = $total_leads > 0 ? round(($converted_leads / $total_leads) * 100, 2) : 0.00;

        $data = compact(
            'total_revenue',
            'total_bookings',
            'confirmed_bookings',
            'total_providers',
            'approved_providers',
            'total_leads',
            'converted_leads',
            'conversion_rate'
        );

        AnalyticsCache::updateOrCreate(
            ['analytics_type' => 'admin_dashboard'],
            [
                'cache_data' => $data,
                'generated_at' => now(),
            ]
        );

        return $data;
    }

    public function compileProviderDashboard(int $providerId): array
    {
        $total_revenue = floatval(Booking::where('provider_id', $providerId)->where('payment_status', 'Paid')->sum('provider_amount'));
        $total_bookings = Booking::where('provider_id', $providerId)->count();
        $pending_bookings = Booking::where('provider_id', $providerId)->where('booking_status', 'Approval Pending')->count();
        $payouts_pending = floatval(ProviderPayout::where('provider_id', $providerId)->where('payment_status', 'Pending')->sum('amount'));
        $payouts_paid = floatval(ProviderPayout::where('provider_id', $providerId)->where('payment_status', 'Paid')->sum('amount'));

        $data = compact(
            'total_revenue',
            'total_bookings',
            'pending_bookings',
            'payouts_pending',
            'payouts_paid'
        );

        AnalyticsCache::updateOrCreate(
            ['analytics_type' => "provider_dashboard_{$providerId}"],
            [
                'cache_data' => $data,
                'generated_at' => now(),
            ]
        );

        return $data;
    }

    public function compileAgentDashboard(int $agentId): array
    {
        $total_leads = Lead::where('agent_id', $agentId)->count();
        
        $pending_followups = LeadFollowup::where('status', 'Pending')
            ->whereHas('lead', function ($q) use ($agentId) {
                $q->where('agent_id', $agentId);
            })->count();

        $commissions_pending = floatval(Commission::where('agent_id', $agentId)->where('status', 'Pending')->sum('commission_amount'));
        $commissions_earned = floatval(Commission::where('agent_id', $agentId)->whereIn('status', ['Approved', 'Paid'])->sum('commission_amount'));

        $data = compact(
            'total_leads',
            'pending_followups',
            'commissions_pending',
            'commissions_earned'
        );

        AnalyticsCache::updateOrCreate(
            ['analytics_type' => "agent_dashboard_{$agentId}"],
            [
                'cache_data' => $data,
                'generated_at' => now(),
            ]
        );

        return $data;
    }
}
