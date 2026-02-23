<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['BAS Report']);
            fputcsv($out, ['Period', $data['periodStart']->format('Y-m-d').' to '.$data['periodEnd']->format('Y-m-d')]);
            fputcsv($out, []);
            fputcsv($out, ['Summary']);
            fputcsv($out, ['Metric', 'Amount']);
            fputcsv($out, ['Sales Inc GST', number_format((float) $data['summary']['payments_inc'], 2, '.', '')]);
            fputcsv($out, ['Sales Ex GST', number_format((float) $data['summary']['payments_ex'], 2, '.', '')]);
            fputcsv($out, ['GST on Sales', number_format((float) $data['summary']['payments_gst'], 2, '.', '')]);
            fputcsv($out, ['Expenses Inc GST', number_format((float) $data['summary']['expenses_inc'], 2, '.', '')]);
            fputcsv($out, ['Expenses Ex GST', number_format((float) $data['summary']['expenses_ex'], 2, '.', '')]);
            fputcsv($out, ['GST on Expenses', number_format((float) $data['summary']['expenses_gst'], 2, '.', '')]);
            fputcsv($out, ['Net GST', number_format((float) $data['summary']['net_gst'], 2, '.', '')]);
            fputcsv($out, []);

            fputcsv($out, ['Processed Payments']);
            fputcsv($out, ['Date', 'Customer', 'Total Inc GST', 'GST']);
            foreach ($data['customerPayments'] as $payment) {
                fputcsv($out, [
                    $payment->received_on?->format('Y-m-d H:i') ?? '',
                    $payment->user?->getName() ?? '',
                    number_format((float) $payment->total_amount, 2, '.', ''),
                    number_format((float) ($payment->bas_gst_amount ?? $payment->gst_amount), 2, '.', ''),
                ]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Expenses']);
            fputcsv($out, ['Date', 'Supplier', 'Description', 'Total Inc GST', 'GST']);
            foreach ($data['expenses'] as $expense) {
                fputcsv($out, [
                    $expense->paid_on?->format('Y-m-d') ?? '',
                    (string) ($expense->supplier ?? ''),
                    (string) ($expense->description ?? ''),
                    number_format((float) $expense->total_amount, 2, '.', ''),
                    number_format((float) $expense->gst_amount, 2, '.', ''),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request): Response
    {
        $data = $this->buildBasData($request);
        $month = (string) $data['selectedMonth'];
        $filename = 'bas-'.$month.'.pdf';

        if (! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            abort(500, 'BAS PDF generation requires barryvdh/laravel-dompdf.');
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.bas', $data)
            ->setOption([
                'enable_font_subsetting' => true,
            ]);

        return $pdf->stream($filename, [
            'Attachment' => false,
        ]);
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
            ->where('kind', Payment::KIND_PAYMENT)
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
            ->with(['user', 'allocations.invoice'])
            ->orderBy('received_on')
            ->orderBy('id')
            ->get();

        $customerPayments->each(function (Payment $payment): void {
            $payment->setAttribute('bas_gst_amount', $this->paymentGstAmount($payment));
        });

        $paymentsTotalInc = round((float) $customerPayments->sum(fn (Payment $payment): float => (float) $payment->total_amount), 2);
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

    private function paymentGstAmount(Payment $payment): float
    {
        $storedGst = round((float) $payment->gst_amount, 2);
        if (abs($storedGst) > 0.0001) {
            return $storedGst;
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

        return round($calculatedGst, 2);
    }
}
