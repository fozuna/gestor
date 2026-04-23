<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\DashboardRepository;

final class DashboardService
{
    public function __construct(private readonly DashboardRepository $repo = new DashboardRepository())
    {
    }

    public function kpis(int $tenantId): array
    {
        return $this->repo->kpis($tenantId);
    }

    public function dashboard(int $tenantId, array $filters): array
    {
        $year = (int)($filters['year'] ?? date('Y'));
        $projectStatus = trim((string)($filters['project_status'] ?? 'active'));
        $clientId = (int)($filters['client_id'] ?? 0);

        $data = $this->repo->dashboardData($tenantId, $year, $projectStatus, $clientId);
        $predicted = array_map('floatval', (array)($data['monthly_predicted'] ?? []));
        $realized = array_map('floatval', (array)($data['monthly_realized'] ?? []));

        $predictedTotal = array_sum($predicted);
        $realizedTotal = array_sum($realized);
        $receiveRate = $predictedTotal > 0 ? (($realizedTotal / $predictedTotal) * 100) : 0.0;
        $avgMonthly = $predictedTotal / 12.0;

        $bestMonthIndex = 1;
        $worstMonthIndex = 1;
        $bestValue = -1.0;
        $worstValue = PHP_FLOAT_MAX;
        for ($i = 0; $i < 12; $i += 1) {
            $value = (float)($predicted[$i] ?? 0);
            if ($value > $bestValue) {
                $bestValue = $value;
                $bestMonthIndex = $i + 1;
            }
            if ($value < $worstValue) {
                $worstValue = $value;
                $worstMonthIndex = $i + 1;
            }
        }

        $months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        return $data + [
            'receive_rate' => $receiveRate,
            'avg_monthly_billed' => $avgMonthly,
            'best_month_label' => $months[$bestMonthIndex - 1],
            'best_month_value' => max(0.0, $bestValue),
            'worst_month_label' => $months[$worstMonthIndex - 1],
            'worst_month_value' => max(0.0, $worstValue),
            'months_labels' => $months,
            'flow_dataset' => $this->flowDataset($months, $predicted, $realized),
        ];
    }

    /**
     * @return array<int, array{mes:string, previsto:float, realizado:float}>
     */
    private function flowDataset(array $months, array $predicted, array $realized): array
    {
        $rows = [];
        for ($i = 0; $i < 12; $i += 1) {
            $rows[] = [
                'mes' => (string)($months[$i] ?? ''),
                'previsto' => (float)($predicted[$i] ?? 0),
                'realizado' => (float)($realized[$i] ?? 0),
            ];
        }
        return $rows;
    }
}

