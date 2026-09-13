<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class CertificateGenerator
{
    /**
     * Generate binary PDF content for a training completion certificate.
     *
     * @param array $data
     * @return string
     */
    public static function generatePdf(array $data): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);

        $employeeName = htmlspecialchars($data['employee_name'] ?? 'Employee', ENT_QUOTES, 'UTF-8');
        $trainingName = htmlspecialchars($data['training_name'] ?? 'Training', ENT_QUOTES, 'UTF-8');
        $trainerName = htmlspecialchars($data['trainer_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $startDate = htmlspecialchars($data['start_date'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $endDate = htmlspecialchars($data['end_date'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
        $completionStatus = htmlspecialchars(ucfirst($data['completion_status'] ?? 'Completed'), ENT_QUOTES, 'UTF-8');

        $html = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            margin: 0;
            padding: 30px;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .outer-border {
            border: 6px solid #1e3a8a;
            padding: 8px;
            background-color: #ffffff;
        }
        .inner-border {
            border: 2px solid #93c5fd;
            padding: 30px 40px;
            text-align: center;
        }
        .title {
            font-size: 30px;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 3px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .subtitle {
            font-size: 15px;
            color: #64748b;
            margin-bottom: 20px;
            font-style: italic;
        }
        .employee-name {
            font-size: 26px;
            font-weight: bold;
            color: #0f172a;
            margin: 15px 0;
            padding-bottom: 6px;
            border-bottom: 2px solid #cbd5e1;
            display: inline-block;
            min-width: 300px;
        }
        .completion-text {
            font-size: 16px;
            color: #475569;
            margin: 15px 0 10px 0;
        }
        .training-name {
            font-size: 22px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 25px;
        }
        .details-table {
            margin: 20px auto 0 auto;
            border-collapse: collapse;
            text-align: left;
        }
        .details-table td {
            padding: 6px 16px;
            font-size: 14px;
            color: #334155;
        }
        .details-table td.label {
            font-weight: bold;
            color: #1e293b;
            text-align: right;
        }
        .status-badge {
            color: #166534;
            background-color: #dcfce7;
            padding: 3px 12px;
            border-radius: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class='outer-border'>
        <div class='inner-border'>
            <div class='title'>TRAINING CERTIFICATE</div>
            <div class='subtitle'>This is to certify that</div>
            <div class='employee-name'>{$employeeName}</div>
            <div class='completion-text'>has successfully completed</div>
            <div class='training-name'>{$trainingName}</div>

            <table class='details-table'>
                <tr>
                    <td class='label'>Trainer:</td>
                    <td>{$trainerName}</td>
                </tr>
                <tr>
                    <td class='label'>Start Date:</td>
                    <td>{$startDate}</td>
                </tr>
                <tr>
                    <td class='label'>End Date:</td>
                    <td>{$endDate}</td>
                </tr>
                <tr>
                    <td class='label'>Completion Status:</td>
                    <td><span class='status-badge'>{$completionStatus}</span></td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>";

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
