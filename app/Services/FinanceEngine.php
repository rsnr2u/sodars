<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCalendar;
use App\Models\BookingLog;
use App\Models\Commission;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ProviderPayout;
use App\Models\Refund;
use App\Models\TaxSetting;
use Illuminate\Support\Facades\DB;

class FinanceEngine
{
    public function generateInvoice(Booking $booking): Invoice
    {
        return DB::transaction(function () use ($booking) {
            $existing = Invoice::where('booking_id', $booking->id)->first();
            if ($existing) {
                return $existing;
            }

            $invoiceNumber = 'INV-BKG-' . str_pad($booking->id, 6, '0', STR_PAD_LEFT) . '-' . time();

            return Invoice::create([
                'invoice_number' => $invoiceNumber,
                'booking_id' => $booking->id,
                'campaign_id' => $booking->campaign_id,
                'invoice_date' => now()->toDateString(),
                'subtotal' => $booking->price,
                'gst_amount' => $booking->gst_amount,
                'total_amount' => $booking->total_amount,
                'payment_status' => 'Pending',
            ]);
        });
    }

    public function processPayment(Invoice $invoice, string $mode, string $transactionId, float $amount): Payment
    {
        return DB::transaction(function () use ($invoice, $mode, $transactionId, $amount) {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'payment_mode' => $mode,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'payment_date' => now(),
                'payment_status' => 'Paid',
            ]);

            $invoice->update(['payment_status' => 'Paid']);

            $booking = $invoice->booking;
            $oldStatus = $booking->booking_status;

            $booking->update([
                'payment_status' => 'Paid',
                'booking_status' => 'Confirmed',
            ]);

            // Update composite availability calendar states to Booked
            BookingCalendar::where('booking_id', $booking->id)->update(['status' => 'Booked']);

            // Audit Trail Log
            BookingLog::create([
                'booking_id' => $booking->id,
                'old_status' => $oldStatus,
                'new_status' => 'Confirmed',
                'remarks' => sprintf('Payment received via %s. Transaction ID: %s. Amount: INR %s', $mode, $transactionId, number_format($amount, 2)),
                'portal_source' => 'API Gateway',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_by' => auth()->id() ?? $booking->created_by,
            ]);

            // Fetch active tax settings
            $tax = TaxSetting::first();
            if (!$tax) {
                $tax = TaxSetting::create([
                    'gst_percentage' => 18.00,
                    'cgst_percentage' => 9.00,
                    'sgst_percentage' => 9.00,
                    'igst_percentage' => 18.00,
                    'tds_percentage' => 2.00,
                ]);
            }

            // Provider payout compilation (base amount is booking provider portion)
            $payoutAmount = $booking->provider_amount;
            $tdsAmount = $payoutAmount * ($tax->tds_percentage / 100);
            $gstDeduction = $payoutAmount * ($tax->gst_percentage / 100);
            $finalPayout = $payoutAmount - $tdsAmount;

            ProviderPayout::create([
                'provider_id' => $booking->provider_id,
                'booking_id' => $booking->id,
                'amount' => $payoutAmount,
                'gst_deduction' => $gstDeduction,
                'tds_amount' => $tdsAmount,
                'final_amount' => $finalPayout,
                'payment_status' => 'Pending',
            ]);

            // Agent commission compilation (using parent campaign creator agent)
            $campaign = $booking->campaign;
            $percentage = $booking->price > 0 ? ($booking->commission_amount / $booking->price * 100) : 10.00;

            Commission::create([
                'agent_id' => $campaign->created_by ?? $booking->created_by,
                'booking_id' => $booking->id,
                'commission_percentage' => $percentage,
                'commission_amount' => $booking->commission_amount,
                'status' => 'Pending',
            ]);

            return $payment;
        });
    }

    public function evaluateProofsAndReleaseSettlement(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $hasMounting = $booking->proofs()->where('proof_type', 'Mounting Photo')->exists();
            $hasNight = $booking->proofs()->where('proof_type', 'Night Illumination')->exists();

            if ($hasMounting && $hasNight) {
                // Unlock Provider payout
                ProviderPayout::where('booking_id', $booking->id)
                    ->where('payment_status', 'Pending')
                    ->update([
                        'payment_status' => 'Paid',
                        'payment_date' => now(),
                    ]);

                // Unlock Agent commission
                Commission::where('booking_id', $booking->id)
                    ->where('status', 'Pending')
                    ->update([
                        'status' => 'Approved',
                    ]);
            }
        });
    }

    public function triggerRefund(Booking $booking, float $amount, string $reason): Refund
    {
        return DB::transaction(function () use ($booking, $amount, $reason) {
            $refund = Refund::create([
                'booking_id' => $booking->id,
                'refund_amount' => $amount,
                'refund_reason' => $reason,
                'refund_status' => 'Approved',
            ]);

            $booking->update([
                'payment_status' => 'Refunded',
                'booking_status' => 'Cancelled',
            ]);

            // Free up composite calendar dates back to Available
            BookingCalendar::where('booking_id', $booking->id)->update(['status' => 'Available']);

            // Log status transition
            BookingLog::create([
                'booking_id' => $booking->id,
                'old_status' => 'Confirmed',
                'new_status' => 'Cancelled',
                'remarks' => sprintf('Refund processed. Reason: %s. Amount: INR %s', $reason, number_format($amount, 2)),
                'portal_source' => 'API',
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'created_by' => auth()->id() ?? $booking->created_by,
            ]);

            return $refund;
        });
    }
}
