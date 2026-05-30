<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingProof;
use App\Models\Commission;
use App\Models\Invoice;
use App\Models\ProviderPayout;
use App\Models\ProviderStaff;
use App\Services\FinanceEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinanceController extends Controller
{
    use RespondsWithApi;

    protected FinanceEngine $financeEngine;

    public function __construct(FinanceEngine $financeEngine)
    {
        $this->financeEngine = $financeEngine;
    }

    public function getInvoices(Request $request): JsonResponse
    {
        $query = Invoice::query();

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->query('payment_status'));
        }

        $invoices = $query->with(['booking', 'campaign'])->orderBy('id', 'desc')->paginate($request->integer('per_page', 25));

        return $this->success([
            'invoices' => $invoices,
        ], 'Invoices list fetched successfully.');
    }

    public function getInvoice(int $id): JsonResponse
    {
        $invoice = Invoice::with(['booking.inventory', 'campaign', 'payments'])->findOrFail($id);

        return $this->success([
            'invoice' => $invoice,
        ], 'Invoice details fetched.');
    }

    public function processCheckout(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::findOrFail($id);

        $payload = $request->validate([
            'payment_mode' => ['required', Rule::in(['UPI', 'Bank Transfer', 'Cheque', 'NEFT', 'RTGS', 'Gateway'])],
            'transaction_id' => ['required', 'string', 'max:255', 'unique:payments,transaction_id'],
        ]);

        $payment = $this->financeEngine->processPayment(
            $invoice,
            $payload['payment_mode'],
            $payload['transaction_id'],
            $invoice->total_amount
        );

        return $this->success([
            'payment' => $payment,
            'invoice' => $invoice->fresh(['booking']),
        ], 'Payment checkout processed successfully and settlements compiled.', 211);
    }

    public function getPayouts(Request $request): JsonResponse
    {
        $query = ProviderPayout::query();

        // Scope by provider if request comes from provider staff
        $user = $request->user();
        if ($user instanceof ProviderStaff) {
            $query->where('provider_id', $user->provider_id);
        } elseif ($request->filled('provider_id')) {
            $query->where('provider_id', $request->integer('provider_id'));
        }

        $payouts = $query->with(['provider', 'booking.inventory'])->orderBy('id', 'desc')->paginate($request->integer('per_page', 25));

        return $this->success([
            'payouts' => $payouts,
        ], 'Provider payouts fetched successfully.');
    }

    public function getCommissions(Request $request): JsonResponse
    {
        $query = Commission::query();

        // Scope by agent user if relevant
        if ($request->filled('agent_id')) {
            $query->where('agent_id', $request->integer('agent_id'));
        }

        $commissions = $query->with(['agent', 'booking'])->orderBy('id', 'desc')->paginate($request->integer('per_page', 25));

        return $this->success([
            'commissions' => $commissions,
        ], 'Agent commissions fetched successfully.');
    }

    public function uploadProof(Request $request, int $bookingId): JsonResponse
    {
        $booking = Booking::findOrFail($bookingId);

        $payload = $request->validate([
            'proof_type' => ['required', Rule::in(['Mounting Photo', 'Night Illumination', 'Drone View', 'Completion Proof'])],
            'proof_file' => ['required', 'file', 'max:10240'], // max 10MB
            'remarks' => ['nullable', 'string'],
        ]);

        $path = $request->file('proof_file')->store('proofs', 'local');

        $proof = BookingProof::create([
            'booking_id' => $booking->id,
            'proof_type' => $payload['proof_type'],
            'proof_file' => $path,
            'remarks' => $payload['remarks'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        // Evaluate and automatically release escrow settlements if both Mounting and Night proofs exist
        $this->financeEngine->evaluateProofsAndReleaseSettlement($booking);

        return $this->success([
            'proof' => $proof,
            'booking' => $booking->fresh(['payout', 'commission']),
        ], 'Proof uploaded successfully and settlements evaluated.', 211);
    }

    public function forceReleaseEscrow(int $bookingId): JsonResponse
    {
        $booking = Booking::findOrFail($bookingId);

        // Force check and release
        $this->financeEngine->evaluateProofsAndReleaseSettlement($booking);

        return $this->success([
            'booking' => $booking->fresh(['payout', 'commission']),
        ], 'Escrow release evaluated manually.');
    }
}
