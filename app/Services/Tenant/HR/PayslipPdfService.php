<?php

declare(strict_types=1);

namespace App\Services\Tenant\HR;

use App\Models\Tenant\Employee;
use App\Models\Tenant\PayrollItem;
use App\Models\Tenant\PayrollItemLine;
use App\Models\Tenant\PayrollRun;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Renders a payslip PDF for download.
 */
class PayslipPdfService
{
    public function download(PayrollItem $item): Response
    {
        $item = $item->loadMissing(['employee.user', 'lines', 'payrollRun']);
        $run = $item->payrollRun;
        $filename = 'payslip-'.($run?->reference ?? $item->id).'-'.$item->employee_id.'.pdf';

        return Pdf::loadView('hr.payslip', $this->viewData($item))->download($filename);
    }

    public function output(PayrollItem $item): string
    {
        $item = $item->loadMissing(['employee.user', 'lines', 'payrollRun']);

        return Pdf::loadView('hr.payslip', $this->viewData($item))->output();
    }

    /**
     * @return array{item: PayrollItem, employee: Employee|null, run: PayrollRun|null, lines: Collection<int, PayrollItemLine>}
     */
    protected function viewData(PayrollItem $item): array
    {
        return [
            'item' => $item,
            'employee' => $item->employee,
            'run' => $item->payrollRun,
            'lines' => $item->lines,
        ];
    }
}
