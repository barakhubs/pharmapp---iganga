<!DOCTYPE html>
<html>

<head>
    <title>Customer Statement</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .statement-title {
            font-size: 18px;
            color: #34495e;
            margin-bottom: 10px;
        }

        .customer-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
        }

        .customer-info h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .info-row {
            display: flex;
            margin-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
            width: 120px;
            color: #555;
        }

        .info-value {
            flex: 1;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            background-color: #3498db;
            color: white;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: bold;
            border-radius: 5px 5px 0 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th,
        td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #ecf0f1;
            font-weight: bold;
            color: #2c3e50;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .amount {
            text-align: right;
            font-weight: bold;
        }

        .total-row {
            background-color: #e8f4fd !important;
            font-weight: bold;
        }

        .total-row td {
            border-top: 2px solid #3498db;
        }

        .summary {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .summary h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 5px 0;
        }

        .summary-item.total {
            border-top: 2px solid #3498db;
            font-weight: bold;
            font-size: 14px;
            color: #2c3e50;
            margin-top: 10px;
            padding-top: 10px;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #7f8c8d;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }

        .no-data {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
            padding: 20px;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="company-name">{{ config('app.name', 'Pharmacy Management System') }}</div>
        <div class="statement-title">Customer Statement</div>
    </div>

    <div class="customer-info">
        <h3>Customer Information</h3>
        <div class="info-row">
            <span class="info-label">Name:</span>
            <span class="info-value">{{ $customer->name }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Phone:</span>
            <span class="info-value">+256{{ $customer->phone }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Email:</span>
            <span class="info-value">{{ $customer->email ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Address:</span>
            <span class="info-value">{{ $customer->address }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Statement Period:</span>
            <span class="info-value">{{ $startDate->format('d/m/Y') }} to {{ $endDate->format('d/m/Y') }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Generated:</span>
            <span class="info-value">{{ $generatedAt->format('d/m/Y H:i:s') }}</span>
        </div>
    </div>

    @if ($reportType === 'sales' || $reportType === 'both')
        <div class="section">
            <div class="section-title">Sales Records</div>
            @if ($sales->count() > 0)
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Order #</th>
                            <th>Items</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sales as $sale)
                            <tr>
                                <td>{{ $sale->created_at->format('d/m/Y') }}</td>
                                <td>#{{ $sale->order_number }}</td>
                                <td>
                                    @foreach ($sale->saleItems as $item)
                                        {{ $item->medicine->name }} ({{ $item->quantity }})
                                        @if (!$loop->last)
                                            ,
                                        @endif
                                    @endforeach
                                </td>
                                <td class="amount">UGX {{ number_format($sale->total_amount, 2) }}</td>
                                <td>{{ ucfirst($sale->payment_status) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total-row">
                            <td colspan="3"><strong>Total Sales</strong></td>
                            <td class="amount"><strong>UGX {{ number_format($salesTotal, 2) }}</strong></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            @else
                <div class="no-data">No sales records found for the selected period.</div>
            @endif
        </div>
    @endif

    @if ($reportType === 'credits' || $reportType === 'both')
        <div class="section">
            <div class="section-title">Credit Records</div>
            @if ($credits->count() > 0)
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Order #</th>
                            <th>Amount Owed</th>
                            <th>Amount Paid</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($credits as $credit)
                            @php
                                $amountPaid = $credit->amount_owed - $credit->balance;
                            @endphp
                            <tr>
                                <td>{{ $credit->created_at->format('d/m/Y') }}</td>
                                <td>#{{ $credit->order_number }}</td>
                                <td class="amount">UGX {{ number_format($credit->amount_owed, 2) }}</td>
                                <td class="amount">UGX {{ number_format($amountPaid, 2) }}</td>
                                <td class="amount">UGX {{ number_format($credit->balance, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="total-row">
                            <td colspan="2"><strong>Total Outstanding</strong></td>
                            <td class="amount"><strong>UGX {{ number_format($creditsTotal, 2) }}</strong></td>
                            <td></td>
                            <td class="amount"><strong>UGX {{ number_format($outstandingBalance, 2) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            @else
                <div class="no-data">No credit records found for the selected period.</div>
            @endif
        </div>
    @endif

    @if ($reportType === 'both')
        <div class="summary">
            <h3>Summary</h3>
            <div class="summary-item">
                <span>Total Sales:</span>
                <span>UGX {{ number_format($salesTotal, 2) }}</span>
            </div>
            <div class="summary-item">
                <span>Total Credits:</span>
                <span>UGX {{ number_format($creditsTotal, 2) }}</span>
            </div>
            <div class="summary-item total">
                <span>Outstanding Balance:</span>
                <span>UGX {{ number_format($outstandingBalance, 2) }}</span>
            </div>
        </div>
    @endif

    <div class="footer">
        <p>This is a computer-generated statement. No signature required.</p>
        <p>Generated on {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>

</html>
