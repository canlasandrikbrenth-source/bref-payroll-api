<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\CsvService;
use App\Services\PdfService;
use App\Services\S3Service;
use Bramus\Router\Router;

// Helper function to return JSON responses with standard CORS headers
function sendJson(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$router = new Router();
$csvService = new CsvService();

// Handle CORS Preflight
$router->options('/.*', function () {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    http_response_code(200);
    exit;
});

// Root welcome / health route
$router->get('/', function () {
    sendJson([
        'success' => true,
        'message' => 'AWS Lambda Bref PHP Employee and Payroll API',
        'author' => [
            'name' => 'Andrik Brenth Canlas',
            'course_section' => 'BSIT 4-A',
            'student_number' => '11-0251-543'
        ],
        'endpoints' => [
            'GET  /api/employees' => 'Return all employee records in JSON',
            'GET  /api/reports/payroll' => 'Generate a summary payroll report',
            'GET  /api/payslips/{payroll_id}' => 'Return an individual payslip in JSON',
            'POST /api/payslips/{payroll_id}/generate' => 'Generate PDF payslip, upload to S3, and return S3 URL'
        ]
    ]);
});

// 1. GET /api/employees - Return employee records in JSON
$router->get('/api/employees', function () use ($csvService) {
    try {
        $employees = $csvService->getAllEmployees();
        sendJson([
            'success' => true,
            'data' => $employees
        ]);
    } catch (\Throwable $e) {
        sendJson([
            'success' => false,
            'message' => 'Failed to load employees: ' . $e->getMessage()
        ], 500);
    }
});

// 2. GET /api/reports/payroll - Generate a payroll summary
$router->get('/api/reports/payroll', function () use ($csvService) {
    try {
        $summary = $csvService->getPayrollSummary();
        sendJson([
            'success' => true,
            'data' => $summary
        ]);
    } catch (\Throwable $e) {
        sendJson([
            'success' => false,
            'message' => 'Failed to generate payroll report: ' . $e->getMessage()
        ], 500);
    }
});

// 3. GET /api/payslips/{payroll_id} - Return individual payslip
$router->get('/api/payslips/{payroll_id}', function ($payrollId) use ($csvService) {
    try {
        $id = (int)$payrollId;
        $payslip = $csvService->getPayslip($id);

        if (!$payslip) {
            sendJson([
                'success' => false,
                'message' => "Payslip not found for payroll ID {$id}."
            ], 404);
        }

        sendJson([
            'success' => true,
            'data' => [
                'payroll_id' => $payslip['payroll_id'],
                'employee_name' => $payslip['employee_name'],
                'gross_pay' => $payslip['gross_pay'],
                'deductions' => $payslip['deductions'],
                'net_pay' => $payslip['net_pay']
            ]
        ]);
    } catch (\Throwable $e) {
        sendJson([
            'success' => false,
            'message' => 'Error retrieving payslip: ' . $e->getMessage()
        ], 500);
    }
});

// 4. POST /api/payslips/{payroll_id}/generate - Generate PDF payslip and upload to S3
$router->post('/api/payslips/{payroll_id}/generate', function ($payrollId) use ($csvService) {
    try {
        $id = (int)$payrollId;
        $payslip = $csvService->getPayslip($id);

        if (!$payslip) {
            sendJson([
                'success' => false,
                'message' => "Payroll record not found for ID {$id}."
            ], 404);
        }

        // Generate PDF using Dompdf
        $pdfService = new PdfService();
        $pdfContent = $pdfService->generatePayslipPdf($payslip);

        // Upload to Amazon S3 using AWS SDK
        $s3Service = new S3Service();
        $uploadResult = $s3Service->uploadPayslipPdf($id, $pdfContent);

        sendJson([
            'success' => true,
            'message' => 'Payslip generated and uploaded.',
            'data' => [
                'payroll_id' => $id,
                'payslip_file' => $uploadResult['url']
            ]
        ]);
    } catch (\Throwable $e) {
        sendJson([
            'success' => false,
            'message' => 'Error generating payslip PDF: ' . $e->getMessage()
        ], 500);
    }
});

// 404 handler
$router->set404(function () {
    sendJson([
        'success' => false,
        'message' => 'Endpoint not found.'
    ], 404);
});

// Run the router
$router->run();
