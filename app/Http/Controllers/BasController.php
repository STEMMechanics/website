<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class BasController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.bas.index', $this->buildBasData($request));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $data = $this->buildBasData($request);
        $month = (string) $data['selectedMonth'];
        $filename = 'bas-'.$month.'.csv';
        $csv = $this->buildCsvContent($data);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $data = $this->buildBasData($request);
        $month = (string) $data['selectedMonth'];
        $filename = 'bas-'.$month.'.pdf';

        return $this->buildBasPdf($data)->stream($filename);
    }

    public function downloadAll(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $data = $this->buildBasData($request);
        $month = (string) $data['selectedMonth'];
        $zipPath = tempnam(sys_get_temp_dir(), 'bas-report-');
        if (! is_string($zipPath)) {
            throw new RuntimeException('Unable to create temporary BAS archive.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            throw new RuntimeException('Unable to create BAS archive.');
        }

        $zip->addFromString('bas-'.$month.'.csv', $this->buildCsvContent($data));
        $zip->addFromString('bas-'.$month.'.pdf', $this->buildBasPdf($data)->output());

        foreach ($data['expenses'] as $expense) {
            $path = trim((string) ($expense->receipt_document_path ?? ''));
            if ($path === '' || ! Storage::disk('local')->exists($path)) {
                continue;
            }

            $archiveName = trim((string) ($expense->receipt_document_name ?? basename($path)));
            $archiveName = $archiveName !== '' ? $archiveName : basename($path);
            $zip->addFile(Storage::disk('local')->path($path), 'expense-documents/'.$archiveName);
        }

        $zip->close();

        return response()->download($zipPath, 'bas-report-'.$month.'.zip')->deleteFileAfterSend(true);
    }

    private function buildBasData(Request $request): array
    {
        $selectedMonth = trim((string) $request->input('month', now()->subMonthNoOverflow()->format('Y-m')));
        if (! preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = now()->subMonthNoOverflow()->format('Y-m');
        }

        $start = Carbon::createFromFormat('Y-m', $selectedMonth, config('app.timezone'))->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $expensesQuery = Expense::query()
            ->whereNotNull('paid_on')
            ->whereBetween('paid_on', [$start->toDateString(), $end->toDateString()]);

        $paymentsQuery = Payment::query()
            ->whereIn('kind', [Payment::KIND_PAYMENT, Payment::KIND_REFUND])
            ->whereNotNull('received_on')
            ->whereBetween('received_on', [$start->copy()->startOfDay()->toDateTimeString(), $end->copy()->endOfDay()->toDateTimeString()]);

        $expensesTotalInc = round((float) (clone $expensesQuery)->sum('total_amount'), 2);
        $expensesGst = round((float) (clone $expensesQuery)->sum('gst_amount'), 2);
        $expensesTotalEx = round($expensesTotalInc - $expensesGst, 2);

        $expenses = (clone $expensesQuery)
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get();

        $customerPayments = (clone $paymentsQuery)
            ->with(['user', 'allocations.invoice.lines', 'refundOf.allocations.invoice.lines'])
            ->orderBy('received_on')
            ->orderBy('id')
            ->get();

        $customerPayments->each(function (Payment $payment): void {
            $signedTotal = $this->paymentSignedAmount($payment);
            $signedGst = $this->paymentGstAmount($payment);
            $payment->setAttribute('bas_total_amount', $signedTotal);
            $payment->setAttribute('bas_gst_amount', $signedGst);
            $payment->setAttribute('bas_ex_amount', round($signedTotal - $signedGst, 2));
            $payment->setAttribute('bas_summary', $this->paymentSummary($payment));
        });

        $paymentsTotalInc = round((float) $customerPayments->sum(fn (Payment $payment): float => (float) ($payment->bas_total_amount ?? 0)), 2);
        $paymentsGst = round((float) $customerPayments->sum(fn (Payment $payment): float => (float) ($payment->bas_gst_amount ?? 0)), 2);
        $paymentsTotalEx = round($paymentsTotalInc - $paymentsGst, 2);

        return [
            'selectedMonth' => $selectedMonth,
            'periodStart' => $start,
            'periodEnd' => $end,
            'expenses' => $expenses,
            'customerPayments' => $customerPayments,
            'summary' => [
                'expenses_inc' => $expensesTotalInc,
                'expenses_ex' => $expensesTotalEx,
                'expenses_gst' => $expensesGst,
                'payments_inc' => $paymentsTotalInc,
                'payments_ex' => $paymentsTotalEx,
                'payments_gst' => $paymentsGst,
                'net_gst' => round($paymentsGst - $expensesGst, 2),
            ],
        ];
    }

    private function buildCsvContent(array $data): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new RuntimeException('Unable to build BAS CSV.');
        }

        fputcsv($stream, ['BAS Report']);
        fputcsv($stream, ['Period', $data['periodStart']->format('Y-m-d').' to '.$data['periodEnd']->format('Y-m-d')]);
        fputcsv($stream, []);
        fputcsv($stream, ['Summary']);
        fputcsv($stream, ['Metric', 'Amount']);
        fputcsv($stream, ['Sales incl GST', number_format((float) $data['summary']['payments_inc'], 2, '.', '')]);
        fputcsv($stream, ['Sales Ex GST', number_format((float) $data['summary']['payments_ex'], 2, '.', '')]);
        fputcsv($stream, ['GST on Sales', number_format((float) $data['summary']['payments_gst'], 2, '.', '')]);
        fputcsv($stream, ['Expenses incl GST', number_format((float) $data['summary']['expenses_inc'], 2, '.', '')]);
        fputcsv($stream, ['Expenses Ex GST', number_format((float) $data['summary']['expenses_ex'], 2, '.', '')]);
        fputcsv($stream, ['GST on Expenses', number_format((float) $data['summary']['expenses_gst'], 2, '.', '')]);
        fputcsv($stream, ['Net GST', number_format((float) $data['summary']['net_gst'], 2, '.', '')]);
        fputcsv($stream, []);

        fputcsv($stream, ['Processed Payments']);
        fputcsv($stream, ['Date', 'Customer', 'Summary', 'Amount Ex GST', 'GST', 'Total incl GST']);
        foreach ($data['customerPayments'] as $payment) {
            fputcsv($stream, [
                $payment->received_on?->format('Y-m-d H:i') ?? '',
                $payment->user?->getName() ?? '',
                (string) ($payment->bas_summary ?? ''),
                number_format((float) ($payment->bas_ex_amount ?? 0), 2, '.', ''),
                number_format((float) ($payment->bas_gst_amount ?? $payment->gst_amount), 2, '.', ''),
                number_format((float) ($payment->bas_total_amount ?? $payment->total_amount), 2, '.', ''),
            ]);
        }
        fputcsv($stream, []);

        fputcsv($stream, ['Expenses']);
        fputcsv($stream, ['Date', 'Supplier', 'Invoice ID', 'Description', 'Amount Ex GST', 'GST', 'Total incl GST', 'Document']);
        foreach ($data['expenses'] as $expense) {
            $expenseTotal = round((float) $expense->total_amount, 2);
            $expenseGst = round((float) $expense->gst_amount, 2);
            fputcsv($stream, [
                $expense->paid_on?->format('Y-m-d') ?? '',
                (string) ($expense->supplier ?? ''),
                (string) ($expense->invoice_id ?? ''),
                (string) ($expense->description ?? ''),
                number_format($expenseTotal - $expenseGst, 2, '.', ''),
                number_format($expenseGst, 2, '.', ''),
                number_format($expenseTotal, 2, '.', ''),
                (string) ($expense->receipt_document_name ?? ''),
            ]);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content !== false ? $content : '';
    }

    private function buildBasPdf(array $data): \Barryvdh\DomPDF\PDF
    {
        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'BAS PDF generation requires barryvdh/laravel-dompdf.');
        }

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.bas', $data)
            ->setOption([
                'enable_font_subsetting' => true,
            ]);
    }

    private function paymentGstAmount(Payment $payment): float
    {
        $baseGst = $this->paymentBaseGstAmount($payment);

        if ($payment->isRefund() && $baseGst <= 0.0001) {
            $original = $payment->refundOf;
            if ($original instanceof Payment) {
                $originalAmount = abs(round((float) $original->total_amount, 2));
                $refundAmount = abs(round((float) $payment->total_amount, 2));
                if ($originalAmount > 0.0001 && $refundAmount > 0.0001) {
                    $ratio = max(0.0, min(1.0, $refundAmount / $originalAmount));
                    $baseGst = round($this->paymentBaseGstAmount($original) * $ratio, 2);
                }
            }
        }

        if ($payment->isRefund()) {
            return -abs($baseGst);
        }

        return abs($baseGst);
    }

    private function paymentSignedAmount(Payment $payment): float
    {
        $amount = round((float) $payment->total_amount, 2);

        if ($payment->isRefund()) {
            return -abs($amount);
        }

        return abs($amount);
    }

    private function paymentSummary(Payment $payment): string
    {
        $sourcePayment = $payment->isRefund() && $payment->refundOf instanceof Payment
            ? $payment->refundOf
            : $payment;

        $invoices = $sourcePayment->allocations
            ->pluck('invoice')
            ->filter()
            ->unique(fn ($invoice) => $invoice->id)
            ->values();

        $lineDescriptions = $invoices
            ->flatMap(function ($invoice): Collection {
                return $invoice->lines
                    ->pluck('description')
                    ->map(fn ($description) => trim((string) $description))
                    ->filter();
            })
            ->unique()
            ->values();

        $summary = '';

        if ($lineDescriptions->isNotEmpty()) {
            $summary = (string) $lineDescriptions->first();
            $remainingCount = $lineDescriptions->count() - 1;
            if ($remainingCount > 0) {
                $summary .= ' +'.$remainingCount.' more';
            }
        } elseif ($invoices->isNotEmpty()) {
            $invoiceNumbers = $invoices
                ->map(fn ($invoice) => trim((string) ($invoice->invoice_number ?? '')))
                ->filter()
                ->values();

            if ($invoiceNumbers->isNotEmpty()) {
                $summary = $invoiceNumbers->take(2)->implode(', ');
                $remainingCount = $invoiceNumbers->count() - 2;
                if ($remainingCount > 0) {
                    $summary .= ' +'.$remainingCount.' more';
                }
            }
        }

        if ($summary === '') {
            $summary = trim((string) ($payment->reference ?? ''))
                ?: trim((string) ($payment->notes ?? ''));
        }

        if ($summary === '') {
            $summary = $payment->isRefund() ? 'Refund' : 'Unallocated payment';
        }

        if ($payment->isRefund() && ! str_starts_with(strtolower($summary), 'refund')) {
            $summary = 'Refund for '.$summary;
        }

        return Str::limit($summary, 100);
    }

    private function paymentBaseGstAmount(Payment $payment): float
    {
        $storedGst = round((float) $payment->gst_amount, 2);
        if (abs($storedGst) > 0.0001) {
            return abs($storedGst);
        }

        $calculatedGst = 0.0;
        foreach ($payment->allocations as $allocation) {
            $invoice = $allocation->invoice;
            if (! $invoice) {
                continue;
            }

            $allocatedAmount = (float) $allocation->allocated_amount;
            $invoiceTotal = (float) $invoice->total_amount;
            $invoiceGst = (float) $invoice->gst_amount;

            if ($allocatedAmount <= 0 || $invoiceTotal <= 0 || $invoiceGst <= 0) {
                continue;
            }

            $ratio = max(0.0, min(1.0, $allocatedAmount / $invoiceTotal));
            $calculatedGst += $invoiceGst * $ratio;
        }

        return abs(round($calculatedGst, 2));
    }
}
