<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\ClientProfileRepository;

final class ClientProfileService
{
    public function __construct(
        private readonly ClientProfileRepository $profile = new ClientProfileRepository(),
        private readonly ClientService $clients = new ClientService(),
        private readonly ProjectService $projects = new ProjectService(),
        private readonly TaskService $tasks = new TaskService()
    ) {
    }

    public function build(int $tenantId, int $clientId, string $startDate, string $endDate): array
    {
        $client = $this->clients->findById($tenantId, $clientId);
        $financial = $this->profile->financialSummary($tenantId, $clientId);
        $installmentsHistory = $this->profile->installmentsHistoryByClient($tenantId, $clientId, $startDate, $endDate);
        $installments = $this->profile->installmentsByClient($tenantId, $clientId, $startDate, $endDate);
        $projects = $this->projects->listByClient($tenantId, $clientId);
        $tasks = $this->tasks->listByClient($tenantId, $clientId, $startDate, $endDate);

        $billableTasks = array_values(array_filter($tasks, static fn(array $task): bool => ($task['billing_type'] ?? 'informative') === 'billable'));
        $informativeTasks = array_values(array_filter($tasks, static fn(array $task): bool => ($task['billing_type'] ?? 'informative') !== 'billable'));

        return [
            'client' => $client,
            'financial' => $financial,
            'installmentsHistory' => $installmentsHistory,
            'installments' => $installments,
            'projects' => $projects,
            'billableTasks' => $billableTasks,
            'informativeTasks' => $informativeTasks,
            'billableTasksTotal' => array_reduce(
                $billableTasks,
                static fn(float $carry, array $task): float => $carry + (float)($task['billable_amount'] ?? 0),
                0.0
            ),
        ];
    }
}
