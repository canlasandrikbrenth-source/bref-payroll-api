<?php

namespace App\Services;

class CsvService
{
    private string $dataPath;
    private ?array $employeesCache = null;
    private ?array $payrollCache = null;
    private ?array $departmentsCache = null;
    private ?array $positionsCache = null;

    public function __construct(?string $dataPath = null)
    {
        $this->dataPath = $dataPath ?? dirname(__DIR__, 2) . '/data';
    }

    /**
     * Read CSV file and return associative array
     */
    private function readCsv(string $filename): array
    {
        $filepath = $this->dataPath . '/' . $filename;
        if (!file_exists($filepath)) {
            // Fallback to project root if not in data/
            $filepath = dirname($this->dataPath) . '/' . $filename;
            if (!file_exists($filepath)) {
                return [];
            }
        }

        $records = [];
        if (($handle = fopen($filepath, 'r')) !== false) {
            $headers = fgetcsv($handle);
            if ($headers !== false) {
                // Trim header names for safety
                $headers = array_map('trim', $headers);
                while (($data = fgetcsv($handle)) !== false) {
                    if (count($data) === count($headers)) {
                        $records[] = array_combine($headers, $data);
                    }
                }
            }
            fclose($handle);
        }

        return $records;
    }

    /**
     * Get all employees
     */
    public function getAllEmployees(): array
    {
        if ($this->employeesCache === null) {
            $raw = $this->readCsv('employees.csv');
            $this->employeesCache = array_map(function ($row) {
                return [
                    'employee_id' => (int)($row['id'] ?? 0),
                    'full_name' => $row['full_name'] ?? '',
                    'email' => $row['email'] ?? '',
                    'position_id' => isset($row['position_id']) && $row['position_id'] !== '' ? (int)$row['position_id'] : null,
                    'department_id' => isset($row['department_id']) && $row['department_id'] !== '' ? (int)$row['department_id'] : null,
                    'manager_id' => isset($row['manager_id']) && $row['manager_id'] !== '' ? (int)$row['manager_id'] : null,
                ];
            }, $raw);
        }

        return $this->employeesCache;
    }

    /**
     * Get employee by ID
     */
    public function getEmployeeById(int $employeeId): ?array
    {
        $employees = $this->getAllEmployees();
        foreach ($employees as $emp) {
            if ($emp['employee_id'] === $employeeId) {
                return $emp;
            }
        }
        return null;
    }

    /**
     * Get all payroll records
     */
    public function getAllPayroll(): array
    {
        if ($this->payrollCache === null) {
            $this->payrollCache = $this->readCsv('payroll.csv');
        }
        return $this->payrollCache;
    }

    /**
     * Get payroll summary report
     */
    public function getPayrollSummary(): array
    {
        $records = $this->getAllPayroll();
        $totalRecords = count($records);
        $totalGross = 0.0;
        $totalNet = 0.0;

        foreach ($records as $row) {
            $totalGross += (float)($row['gross_pay'] ?? 0);
            $totalNet += (float)($row['net_pay'] ?? 0);
        }

        $totalDeductions = $totalGross - $totalNet;

        return [
            'total_payroll_records' => $totalRecords,
            'total_gross_pay' => round($totalGross, 2),
            'total_deductions' => round($totalDeductions, 2),
            'total_net_pay' => round($totalNet, 2),
        ];
    }

    /**
     * Get individual payslip details by payroll ID
     */
    public function getPayslip(int $payrollId): ?array
    {
        $payrollList = $this->getAllPayroll();
        $foundPayroll = null;

        foreach ($payrollList as $p) {
            if ((int)($p['id'] ?? 0) === $payrollId) {
                $foundPayroll = $p;
                break;
            }
        }

        if (!$foundPayroll) {
            return null;
        }

        $employeeId = (int)($foundPayroll['employee_id'] ?? 0);
        $employee = $this->getEmployeeById($employeeId);

        $grossPay = (float)($foundPayroll['gross_pay'] ?? 0);
        $netPay = (float)($foundPayroll['net_pay'] ?? 0);
        $deductions = round($grossPay - $netPay, 2);

        return [
            'payroll_id' => $payrollId,
            'employee_id' => $employeeId,
            'employee_name' => $employee['full_name'] ?? ('Employee #' . $employeeId),
            'employee_email' => $employee['email'] ?? null,
            'department_id' => $employee['department_id'] ?? null,
            'pay_period_start' => $foundPayroll['pay_period_start'] ?? '',
            'pay_period_end' => $foundPayroll['pay_period_end'] ?? '',
            'gross_pay' => round($grossPay, 2),
            'deductions' => round($deductions, 2),
            'net_pay' => round($netPay, 2),
        ];
    }
}
