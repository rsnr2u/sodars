<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    use RespondsWithApi;

    public function __construct(private readonly CampaignService $service) {}

    // ─── User-scoped Endpoints ─────────────────────────────────────────────────

    /**
     * GET /campaigns  — List campaigns owned by the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $campaigns = $this->service->paginateForUser(
            $request->user()->id,
            $request->only(['status', 'search', 'start_date', 'end_date']),
            (int) $request->get('per_page', 25)
        );

        return $this->success([
            'campaigns'  => $campaigns->items(),
            'pagination' => [
                'total'        => $campaigns->total(),
                'per_page'     => $campaigns->perPage(),
                'current_page' => $campaigns->currentPage(),
                'last_page'    => $campaigns->lastPage(),
            ],
        ], 'Campaigns fetched successfully.');
    }

    /**
     * POST /campaigns  — Create a new campaign (starts as Draft)
     */
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $campaign = $this->service->create($request->validated(), $request->user()->id);

        return $this->success(['campaign' => $campaign], 'Campaign created successfully.', 201);
    }

    /**
     * GET /campaigns/{id}  — Show campaign with locations + bookings
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $campaign = $this->service->findForUser($id, $request->user()->id);

        return $this->success(['campaign' => $campaign], 'Campaign details fetched successfully.');
    }

    /**
     * PUT /campaigns/{id}  — Update campaign (field-level or location re-sync)
     */
    public function update(UpdateCampaignRequest $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('created_by', $request->user()->id)->findOrFail($id);
        $updated  = $this->service->update($campaign, $request->validated());

        return $this->success(['campaign' => $updated], 'Campaign updated successfully.');
    }

    /**
     * DELETE /campaigns/{id}  — Soft-delete (only allowed if no confirmed bookings)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('created_by', $request->user()->id)->findOrFail($id);

        try {
            $this->service->delete($campaign);
        } catch (\RuntimeException $e) {
            return $this->failure($e->getMessage(), [], 422);
        }

        return $this->success(message: 'Campaign deleted successfully.');
    }

    /**
     * GET /campaigns/{id}/bookings  — Full booking summary for a campaign
     */
    public function bookings(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('created_by', $request->user()->id)->findOrFail($id);
        $summary  = $this->service->getBookingsSummary($campaign);

        return $this->success($summary, 'Campaign booking summary fetched.');
    }

    /**
     * POST /campaigns/{id}/reconcile  — Recalculate campaign financial totals
     */
    public function reconcile(Request $request, int $id): JsonResponse
    {
        $campaign = Campaign::where('created_by', $request->user()->id)->findOrFail($id);
        $updated  = $this->service->reconcileBudget($campaign);

        return $this->success(['campaign' => $updated], 'Campaign budget reconciled successfully.');
    }

    // ─── Admin-scoped Endpoints ────────────────────────────────────────────────

    /**
     * GET /admin/campaigns  — All campaigns (admin view)
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $campaigns = $this->service->paginateAll(
            $request->only(['status', 'search', 'created_by']),
            (int) $request->get('per_page', 25)
        );

        return $this->success([
            'campaigns'  => $campaigns->items(),
            'pagination' => [
                'total'        => $campaigns->total(),
                'per_page'     => $campaigns->perPage(),
                'current_page' => $campaigns->currentPage(),
                'last_page'    => $campaigns->lastPage(),
            ],
        ], 'All campaigns fetched successfully.');
    }

    /**
     * GET /admin/campaigns/{id}  — Full campaign detail (admin view)
     */
    public function adminShow(int $id): JsonResponse
    {
        $campaign = $this->service->findAny($id);

        return $this->success(['campaign' => $campaign], 'Campaign details fetched successfully.');
    }

    /**
     * POST /admin/campaigns/{id}/status  — Admin overrides campaign status
     */
    public function adminUpdateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', Rule::in([
                'Draft', 'Pending', 'Processing', 'Approval Pending',
                'Confirmed', 'Active', 'Completed', 'Cancelled', 'Expired',
            ])],
        ]);

        $campaign = Campaign::findOrFail($id);
        $updated  = $this->service->updateStatus($campaign, $request->status);

        return $this->success(['campaign' => $updated], 'Campaign status updated successfully.');
    }

    /**
     * POST /admin/campaigns/{id}/reconcile  — Admin triggers budget reconciliation
     */
    public function adminReconcile(int $id): JsonResponse
    {
        $campaign = Campaign::findOrFail($id);
        $updated  = $this->service->reconcileBudget($campaign);

        return $this->success(['campaign' => $updated], 'Campaign budget reconciled.');
    }
}
