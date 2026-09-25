<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\VisitServiceStatus;
use App\Enums\VisitStatus;
use App\Models\PatientVisit;
use App\Models\Service;
use App\Models\VisitService;

final class ServicesReportService
{
    /**
     * Cache for aggregated shift service statistics.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $shiftCache = [];

    /**
     * Compute full statistics for all services present in the given shifts.
     *
     * @param  array<int>  $shiftIds
     * @return array{
     *     services: array<int, array<string, mixed>>,
     *     overall: array<string, mixed>,
     *     top_for_chart: array{labels: array<int, string>, counts: array<int, int>, revenues: array<int, float>}
     * }
     */
    public function getReportData(array $shiftIds): array
    {
        $cacheKey = implode(',', $shiftIds);
        if (isset($this->shiftCache[$cacheKey])) {
            return $this->shiftCache[$cacheKey];
        }

        if (empty($shiftIds)) {
            $emptyData = [
                'services' => [],
                'overall' => [
                    'top_performed_service' => null,
                    'top_revenue_service' => null,
                    'total_performed_count' => 0,
                    'completed_visits_count' => 0,
                    'waiting_visits_count' => 0,
                    'cancelled_visits_count' => 0,
                    'total_payments' => 0.0,
                    'total_refunds' => 0.0,
                    'net_revenue' => 0.0,
                ],
                'top_for_chart' => [
                    'labels' => [],
                    'counts' => [],
                    'revenues' => [],
                ],
            ];

            $this->shiftCache[$cacheKey] = $emptyData;

            return $emptyData;
        }

        // Fetch all patient visits for the specified shifts along with visitServices and payments/invoice
        $visits = PatientVisit::query()
            ->whereIn('shift_id', $shiftIds)
            ->with([
                'visitServices.service.serviceCategory',
                'invoice',
                'payments',
            ])
            ->get();

        $serviceStats = [];
        $totalShiftPerformedCount = 0;
        $totalShiftPayments = 0.0;
        $totalShiftRefunds = 0.0;
        $completedVisitsAll = 0;
        $waitingVisitsAll = 0;
        $cancelledVisitsAll = 0;

        foreach ($visits as $visit) {
            $visitStatus = $visit->status;
            $visitStatusVal = $visitStatus instanceof VisitStatus ? $visitStatus->value : (string) $visitStatus;

            if ($visitStatusVal === VisitStatus::Completed->value) {
                $completedVisitsAll++;
            } elseif ($visitStatusVal === VisitStatus::Waiting->value) {
                $waitingVisitsAll++;
            } elseif ($visitStatusVal === VisitStatus::Cancelled->value) {
                $cancelledVisitsAll++;
            }

            // Sum payments and refunds for the visit
            $visitPayments = (float) $visit->payments
                ->where(fn ($p) => ($p->type !== 'refund' && (float) $p->amount > 0))
                ->sum('amount');

            $visitRefunds = abs((float) $visit->payments
                ->where(fn ($p) => ($p->type === 'refund' || (float) $p->amount < 0))
                ->sum('amount'));

            $activeServices = $visit->visitServices;
            $activeServicesCount = $activeServices->count();

            if ($activeServicesCount === 0) {
                continue;
            }

            // Calculate positive sum of totals for the visit services to distribute payments proportionally
            $totalServicesBilled = (float) $activeServices->sum(fn (VisitService $vs) => max(0.0, (float) $vs->total));

            foreach ($activeServices as $vs) {
                $serviceId = (int) $vs->service_id;
                $service = $vs->service;

                if (! isset($serviceStats[$serviceId])) {
                    $serviceStats[$serviceId] = [
                        'service_id' => $serviceId,
                        'name' => $service?->name ?? "خدمة #{$serviceId}",
                        'code' => $service?->code,
                        'category_name' => $service?->serviceCategory?->name,
                        'category_id' => $service?->category_id,
                        'base_price' => (float) ($service?->base_price ?? 0),
                        'performed_count' => 0,
                        'completed_visits_count' => 0,
                        'waiting_visits_count' => 0,
                        'cancelled_visits_count' => 0,
                        'total_payments' => 0.0,
                        'total_refunds' => 0.0,
                        'net_revenue' => 0.0,
                        'percent_of_services' => 0.0,
                        'visit_ids' => [],
                    ];
                }

                $vsStatus = $vs->status;
                $vsStatusVal = $vsStatus instanceof VisitServiceStatus ? $vsStatus->value : (string) $vsStatus;
                $qty = max(1, (int) $vs->quantity);

                // Performed check: Completed service, or visit completed and service not cancelled
                $isPerformed = ($vsStatusVal === VisitServiceStatus::Completed->value) ||
                    ($visitStatusVal === VisitStatus::Completed->value && $vsStatusVal !== VisitServiceStatus::Cancelled->value);

                if ($isPerformed) {
                    $serviceStats[$serviceId]['performed_count'] += $qty;
                    $totalShiftPerformedCount += $qty;
                }

                // Track distinct visits by status
                if (! in_array($visit->id, $serviceStats[$serviceId]['visit_ids'], true)) {
                    $serviceStats[$serviceId]['visit_ids'][] = $visit->id;

                    if ($visitStatusVal === VisitStatus::Completed->value) {
                        $serviceStats[$serviceId]['completed_visits_count']++;
                    } elseif ($visitStatusVal === VisitStatus::Waiting->value) {
                        $serviceStats[$serviceId]['waiting_visits_count']++;
                    } elseif ($visitStatusVal === VisitStatus::Cancelled->value) {
                        $serviceStats[$serviceId]['cancelled_visits_count']++;
                    }
                }

                // Proportional financial allocation
                $vsTotal = max(0.0, (float) $vs->total);
                $ratio = ($totalServicesBilled > 0)
                    ? ($vsTotal / $totalServicesBilled)
                    : (1.0 / $activeServicesCount);

                $allocatedPayment = $ratio * $visitPayments;
                $allocatedRefund = $ratio * $visitRefunds;

                $serviceStats[$serviceId]['total_payments'] += $allocatedPayment;
                $serviceStats[$serviceId]['total_refunds'] += $allocatedRefund;
            }
        }

        // Post-process metrics: calculate net_revenue, percent_of_services, and total sums
        foreach ($serviceStats as $id => &$s) {
            $s['net_revenue'] = round($s['total_payments'] - $s['total_refunds'], 2);
            $s['total_payments'] = round($s['total_payments'], 2);
            $s['total_refunds'] = round($s['total_refunds'], 2);
            $s['percent_of_services'] = ($totalShiftPerformedCount > 0)
                ? round(($s['performed_count'] / $totalShiftPerformedCount) * 100, 1)
                : 0.0;

            $totalShiftPayments += $s['total_payments'];
            $totalShiftRefunds += $s['total_refunds'];
        }
        unset($s);

        // Sort by performed_count descending
        uasort($serviceStats, fn ($a, $b) => $b['performed_count'] <=> $a['performed_count']);

        // Identify Top Performed Service
        $topPerformed = null;
        if (! empty($serviceStats)) {
            $firstPerformed = reset($serviceStats);
            if ($firstPerformed['performed_count'] > 0) {
                $topPerformed = [
                    'name' => $firstPerformed['name'],
                    'count' => $firstPerformed['performed_count'],
                    'percent' => $firstPerformed['percent_of_services'],
                ];
            }
        }

        // Identify Top Revenue Service
        $topRevenue = null;
        $highestNet = -INF;
        foreach ($serviceStats as $stat) {
            if ($stat['net_revenue'] > $highestNet && $stat['net_revenue'] > 0) {
                $highestNet = $stat['net_revenue'];
                $totalNet = max(0.01, $totalShiftPayments - $totalShiftRefunds);
                $percent = round(($stat['net_revenue'] / $totalNet) * 100, 1);
                $topRevenue = [
                    'name' => $stat['name'],
                    'revenue' => $stat['net_revenue'],
                    'percent' => min(100.0, $percent),
                ];
            }
        }

        // Top 8 services for chart
        $chartLabels = [];
        $chartCounts = [];
        $chartRevenues = [];
        $index = 0;
        $otherCount = 0;
        $otherRevenue = 0.0;

        foreach ($serviceStats as $stat) {
            if ($index < 8) {
                $chartLabels[] = $stat['name'];
                $chartCounts[] = $stat['performed_count'];
                $chartRevenues[] = $stat['net_revenue'];
            } else {
                $otherCount += $stat['performed_count'];
                $otherRevenue += $stat['net_revenue'];
            }
            $index++;
        }

        if ($otherCount > 0) {
            $chartLabels[] = 'خدمات أخرى';
            $chartCounts[] = $otherCount;
            $chartRevenues[] = round($otherRevenue, 2);
        }

        $result = [
            'services' => $serviceStats,
            'overall' => [
                'top_performed_service' => $topPerformed,
                'top_revenue_service' => $topRevenue,
                'total_performed_count' => $totalShiftPerformedCount,
                'completed_visits_count' => $completedVisitsAll,
                'waiting_visits_count' => $waitingVisitsAll,
                'cancelled_visits_count' => $cancelledVisitsAll,
                'total_payments' => round($totalShiftPayments, 2),
                'total_refunds' => round($totalShiftRefunds, 2),
                'net_revenue' => round($totalShiftPayments - $totalShiftRefunds, 2),
            ],
            'top_for_chart' => [
                'labels' => $chartLabels,
                'counts' => $chartCounts,
                'revenues' => $chartRevenues,
            ],
        ];

        $this->shiftCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Compute summary metrics for a specific single service.
     *
     * @param  array<int>  $shiftIds
     * @return array{
     *     total_visits: int,
     *     unique_patients: int,
     *     performed_count: int,
     *     total_payments: float,
     *     total_refunds: float,
     *     net_revenue: float
     * }
     */
    public function getServiceDetailSummary(int $serviceId, array $shiftIds = []): array
    {
        $query = VisitService::query()
            ->where('service_id', $serviceId)
            ->whereHas('visit', function ($q) use ($shiftIds): void {
                if (! empty($shiftIds)) {
                    $q->whereIn('shift_id', $shiftIds);
                }
            })
            ->with(['visit.invoice', 'visit.payments']);

        $visitServices = $query->get();

        $visitIds = [];
        $patientIds = [];
        $completedVisits = 0;
        $waitingVisits = 0;
        $cancelledVisits = 0;
        $performedCount = 0;
        $totalPayments = 0.0;
        $totalRefunds = 0.0;

        foreach ($visitServices as $vs) {
            $visit = $vs->visit;
            if (! $visit) {
                continue;
            }

            $vsStatus = $vs->status;
            $vsStatusVal = $vsStatus instanceof VisitServiceStatus ? $vsStatus->value : (string) $vsStatus;
            $visitStatusVal = $visit->status instanceof VisitStatus ? $visit->status->value : (string) $visit->status;

            if (! isset($visitIds[$visit->id])) {
                $visitIds[$visit->id] = true;
                if ($visitStatusVal === VisitStatus::Completed->value) {
                    $completedVisits++;
                } elseif ($visitStatusVal === VisitStatus::Waiting->value) {
                    $waitingVisits++;
                } elseif ($visitStatusVal === VisitStatus::Cancelled->value) {
                    $cancelledVisits++;
                }
            }

            if ($visit->patient_id) {
                $patientIds[$visit->patient_id] = true;
            }

            $qty = max(1, (int) $vs->quantity);
            if ($vsStatusVal === VisitServiceStatus::Completed->value ||
                ($visitStatusVal === VisitStatus::Completed->value && $vsStatusVal !== VisitServiceStatus::Cancelled->value)) {
                $performedCount += $qty;
            }

            // Proportional payment calculation
            $activeServices = $visit->visitServices;
            $activeServicesCount = max(1, $activeServices->count());
            $totalServicesBilled = (float) $activeServices->sum(fn (VisitService $item) => max(0.0, (float) $item->total));

            $visitPayments = (float) $visit->payments
                ->where(fn ($p) => ($p->type !== 'refund' && (float) $p->amount > 0))
                ->sum('amount');

            $visitRefunds = abs((float) $visit->payments
                ->where(fn ($p) => ($p->type === 'refund' || (float) $p->amount < 0))
                ->sum('amount'));

            $vsTotal = max(0.0, (float) $vs->total);
            $ratio = ($totalServicesBilled > 0)
                ? ($vsTotal / $totalServicesBilled)
                : (1.0 / $activeServicesCount);

            $totalPayments += $ratio * $visitPayments;
            $totalRefunds += $ratio * $visitRefunds;
        }

        return [
            'total_visits' => count($visitIds),
            'completed_visits' => $completedVisits,
            'waiting_visits' => $waitingVisits,
            'cancelled_visits' => $cancelledVisits,
            'unique_patients' => count($patientIds),
            'performed_count' => $performedCount,
            'total_payments' => round($totalPayments, 2),
            'total_refunds' => round($totalRefunds, 2),
            'net_revenue' => round($totalPayments - $totalRefunds, 2),
        ];
    }
}
