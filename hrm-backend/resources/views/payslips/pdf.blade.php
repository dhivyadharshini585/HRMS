<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip - {{ $payslip->payslip_number }}</title>
    <style>
        @page {
            margin: 35px 40px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #334155;
            margin: 0;
            padding: 0;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .company-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0 0 4px 0;
        }
        .company-subtitle {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        .payslip-title {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 8px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .meta-table td {
            vertical-align: top;
            padding: 4px 8px;
            font-size: 12px;
        }
        .meta-label {
            color: #64748b;
            font-weight: 600;
            width: 25%;
        }
        .meta-value {
            color: #0f172a;
            font-weight: 600;
            width: 25%;
        }
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .breakdown-table th, .breakdown-table td {
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            font-size: 12px;
        }
        .breakdown-table th {
            background-color: #f1f5f9;
            color: #1e293b;
            font-weight: 700;
            text-align: left;
        }
        .amount-col {
            text-align: right;
        }
        .total-row td {
            font-weight: 700;
            background-color: #f8fafc;
            border-top: 2px solid #94a3b8;
        }
        .net-salary-card {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 12px 16px;
            margin-bottom: 20px;
            text-align: right;
        }
        .net-label {
            font-size: 13px;
            color: #1e40af;
            font-weight: 600;
        }
        .net-amount {
            font-size: 20px;
            font-weight: bold;
            color: #1e3a8a;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 12px;
            font-size: 10px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="company-title">ACME HRMS Solutions</div>
                    <div class="company-subtitle">Human Resources Management System</div>
                </td>
                <td style="text-align: right;">
                    <div class="payslip-title">PAYSLIP</div>
                    <div style="font-size: 12px; color: #64748b;">{{ date('F Y', mktime(0, 0, 0, $payroll->month, 10, $payroll->year)) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Employee & Payslip Meta -->
    <table class="meta-table">
        <tr>
            <td class="meta-label">Payslip Number:</td>
            <td class="meta-value">{{ $payslip->payslip_number }}</td>
            <td class="meta-label">Employee Code:</td>
            <td class="meta-value">{{ $employee->employee_code ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Employee Name:</td>
            <td class="meta-value">{{ $employee->first_name }} {{ $employee->last_name }}</td>
            <td class="meta-label">Department:</td>
            <td class="meta-value">{{ $employee->department->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Designation:</td>
            <td class="meta-value">{{ $employee->designation->title ?? 'N/A' }}</td>
            <td class="meta-label">Pay Period:</td>
            <td class="meta-value">{{ $payroll->month }}/{{ $payroll->year }}</td>
        </tr>
        <tr>
            <td class="meta-label">Generation Date:</td>
            <td class="meta-value">{{ $payslip->generated_at ? \Carbon\Carbon::parse($payslip->generated_at)->format('d M Y') : date('d M Y') }}</td>
            <td class="meta-label">Status:</td>
            <td class="meta-value">{{ $payroll->status }}</td>
        </tr>
    </table>

    <!-- Earnings and Deductions Table -->
    <table class="breakdown-table">
        <thead>
            <tr>
                <th style="width: 35%;">Earnings</th>
                <th class="amount-col" style="width: 15%;">Amount (INR)</th>
                <th style="width: 35%;">Deductions</th>
                <th class="amount-col" style="width: 15%;">Amount (INR)</th>
            </tr>
        </thead>
        <tbody>
            @php
                $earningsCount = count($earnings);
                $deductionsCount = count($deductions);
                $maxRows = max($earningsCount, $deductionsCount, 1);
            @endphp
            @for ($i = 0; $i < $maxRows; $i++)
                <tr>
                    <td>{{ isset($earnings[$i]) ? $earnings[$i]['name'] : '' }}</td>
                    <td class="amount-col">{{ isset($earnings[$i]) ? number_format($earnings[$i]['amount'], 2) : '' }}</td>
                    <td>{{ isset($deductions[$i]) ? $deductions[$i]['name'] : '' }}</td>
                    <td class="amount-col">{{ isset($deductions[$i]) ? number_format($deductions[$i]['amount'], 2) : '' }}</td>
                </tr>
            @endfor
            <tr class="total-row">
                <td>Total Gross Earnings</td>
                <td class="amount-col">&#8377; {{ number_format($payroll->gross_earnings, 2) }}</td>
                <td>Total Deductions</td>
                <td class="amount-col">&#8377; {{ number_format($payroll->total_deductions, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Net Salary Summary Card -->
    <div class="net-salary-card">
        <span class="net-label">NET SALARY PAYABLE:&nbsp;&nbsp;</span>
        <span class="net-amount">&#8377; {{ number_format($payroll->net_salary, 2) }}</span>
    </div>

    <!-- Footer -->
    <div class="footer">
        This is a computer-generated document. No physical signature is required. For inquiries, contact payroll@hrms.local.
    </div>
</body>
</html>
