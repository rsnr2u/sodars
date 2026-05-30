<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadFollowup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request): JsonResponse
    {
        $query = Lead::query();

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }

        $leads = $query->with(['agent', 'city'])->orderBy('id', 'desc')->paginate($request->integer('per_page', 25));

        return $this->success([
            'leads' => $leads,
        ], 'CRM leads fetched successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'lead_source' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['New', 'Contacted', 'Interested', 'Negotiation', 'Converted', 'Lost'])],
            'remarks' => ['nullable', 'string'],
        ]);

        $lead = Lead::create(array_merge($payload, [
            'lead_source' => $payload['lead_source'] ?? 'Manual',
            'status' => $payload['status'] ?? 'New',
        ]));

        return $this->success([
            'lead' => $lead,
        ], 'CRM lead registered successfully.', 211);
    }

    public function show(int $id): JsonResponse
    {
        $lead = Lead::with(['agent', 'city', 'followups.creator'])->findOrFail($id);

        return $this->success([
            'lead' => $lead,
        ], 'Lead details fetched successfully.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);

        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['sometimes', 'string', 'max:20'],
            'email' => ['sometimes', 'email', 'max:255'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'lead_source' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::in(['New', 'Contacted', 'Interested', 'Negotiation', 'Converted', 'Lost'])],
            'remarks' => ['nullable', 'string'],
        ]);

        $oldStatus = $lead->status;
        $lead->update($payload);

        if ($lead->status === 'Converted' && $oldStatus !== 'Converted') {
            $yearMonthDay = now()->format('Ymd');
            $count = \App\Models\Campaign::whereDate('created_at', now()->toDateString())->count() + 1;
            $campaignCode = sprintf('CMP-%s-%04d', $yearMonthDay, $count);

            \App\Models\Campaign::create([
                'campaign_code' => $campaignCode,
                'title' => sprintf('Campaign for %s', $lead->company_name ?? $lead->name),
                'advertiser_name' => $lead->company_name ?? $lead->name,
                'customer_name' => $lead->name,
                'customer_mobile' => $lead->mobile,
                'customer_email' => $lead->email,
                'budget' => 0.00,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
                'status' => 'Draft',
                'notes' => 'Auto-drafted from converted CRM lead.',
                'created_by' => $lead->agent_id ?? $request->user()->id ?? \App\Models\User::first()->id,
            ]);
        }

        return $this->success([
            'lead' => $lead,
        ], 'CRM lead updated successfully.');
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);

        $payload = $request->validate([
            'agent_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $lead->update([
            'agent_id' => $payload['agent_id'],
        ]);

        return $this->success([
            'lead' => $lead->load('agent'),
        ], 'CRM lead assigned to agent successfully.');
    }

    public function addFollowup(Request $request, int $id): JsonResponse
    {
        $lead = Lead::findOrFail($id);

        $payload = $request->validate([
            'followup_date' => ['required', 'date'],
            'remarks' => ['required', 'string'],
            'status' => ['sometimes', Rule::in(['Pending', 'Completed', 'Rescheduled'])],
        ]);

        $followup = LeadFollowup::create([
            'lead_id' => $lead->id,
            'followup_date' => $payload['followup_date'],
            'remarks' => $payload['remarks'],
            'status' => $payload['status'] ?? 'Pending',
            'created_by' => $request->user()->id,
        ]);

        return $this->success([
            'followup' => $followup,
            'lead' => $lead->load('followups.creator'),
        ], 'Lead follow-up logged successfully.', 211);
    }
}
