<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employment Offer Letter - {{ $offer->offer_code }}</title>
    <style>
        @page {
            margin: 35px 40px;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 13px;
            line-height: 1.6;
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
            font-size: 22px;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 0.5px;
            margin: 0 0 4px 0;
        }
        .company-subtitle {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .meta-table td {
            vertical-align: top;
            padding: 4px 0;
        }
        .meta-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
        }
        .meta-value {
            font-size: 13px;
            color: #0f172a;
            font-weight: 600;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin: 18px 0 10px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .details-table th, .details-table td {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            text-align: left;
            font-size: 12px;
        }
        .details-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            width: 32%;
        }
        .details-table td {
            color: #0f172a;
            font-weight: 500;
        }
        .text-block {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px 12px;
            font-size: 12px;
            color: #334155;
            white-space: pre-wrap;
            margin-bottom: 15px;
        }
        .acceptance-box {
            margin-top: 30px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 15px;
            background-color: #ffffff;
            page-break-inside: avoid;
        }
        .signature-table {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
        }
        .signature-table td {
            width: 50%;
            vertical-align: bottom;
            padding: 0 15px;
        }
        .signature-line {
            border-top: 1px solid #475569;
            padding-top: 6px;
            font-size: 11px;
            color: #475569;
            text-align: center;
        }
        .footer {
            margin-top: 25px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            font-size: 10px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td style="vertical-align: middle;">
                    <div class="company-title">HUMAN RESOURCE MANAGEMENT SYSTEM</div>
                    <div class="company-subtitle">Official Employment Offer Letter</div>
                </td>
                <td style="text-align: right; vertical-align: middle;">
                    <div style="font-size: 14px; font-weight: bold; color: #2563eb;">{{ $offer->offer_code }}</div>
                    <div style="font-size: 11px; color: #64748b;">Status: {{ $offer->offer_status }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Candidate & Metadata Header -->
    <table class="meta-table">
        <tr>
            <td style="width: 60%;">
                <div class="meta-label">Issued To:</div>
                <div style="font-size: 15px; font-weight: bold; color: #0f172a;">{{ $offer->candidate->full_name }}</div>
                <div style="font-size: 12px; color: #475569;">Email: {{ $offer->candidate->email }}</div>
                <div style="font-size: 12px; color: #475569;">Phone: {{ $offer->candidate->phone }}</div>
                @if($offer->candidate->address)
                    <div style="font-size: 12px; color: #475569;">{{ $offer->candidate->address }}</div>
                @endif
            </td>
            <td style="width: 40%; text-align: right;">
                <div class="meta-label">Offer Date:</div>
                <div class="meta-value">{{ \Carbon\Carbon::parse($offer->offer_date)->format('F d, Y') }}</div>
                <div class="meta-label" style="margin-top: 6px;">Anticipated Joining Date:</div>
                <div class="meta-value" style="color: #2563eb;">{{ \Carbon\Carbon::parse($offer->joining_date)->format('F d, Y') }}</div>
            </td>
        </tr>
    </table>

    <p style="margin: 10px 0 15px 0;">
        Dear <strong>{{ $offer->candidate->first_name }}</strong>,<br>
        On behalf of our organization, we are delighted to offer you the position of 
        <strong>{{ $offer->designation }}</strong>. 
        We were very impressed with your background and achievements, and we are confident that you will make significant contributions to our team.
    </p>

    <!-- Position & Employment Summary -->
    <div class="section-title">1. Position & Employment Details</div>
    <table class="details-table">
        <tr>
            <th>Job Designation</th>
            <td>{{ $offer->designation }}</td>
        </tr>
        <tr>
            <th>Department</th>
            <td>{{ $offer->department ? $offer->department->name : ($offer->jobOpening->department ? $offer->jobOpening->department->name : 'General') }}</td>
        </tr>
        <tr>
            <th>Associated Job Opening</th>
            <td>{{ $offer->jobOpening->title }} ({{ $offer->jobOpening->job_code }})</td>
        </tr>
        <tr>
            <th>Employment Type</th>
            <td>{{ $offer->employment_type }}</td>
        </tr>
        <tr>
            <th>Work Location</th>
            <td>{{ $offer->work_location ?: 'Headquarters / Remote' }}</td>
        </tr>
        @if($offer->probation_period_months !== null)
        <tr>
            <th>Probation Period</th>
            <td>{{ $offer->probation_period_months }} month{{ $offer->probation_period_months == 1 ? '' : 's' }}</td>
        </tr>
        @endif
        @if($offer->notice_period_days !== null)
        <tr>
            <th>Notice Period</th>
            <td>{{ $offer->notice_period_days }} days</td>
        </tr>
        @endif
    </table>

    <!-- Compensation & Benefits -->
    <div class="section-title">2. Compensation & Benefits</div>
    <table class="details-table">
        <tr>
            <th>Salary Amount</th>
            <td style="font-weight: bold; color: #047857; font-size: 13px;">
                {{ $offer->salary_currency }} {{ number_format($offer->salary_amount, 2) }}
            </td>
        </tr>
        <tr>
            <th>Payment Frequency</th>
            <td>{{ $offer->salary_frequency }}</td>
        </tr>
    </table>

    @if(!empty($offer->benefits))
    <div style="font-weight: 600; font-size: 12px; color: #1e3a8a; margin-bottom: 4px;">Benefits & Perks:</div>
    <div class="text-block">{{ $offer->benefits }}</div>
    @endif

    <!-- Terms & Conditions -->
    @if(!empty($offer->terms_and_conditions))
    <div class="section-title">3. Terms & Conditions</div>
    <div class="text-block">{{ $offer->terms_and_conditions }}</div>
    @endif

    <!-- Acceptance Section -->
    <div class="acceptance-box">
        <div style="font-weight: bold; font-size: 12px; color: #0f172a; margin-bottom: 6px;">
            Candidate Acceptance Acknowledgment
        </div>
        <p style="font-size: 11px; margin: 0 0 10px 0; color: #475569;">
            I confirm my acceptance of the employment offer described above. I understand and agree to the position requirements, compensation, and conditions outlined in this document.
        </p>

        <table class="signature-table">
            <tr>
                <td>
                    <div style="height: 35px;"></div>
                    <div class="signature-line">
                        <strong>Authorized HR Representative</strong><br>
                        {{ $offer->creator ? $offer->creator->name : 'Human Resources' }}
                    </div>
                </td>
                <td>
                    <div style="height: 35px;"></div>
                    <div class="signature-line">
                        <strong>Candidate Signature & Date</strong><br>
                        {{ $offer->candidate->full_name }}
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Document Generated on: {{ now()->format('F d, Y H:i:s T') }} | Offer Reference: {{ $offer->offer_code }} | Confidential
    </div>

</body>
</html>
