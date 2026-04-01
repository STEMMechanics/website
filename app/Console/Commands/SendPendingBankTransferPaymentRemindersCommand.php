<?php

namespace App\Console\Commands;

use App\Mail\PendingBankTransferPaymentsDigest;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class SendPendingBankTransferPaymentRemindersCommand extends Command
{
    protected $signature = 'payments:send-pending-bank-transfer-reminders';

    protected $description = 'Queue daily admin reminder emails for pending bank transfer payments older than two days';

    public function handle(): int
    {
        if (! Schema::hasTable('payments')) {
            return self::SUCCESS;
        }

        $pendingPayments = Payment::query()
            ->pendingBankTransfers()
            ->with(['user', 'allocations.invoice'])
            ->where('received_on', '<', now()->subDays(2))
            ->orderBy('received_on')
            ->orderBy('created_at')
            ->get();

        if ($pendingPayments->isEmpty()) {
            $this->info('No pending bank transfer payments are older than two days.');

            return self::SUCCESS;
        }

        $recipients = $this->adminRecipients();
        if ($recipients === []) {
            $this->warn('Pending bank transfers were found, but no admin email recipients are configured.');

            return self::SUCCESS;
        }

        $digestDateLabel = now()->format('F jS Y');
        $paymentPayload = $pendingPayments->map(function (Payment $payment): array {
            $invoiceNumbers = $payment->allocations
                ->filter(fn ($allocation): bool => abs((float) $allocation->allocated_amount) > 0.0001 && $allocation->invoice !== null)
                ->map(fn ($allocation): string => (string) $allocation->invoice->invoice_number)
                ->unique()
                ->values()
                ->all();

            return [
                'id' => (int) $payment->id,
                'customer_name' => $payment->user?->getName() ?: 'Unknown customer',
                'customer_email' => (string) (($payment->user?->email) ?: '-'),
                'received_on' => $payment->received_on?->format('j M Y g:i a') ?? '-',
                'age_label' => $payment->received_on instanceof Carbon
                    ? $payment->received_on->diffForHumans()
                    : 'unknown age',
                'amount' => money((float) $payment->total_amount),
                'reference' => trim((string) ($payment->reference ?? '')) ?: null,
                'notes' => trim((string) ($payment->notes ?? '')) ?: null,
                'edit_url' => route('admin.payment.edit', $payment),
                'allocations' => $invoiceNumbers,
            ];
        })->values()->all();

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->queue(new PendingBankTransferPaymentsDigest($digestDateLabel, $paymentPayload));
        }

        $this->info('Queued pending bank transfer reminders for '.count($recipients).' recipient(s).');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function adminRecipients(): array
    {
        $recipients = User::query()
            ->whereHas('groups', fn ($query) => $query->where('slug', 'admin'))
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        if ($recipients !== []) {
            return $recipients;
        }

        $fallback = strtolower(trim((string) config('mail.from.address', '')));

        return $fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)
            ? [$fallback]
            : [];
    }
}
