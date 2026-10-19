<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService
{
    /**
     * Generate PDF content as string for an individual payslip
     */
    public function generatePayslipPdf(array $payslipData): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);

        $html = $this->renderHtml($payslipData);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Render the HTML template for the payslip
     */
    private function renderHtml(array $data): string
    {
        $payrollId = htmlspecialchars((string)($data['payroll_id'] ?? 'N/A'));
        $employeeName = htmlspecialchars((string)($data['employee_name'] ?? 'N/A'));
        $employeeId = htmlspecialchars((string)($data['employee_id'] ?? 'N/A'));
        $employeeEmail = htmlspecialchars((string)($data['employee_email'] ?? 'N/A'));
        $periodStart = htmlspecialchars((string)($data['pay_period_start'] ?? 'N/A'));
        $periodEnd = htmlspecialchars((string)($data['pay_period_end'] ?? 'N/A'));
        $grossPay = number_format((float)($data['gross_pay'] ?? 0), 2);
        $deductions = number_format((float)($data['deductions'] ?? 0), 2);
        $netPay = number_format((float)($data['net_pay'] ?? 0), 2);
        $currentDate = date('F d, Y h:i A');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip #{$payrollId}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 30px;
            color: #2d3748;
            font-size: 13px;
            line-height: 1.5;
        }
        .header {
            border-bottom: 3px solid #2b6cb0;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2b6cb0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-title {
            float: right;
            font-size: 18px;
            color: #4a5568;
            font-weight: 600;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .info-section {
            margin-bottom: 25px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 6px 10px;
            vertical-align: top;
        }
        .info-label {
            font-weight: bold;
            color: #718096;
            width: 25%;
        }
        .info-value {
            color: #1a202c;
            width: 25%;
        }
        .payroll-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .payroll-table th {
            background-color: #ebf8ff;
            color: #2b6cb0;
            text-align: left;
            padding: 10px 12px;
            font-size: 13px;
            border-top: 1px solid #bee3f8;
            border-bottom: 2px solid #bee3f8;
        }
        .payroll-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            background-color: #f7fafc;
            font-weight: bold;
        }
        .net-pay-banner {
            background-color: #2b6cb0;
            color: #ffffff;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 30px;
        }
        .net-pay-banner .label {
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .net-pay-banner .amount {
            font-size: 26px;
            font-weight: bold;
            float: right;
            margin-top: -5px;
        }
        .footer {
            margin-top: 50px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            font-size: 11px;
            color: #a0aec0;
            text-align: center;
        }
        .student-badge {
            background-color: #edf2f7;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 20px;
            font-size: 12px;
            color: #4a5568;
        }
    </style>
</head>
<body>
    <div class="header clearfix">
        <div class="doc-title">OFFICIAL PAYSLIP</div>
        <div class="company-name">ENTERPRISE HR SYSTEM</div>
        <div style="font-size: 11px; color: #718096; margin-top: 4px;">AWS Lambda Serverless Payroll System</div>
    </div>

    <div class="info-section">
        <table class="info-table">
            <tr>
                <td class="info-label">Payslip ID:</td>
                <td class="info-value">#{$payrollId}</td>
                <td class="info-label">Pay Period Start:</td>
                <td class="info-value">{$periodStart}</td>
            </tr>
            <tr>
                <td class="info-label">Employee ID:</td>
                <td class="info-value">{$employeeId}</td>
                <td class="info-label">Pay Period End:</td>
                <td class="info-value">{$periodEnd}</td>
            </tr>
            <tr>
                <td class="info-label">Employee Name:</td>
                <td class="info-value"><strong>{$employeeName}</strong></td>
                <td class="info-label">Generated On:</td>
                <td class="info-value">{$currentDate}</td>
            </tr>
            <tr>
                <td class="info-label">Email Address:</td>
                <td class="info-value">{$employeeEmail}</td>
                <td class="info-label">Status:</td>
                <td class="info-value"><span style="color: #38a169; font-weight: bold;">Processed & Paid</span></td>
            </tr>
        </table>
    </div>

    <table class="payroll-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Earnings</th>
                <th class="text-right">Deductions</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Basic Gross Pay (Current Period)</td>
                <td class="text-right">$$grossPay</td>
                <td class="text-right">-</td>
            </tr>
            <tr>
                <td>Standard Payroll Deductions (Gross - Net)</td>
                <td class="text-right">-</td>
                <td class="text-right">$$deductions</td>
            </tr>
            <tr class="total-row">
                <td>Total Computations</td>
                <td class="text-right">$$grossPay</td>
                <td class="text-right">$$deductions</td>
            </tr>
        </tbody>
    </table>

    <div class="net-pay-banner clearfix">
        <span class="label">Total Net Take-Home Pay</span>
        <span class="amount">$$netPay</span>
    </div>

    <div class="student-badge">
        <strong>Academic Lab Verification:</strong><br>
        Student: Andrik Brenth Canlas &nbsp;|&nbsp; Course: BSIT 4-A &nbsp;|&nbsp; Student Number: 11-0251-543<br>
        Deployed via Bref PHP 8.2 on AWS Lambda & API Gateway. Payslip stored in Amazon S3.
    </div>

    <div class="footer">
        This document is an electronic payslip generated automatically by the AWS Lambda Payroll Service.<br>
        Confidential &copy; 2026 Enterprise HR System. All rights reserved.
    </div>
</body>
</html>
HTML;
    }
}
