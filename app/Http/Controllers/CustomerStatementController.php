<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Credit;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class CustomerStatementController extends Controller
{
    public function generateStatement(Request $request, Customer $customer)
    {
        try {
            $reportType = $request->get('report_type');
            $startDate = Carbon::parse($request->get('start_date'));
            $endDate = Carbon::parse($request->get('end_date'));
            $format = $request->get('format');

            // Validate the date range
            if ($startDate->gt($endDate)) {
                return back()->withErrors(['error' => 'Start date cannot be after end date']);
            }

            // Get data based on report type
            $sales = collect();
            $credits = collect();

            if ($reportType === 'sales' || $reportType === 'both') {
                $sales = Sale::with(['saleItems.medicine'])
                    ->where('customer_id', $customer->id)
                    ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            if ($reportType === 'credits' || $reportType === 'both') {
                $credits = Credit::where('customer_id', $customer->id)
                    ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            // Calculate totals
            $salesTotal = $sales->sum('total_amount');
            $creditsTotal = $credits->sum('amount_owed');
            $outstandingBalance = $credits->sum('balance');

            $data = [
                'customer' => $customer,
                'sales' => $sales,
                'credits' => $credits,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'reportType' => $reportType,
                'salesTotal' => $salesTotal,
                'creditsTotal' => $creditsTotal,
                'outstandingBalance' => $outstandingBalance,
                'generatedAt' => now(),
            ];

            $filename = "customer_statement_{$customer->id}_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";

            if ($format === 'pdf') {
                return $this->generatePDF($data, $filename);
            } else {
                return $this->generateCSV($data, $filename);
            }
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to generate statement: ' . $e->getMessage()]);
        }
    }

    private function generatePDF($data, $filename)
    {
        $pdf = PDF::loadView('statements.pdf', $data);
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download($filename . '.pdf');
    }

    private function generateCSV($data, $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"',
        ];

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');

            // CSV Header information
            fputcsv($file, ['Customer Statement']);
            fputcsv($file, ['Customer Name', $data['customer']->name]);
            fputcsv($file, ['Phone', '+256' . $data['customer']->phone]);
            fputcsv($file, ['Email', $data['customer']->email ?? 'N/A']);
            fputcsv($file, ['Address', $data['customer']->address]);
            fputcsv($file, ['Period', $data['startDate']->format('d/m/Y') . ' to ' . $data['endDate']->format('d/m/Y')]);
            fputcsv($file, ['Generated At', $data['generatedAt']->format('d/m/Y H:i:s')]);
            fputcsv($file, []); // Empty row

            // Sales section
            if ($data['reportType'] === 'sales' || $data['reportType'] === 'both') {
                fputcsv($file, ['SALES RECORDS']);
                fputcsv($file, ['Date', 'Order Number', 'Items', 'Total Amount', 'Payment Status']);

                foreach ($data['sales'] as $sale) {
                    $items = $sale->saleItems->map(function ($item) {
                        return $item->medicine->name . ' (Qty: ' . $item->quantity . ')';
                    })->join(', ');

                    fputcsv($file, [
                        $sale->created_at->format('d/m/Y'),
                        '#' . $sale->order_number,
                        $items,
                        'UGX ' . number_format($sale->total_amount, 2),
                        ucfirst($sale->payment_status),
                    ]);
                }

                fputcsv($file, ['', '', '', 'TOTAL SALES:', 'UGX ' . number_format($data['salesTotal'], 2)]);
                fputcsv($file, []); // Empty row
            }

            // Credits section
            if ($data['reportType'] === 'credits' || $data['reportType'] === 'both') {
                fputcsv($file, ['CREDIT RECORDS']);
                fputcsv($file, ['Date', 'Order Number', 'Amount Owed', 'Amount Paid', 'Balance']);

                foreach ($data['credits'] as $credit) {
                    $amountPaid = $credit->amount_owed - $credit->balance;

                    fputcsv($file, [
                        $credit->created_at->format('d/m/Y'),
                        '#' . $credit->order_number,
                        'UGX ' . number_format($credit->amount_owed, 2),
                        'UGX ' . number_format($amountPaid, 2),
                        'UGX ' . number_format($credit->balance, 2),
                    ]);
                }

                fputcsv($file, ['', '', 'TOTAL OUTSTANDING:', '', 'UGX ' . number_format($data['outstandingBalance'], 2)]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
