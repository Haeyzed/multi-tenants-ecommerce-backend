<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\HR\IndexPayrollRunRequest;
use App\Http\Requests\Tenant\HR\PayPayrollRunRequest;
use App\Http\Requests\Tenant\HR\StorePayrollRunRequest;
use App\Http\Resources\Tenant\HR\PayrollItemResource;
use App\Http\Resources\Tenant\HR\PayrollRunResource;
use App\Models\Tenant\Employee;
use App\Models\Tenant\PayrollItem;
use App\Models\Tenant\PayrollRun;
use App\Models\Tenant\User;
use App\Services\Tenant\HR\HrCsvExporter;
use App\Services\Tenant\HR\PayrollRunService;
use App\Services\Tenant\HR\PayslipPdfService;
use App\Support\ApiResponseSchema;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tenant HR payroll run endpoints.
 */
#[Group('HR')]
class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollRunService $payrollRunService,
        private readonly PayslipPdfService $payslips,
        private readonly HrCsvExporter $csv,
    ) {}

    #[Response(status: 200, description: 'Paginated payroll runs.', type: 'array{success: true, message: string, data: PayrollRunResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function index(IndexPayrollRunRequest $request): JsonResponse
    {
        $this->authorize('viewAny', PayrollRun::class);

        $runs = $this->payrollRunService->list($request->validated());

        return $this->success(
            PayrollRunResource::collection($runs->items()),
            'Payroll runs retrieved successfully.',
            $this->paginationMeta($runs),
        );
    }

    #[Response(status: 201, description: 'Created payroll run.', type: 'array{success: true, message: string, data: PayrollRunResource, meta: null, errors: null}')]
    public function store(StorePayrollRunRequest $request): JsonResponse
    {
        $this->authorize('create', PayrollRun::class);

        return $this->created(
            new PayrollRunResource($this->payrollRunService->create($request->validated())),
            'Payroll run created successfully.',
        );
    }

    #[Response(status: 200, description: 'A payroll run.', type: 'array{success: true, message: string, data: PayrollRunResource, meta: null, errors: null}')]
    public function show(PayrollRun $payroll_run): JsonResponse
    {
        $this->authorize('view', $payroll_run);

        return $this->success(
            new PayrollRunResource($this->payrollRunService->show($payroll_run)),
            'Payroll run retrieved successfully.',
        );
    }

    #[Response(status: 200, description: 'Regenerated payroll run.', type: 'array{success: true, message: string, data: PayrollRunResource, meta: null, errors: null}')]
    public function generate(PayrollRun $payroll_run): JsonResponse
    {
        $this->authorize('manage', PayrollRun::class);

        return $this->updated(
            new PayrollRunResource($this->payrollRunService->generate($payroll_run)),
            'Payroll run regenerated successfully.',
        );
    }

    #[Response(status: 200, description: 'Processed payroll run.', type: 'array{success: true, message: string, data: PayrollRunResource, meta: null, errors: null}')]
    public function process(PayrollRun $payroll_run): JsonResponse
    {
        $this->authorize('manage', PayrollRun::class);

        return $this->updated(
            new PayrollRunResource($this->payrollRunService->process($payroll_run, $this->actor())),
            'Payroll run processed successfully.',
        );
    }

    #[Response(status: 200, description: 'Paid payroll run.', type: 'array{success: true, message: string, data: PayrollRunResource, meta: null, errors: null}')]
    public function pay(PayPayrollRunRequest $request, PayrollRun $payroll_run): JsonResponse
    {
        $this->authorize('pay', $payroll_run);

        return $this->updated(
            new PayrollRunResource($this->payrollRunService->pay(
                $payroll_run,
                $this->actor(),
                $request->validated(),
            )),
            'Payroll run marked as paid successfully.',
        );
    }

    #[Response(status: 200, description: 'Approved payroll run.', type: 'array{success: true, message: string, data: PayrollRunResource, meta: null, errors: null}')]
    public function approve(PayrollRun $payroll_run): JsonResponse
    {
        $this->authorize('approve', $payroll_run);

        return $this->updated(
            new PayrollRunResource($this->payrollRunService->approve($payroll_run, $this->actor())),
            'Payroll run approved successfully.',
        );
    }

    #[Response(status: 200, description: 'Cancelled payroll run.', type: 'array{success: true, message: string, data: PayrollRunResource, meta: null, errors: null}')]
    public function cancel(PayrollRun $payroll_run): JsonResponse
    {
        $this->authorize('manage', PayrollRun::class);

        return $this->updated(
            new PayrollRunResource($this->payrollRunService->cancel($payroll_run)),
            'Payroll run cancelled successfully.',
        );
    }

    #[Response(status: 200, description: 'A payslip.', type: 'array{success: true, message: string, data: PayrollItemResource, meta: null, errors: null}')]
    public function showItem(PayrollRun $payroll_run, PayrollItem $payroll_item): JsonResponse
    {
        abort_unless($payroll_item->payroll_run_id === $payroll_run->id, 404);

        $this->authorize('view', $payroll_item);

        return $this->success(
            new PayrollItemResource($this->payrollRunService->showItem($payroll_item)),
            'Payslip retrieved successfully.',
        );
    }

    public function downloadItem(PayrollRun $payroll_run, PayrollItem $payroll_item): HttpResponse
    {
        abort_unless($payroll_item->payroll_run_id === $payroll_run->id, 404);

        $this->authorize('view', $payroll_item);

        return $this->payslips->download($this->payrollRunService->showItem($payroll_item));
    }

    public function paymentRegister(Request $request, PayrollRun $payroll_run): JsonResponse|StreamedResponse
    {
        $this->authorize('view', $payroll_run);

        $rows = $this->payrollRunService->paymentRegister($this->payrollRunService->show($payroll_run));

        if ($request->query('format') === 'csv') {
            return $this->csv->rows('payment-register-'.$payroll_run->reference.'.csv', $rows);
        }

        return $this->success($rows, 'Payment register retrieved successfully.');
    }

    #[Response(status: 200, description: 'Employee payslips.', type: 'array{success: true, message: string, data: PayrollItemResource[], meta: '.ApiResponseSchema::PAGINATION_META.', errors: null}')]
    public function employeeItems(Employee $employee): JsonResponse
    {
        $this->authorize('viewSalary', $employee);

        $items = $this->payrollRunService->listForEmployee($employee);

        return $this->success(
            PayrollItemResource::collection($items->items()),
            'Payslips retrieved successfully.',
            $this->paginationMeta($items),
        );
    }

    protected function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('tenant')->user();

        return $user;
    }
}
