<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Analytics\AnalyticsReportRequest;
use App\Services\Tenant\Analytics\AnalyticsCsvExporter;
use App\Services\Tenant\Analytics\CommerceReportService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Commerce analytics reporting endpoints (gated by the advanced-reports feature).
 */
class AnalyticsController extends Controller
{
    /**
     * Create a new class instance.
     *
     * @param  CommerceReportService  $reports
     * @param  AnalyticsCsvExporter  $exporter
     */
    public function __construct(
        private readonly CommerceReportService $reports,
        private readonly AnalyticsCsvExporter $exporter,
    ) {}

    /**
     * Headline sales, customer, and inventory figures for a period.
     *
     * @param  AnalyticsReportRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Commerce overview.',
        type: 'array{success: true, message: string, data: array{sales: array<string, mixed>, customers: array<string, mixed>, inventory: array<string, mixed>}, meta: array{period: string, from: string, to: string, timezone: string}, errors: null}',
    )]
    public function overview(AnalyticsReportRequest $request): JsonResponse
    {
        $range = $request->dateRange();

        return $this->success(
            [
                'sales' => $this->reports->salesSummary($range),
                'customers' => $this->reports->customerMetrics($range),
                'inventory' => $this->reports->inventoryMetrics(),
            ],
            'Analytics overview retrieved successfully.',
            $range->toArray(),
        );
    }

    /**
     * Sales summary for a period, optionally exported as CSV.
     *
     * @param  AnalyticsReportRequest  $request
     * @return JsonResponse|StreamedResponse
     */
    #[Response(
        status: 200,
        description: 'Sales summary.',
        type: 'array{success: true, message: string, data: array<string, mixed>, meta: array{period: string, from: string, to: string, timezone: string}, errors: null}',
    )]
    public function sales(AnalyticsReportRequest $request): JsonResponse|StreamedResponse
    {
        $range = $request->dateRange();
        $summary = $this->reports->salesSummary($range);

        if ($request->wantsCsv()) {
            return $this->exporter->summary('sales-summary.csv', $summary);
        }

        return $this->success($summary, 'Sales summary retrieved successfully.', $range->toArray());
    }

    /**
     * Sales grouped into day, week, or month buckets.
     *
     * @param  AnalyticsReportRequest  $request
     * @return JsonResponse|StreamedResponse
     */
    #[Response(
        status: 200,
        description: 'Sales time series.',
        type: 'array{success: true, message: string, data: array<string, mixed>[], meta: array{period: string, from: string, to: string, timezone: string}, errors: null}',
    )]
    public function salesBreakdown(AnalyticsReportRequest $request): JsonResponse|StreamedResponse
    {
        $range = $request->dateRange();
        $rows = $this->reports->salesBreakdown($range, $request->reportInterval());

        if ($request->wantsCsv()) {
            return $this->exporter->rows('sales-breakdown.csv', $rows);
        }

        return $this->success(
            $rows,
            'Sales breakdown retrieved successfully.',
            $range->toArray() + ['group_by' => $request->reportInterval()->value],
        );
    }

    /**
     * Customer acquisition and retention figures.
     *
     * @param  AnalyticsReportRequest  $request
     * @return JsonResponse|StreamedResponse
     */
    #[Response(
        status: 200,
        description: 'Customer metrics.',
        type: 'array{success: true, message: string, data: array<string, mixed>, meta: array{period: string, from: string, to: string, timezone: string}, errors: null}',
    )]
    public function customers(AnalyticsReportRequest $request): JsonResponse|StreamedResponse
    {
        $range = $request->dateRange();
        $metrics = $this->reports->customerMetrics($range);

        if ($request->wantsCsv()) {
            return $this->exporter->summary('customer-metrics.csv', $metrics);
        }

        return $this->success($metrics, 'Customer metrics retrieved successfully.', $range->toArray());
    }

    /**
     * Best selling products for a period.
     *
     * @param  AnalyticsReportRequest  $request
     * @return JsonResponse|StreamedResponse
     */
    #[Response(
        status: 200,
        description: 'Top selling products.',
        type: 'array{success: true, message: string, data: array<string, mixed>[], meta: array{period: string, from: string, to: string, timezone: string}, errors: null}',
    )]
    public function products(AnalyticsReportRequest $request): JsonResponse|StreamedResponse
    {
        $range = $request->dateRange();
        $rows = $this->reports->productMetrics($range, $request->limit());

        if ($request->wantsCsv()) {
            return $this->exporter->rows('product-metrics.csv', $rows);
        }

        return $this->success($rows, 'Product metrics retrieved successfully.', $range->toArray());
    }

    /**
     * Current stock position across warehouses.
     *
     * @param  AnalyticsReportRequest  $request
     * @return JsonResponse|StreamedResponse
     */
    #[Response(
        status: 200,
        description: 'Inventory metrics.',
        type: 'array{success: true, message: string, data: array<string, mixed>, meta: null, errors: null}',
    )]
    public function inventory(AnalyticsReportRequest $request): JsonResponse|StreamedResponse
    {
        $metrics = $this->reports->inventoryMetrics();

        if ($request->wantsCsv()) {
            return $this->exporter->summary('inventory-metrics.csv', $metrics);
        }

        return $this->success($metrics, 'Inventory metrics retrieved successfully.');
    }

    /**
     * Marketplace commission aggregates, optionally scoped to one seller.
     *
     * @param  AnalyticsReportRequest  $request
     * @return JsonResponse|StreamedResponse
     */
    #[Response(
        status: 200,
        description: 'Marketplace metrics.',
        type: 'array{success: true, message: string, data: array<string, mixed>|null, meta: array{period: string, from: string, to: string, timezone: string}, errors: null}',
    )]
    public function marketplace(AnalyticsReportRequest $request): JsonResponse|StreamedResponse
    {
        $range = $request->dateRange();
        $sellerId = $request->input('seller_id');
        $metrics = $this->reports->marketplaceMetrics($range, $sellerId === null ? null : (int) $sellerId);

        if ($metrics === null) {
            return $this->error('Marketplace reporting is not available for this tenant.', 404);
        }

        if ($request->wantsCsv()) {
            return $this->exporter->summary('marketplace-metrics.csv', $metrics);
        }

        return $this->success($metrics, 'Marketplace metrics retrieved successfully.', $range->toArray());
    }

    /**
     * Coupon redemption aggregates.
     *
     * @param  AnalyticsReportRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Coupon metrics.',
        type: 'array{success: true, message: string, data: array<string, mixed>, meta: array{period: string, from: string, to: string, timezone: string}, errors: null}',
    )]
    public function coupons(AnalyticsReportRequest $request): JsonResponse
    {
        $range = $request->dateRange();

        return $this->success(
            $this->reports->couponMetrics($range),
            'Coupon metrics retrieved successfully.',
            $range->toArray(),
        );
    }

    /**
     * Payment capture aggregates.
     *
     * @param  AnalyticsReportRequest  $request
     * @return JsonResponse
     */
    #[Response(
        status: 200,
        description: 'Payment metrics.',
        type: 'array{success: true, message: string, data: array<string, mixed>, meta: array{period: string, from: string, to: string, timezone: string}, errors: null}',
    )]
    public function payments(AnalyticsReportRequest $request): JsonResponse
    {
        $range = $request->dateRange();

        return $this->success(
            $this->reports->paymentMetrics($range),
            'Payment metrics retrieved successfully.',
            $range->toArray(),
        );
    }
}
