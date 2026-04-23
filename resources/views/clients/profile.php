<?php
$active = 'clients';
ob_start();
?>
<?php
$slot = (function () use ($profile, $filter, $error, $ok): string {
    $client = $profile['client'];
    $financial = $profile['financial'];
    $projects = $profile['projects'];
    $installments = $profile['installments'];
    $installmentsHistory = $profile['installmentsHistory'];
    $billableTasks = $profile['billableTasks'];
    $informativeTasks = $profile['informativeTasks'];
    $billableTasksTotal = $profile['billableTasksTotal'];
    $projectStatusLabels = [
        'active' => 'Em andamento',
        'paused' => 'Pausado',
        'done' => 'Concluído',
        'cancelled' => 'Cancelado',
        'inactive' => 'Inativo',
    ];
    $taskStatusLabels = [
        'todo' => 'A fazer',
        'doing' => 'Em progresso',
        'done' => 'Concluído',
    ];
    ob_start();
?>
  <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
    <div>
      <div class="text-sm text-zinc-500">Painel do cliente</div>
      <div class="mt-1 text-2xl font-semibold text-zinc-900"><?= htmlspecialchars((string)($client['name'] ?? 'Cliente'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars((string)($client['email'] ?? $client['phone'] ?? 'Sem contato principal'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <div class="flex gap-2">
      <a href="/clients" class="icon-btn icon-btn--md" title="Voltar para clientes" aria-label="Voltar para clientes">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m15 18-6-6 6-6"/></svg>
        <span class="sr-only">Voltar para clientes</span>
      </a>
      <a href="/projetos" class="icon-btn icon-btn--md icon-btn--primary" title="Novo projeto" aria-label="Criar novo projeto">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 5v14M5 12h14"/></svg>
        <span class="sr-only">Novo projeto</span>
      </a>
    </div>
  </div>

  <?php if ((float)($financial['overdue_total'] ?? 0) > 0): ?>
    <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      <span class="font-semibold">⚠ Alerta financeiro:</span>
      Este cliente possui R$ <?= number_format((float)($financial['overdue_total'] ?? 0), 2, ',', '.') ?> em parcelas vencidas.
    </div>
  <?php endif; ?>

  <?php if (!empty($ok)): ?>
    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= htmlspecialchars((string)$ok, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="mt-6 grid gap-4 xl:grid-cols-4">
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Parcelas pendentes</div>
      <div class="mt-3 text-3xl font-semibold text-zinc-900"><?= (int)($financial['pending_installments'] ?? 0) ?></div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Valor pendente</div>
      <div class="mt-3 text-3xl font-semibold text-amber-600">R$ <?= number_format((float)($financial['pending_total'] ?? 0), 2, ',', '.') ?></div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Valor vencido</div>
      <div class="mt-3 text-3xl font-semibold text-red-600">R$ <?= number_format((float)($financial['overdue_total'] ?? 0), 2, ',', '.') ?></div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Tarefas cobráveis</div>
      <div class="mt-3 text-3xl font-semibold text-[#FE5516]">R$ <?= number_format((float)$billableTasksTotal, 2, ',', '.') ?></div>
    </div>
  </div>

  <div class="mt-6 grid gap-4 xl:grid-cols-[1.5fr_1fr]">
    <div class="space-y-4">
      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-sm font-semibold text-zinc-900">Parcelas do cliente</div>
            <div class="mt-1 text-sm text-zinc-500">Indicadores visuais: ✓ paga, ⏳ pendente e ⚠ vencida.</div>
          </div>
          <form method="GET" action="/clients/profile" class="grid gap-2 sm:grid-cols-3" data-loading-form>
            <input type="hidden" name="id" value="<?= (int)($client['id'] ?? 0) ?>" />
            <label class="text-xs text-zinc-500">
              Data inicial
              <input type="date" lang="pt-BR" required name="start_date" value="<?= htmlspecialchars((string)$filter['start_date'], ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
            </label>
            <label class="text-xs text-zinc-500">
              Data final
              <input type="date" lang="pt-BR" required name="end_date" value="<?= htmlspecialchars((string)$filter['end_date'], ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
            </label>
            <div class="flex items-end gap-2">
              <button type="submit" data-loading-button data-loading-text="Buscando..." class="icon-btn icon-btn--md icon-btn--primary" title="Buscar histórico" aria-label="Buscar histórico financeiro">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <span class="sr-only">Buscar histórico</span>
              </button>
              <a
                href="/clients/profile/tasks-pdf?id=<?= (int)($client['id'] ?? 0) ?>&start_date=<?= urlencode((string)$filter['start_date']) ?>&end_date=<?= urlencode((string)$filter['end_date']) ?>"
                class="icon-btn icon-btn--md"
                target="_blank"
                rel="noopener"
                title="Exportar PDF"
                aria-label="Exportar relatório de tarefas em PDF"
              >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M5 13V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-1"/><path d="M9 17h6M9 13h3"/></svg>
                <span class="sr-only">Exportar PDF</span>
              </a>
            </div>
            <div class="sm:col-span-3 hidden rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500" data-loading-indicator>Buscando parcelas e histórico financeiro...</div>
          </form>
        </div>

        <div class="mt-4 space-y-3">
          <?php if ($installments === []): ?>
            <div class="rounded-xl border border-dashed border-zinc-200 px-4 py-6 text-sm text-zinc-500">Nenhuma parcela encontrada para o período selecionado.</div>
          <?php else: ?>
            <?php foreach ($installments as $installment): ?>
              <?php
              $status = (string)$installment['status'];
              $statusClass = $status === 'paid'
                  ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                  : ($status === 'overdue' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700');
              $icon = $status === 'paid' ? '✓' : ($status === 'overdue' ? '⚠' : '⏳');
              $remaining = max(0, round((float)$installment['amount_total'] - (float)$installment['amount_paid'], 2));
              ?>
              <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <div class="flex items-center gap-2">
                      <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium <?= $statusClass ?>">
                        <span><?= $icon ?></span>
                        <span><?= $status === 'paid' ? 'Paga' : ($status === 'overdue' ? 'Vencida' : 'Pendente') ?></span>
                      </span>
                      <div class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars((string)($installment['project_name'] ?? $installment['task_title'] ?? 'Sem origem'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)($installment['description'] ?? 'Parcela'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="mt-2 flex flex-wrap gap-4 text-xs text-zinc-500">
                      <span>Vencimento: <?= htmlspecialchars(\App\Helpers\Security::formatDate((string)$installment['due_date']), ENT_QUOTES, 'UTF-8') ?></span>
                      <span>Referência: <?= str_pad((string)$installment['reference_month'], 2, '0', STR_PAD_LEFT) ?>/<?= htmlspecialchars((string)$installment['reference_year'], ENT_QUOTES, 'UTF-8') ?></span>
                      <span>Pago: R$ <?= number_format((float)$installment['amount_paid'], 2, ',', '.') ?></span>
                      <?php if ($status === 'paid' && (int)($installment['payment_id'] ?? 0) > 0): ?>
                        <a
                          href="/finance/payments/<?= (int)$installment['payment_id'] ?>/receipt?return_to=<?= urlencode('/clients/profile?id=' . (int)($client['id'] ?? 0)) ?>"
                          class="icon-btn icon-btn--sm"
                          title="Baixar recibo"
                          aria-label="Baixar recibo"
                          data-loading-link
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                          <span class="sr-only">Baixar recibo</span>
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-sm text-zinc-500">Valor</div>
                    <div class="text-xl font-semibold text-zinc-900">R$ <?= number_format((float)$installment['amount_total'], 2, ',', '.') ?></div>
                    <div class="mt-1 text-xs text-zinc-500">Saldo: R$ <?= number_format((float)$remaining, 2, ',', '.') ?></div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-1">
          <div class="text-sm font-semibold text-zinc-900">Histórico completo de parcelas do período</div>
          <div class="text-xs text-zinc-500">Exibe parcelas pagas e pendentes com vencimento dentro do intervalo selecionado.</div>
        </div>
        <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-200">
          <?php if ($installmentsHistory === []): ?>
            <div class="px-4 py-6 text-sm text-zinc-500">Nenhuma parcela encontrada para o período selecionado.</div>
          <?php else: ?>
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
              <thead class="bg-zinc-50">
                <tr class="text-left text-zinc-500">
                  <th class="px-4 py-3 font-medium">Valor da parcela</th>
                  <th class="px-4 py-3 font-medium">Data de vencimento</th>
                  <th class="px-4 py-3 font-medium">Data de pagamento</th>
                  <th class="px-4 py-3 font-medium text-right">Ações</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-zinc-200 bg-white">
                <?php foreach ($installmentsHistory as $historyItem): ?>
                  <tr>
                    <td class="px-4 py-3 font-medium text-zinc-900">R$ <?= number_format((float)$historyItem['amount_total'], 2, ',', '.') ?></td>
                    <td class="px-4 py-3 text-zinc-700"><?= htmlspecialchars(\App\Helpers\Security::formatDate((string)$historyItem['due_date']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-zinc-700">
                      <?php if (!empty($historyItem['paid_at'])): ?>
                        <?= htmlspecialchars(\App\Helpers\Security::formatDate(substr((string)$historyItem['paid_at'], 0, 10)), ENT_QUOTES, 'UTF-8') ?>
                      <?php else: ?>
                        <span class="text-zinc-400">&nbsp;</span>
                      <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <?php if ((string)($historyItem['status'] ?? '') === 'paid' && (int)($historyItem['payment_id'] ?? 0) > 0): ?>
                        <a
                          href="/finance/payments/<?= (int)$historyItem['payment_id'] ?>/receipt?return_to=<?= urlencode('/clients/profile?id=' . (int)($client['id'] ?? 0)) ?>"
                          class="icon-btn icon-btn--sm"
                          title="Baixar recibo"
                          aria-label="Baixar recibo"
                          data-loading-link
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                          <span class="sr-only">Baixar recibo</span>
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="space-y-4">
      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="text-sm font-semibold text-zinc-900">Projetos ativos</div>
        <div class="mt-4 space-y-3">
          <?php if ($projects === []): ?>
            <div class="rounded-xl border border-dashed border-zinc-200 px-4 py-6 text-sm text-zinc-500">Nenhum projeto vinculado.</div>
          <?php else: ?>
            <?php foreach ($projects as $project): ?>
              <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                <div class="flex items-center justify-between gap-3">
                  <div class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars((string)$project['name'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="flex items-center gap-2">
                    <a
                      href="/projetos/<?= (int)$project['id'] ?>"
                      class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-zinc-600 transition hover:border-[#FE5516] hover:text-[#FE5516] focus:outline-none focus:ring-2 focus:ring-[#FE5516]/30"
                      title="Ver detalhes do projeto"
                      aria-label="Ver detalhes do projeto"
                      data-loading-link
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                      </svg>
                    </a>
                    <span class="rounded-full border border-zinc-200 px-2 py-1 text-xs text-zinc-600"><?= htmlspecialchars((string)($projectStatusLabels[(string)$project['status']] ?? $project['status']), ENT_QUOTES, 'UTF-8') ?></span>
                  </div>
                </div>
                <div class="mt-3 grid gap-2 text-xs text-zinc-500">
                  <div>Total contratado: <span class="font-semibold text-zinc-700">R$ <?= number_format((float)$project['contract_value'], 2, ',', '.') ?></span></div>
                  <div>Pendente: <span class="font-semibold text-amber-600">R$ <?= number_format((float)$project['pending_amount'], 2, ',', '.') ?></span></div>
                  <div>Recebido: <span class="font-semibold text-emerald-600">R$ <?= number_format((float)$project['paid_amount'], 2, ',', '.') ?></span></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-3">
          <div class="text-sm font-semibold text-zinc-900">Tarefas cobráveis</div>
          <div class="text-sm font-semibold text-[#FE5516]">R$ <?= number_format((float)$billableTasksTotal, 2, ',', '.') ?></div>
        </div>
        <div class="mt-3 space-y-3">
          <?php if ($billableTasks === []): ?>
            <div class="rounded-xl border border-dashed border-zinc-200 px-4 py-5 text-sm text-zinc-500">Nenhuma tarefa cobrável no período.</div>
          <?php else: ?>
            <?php foreach ($billableTasks as $task): ?>
              <div class="rounded-xl border border-orange-100 bg-orange-50 p-4">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <div class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars((string)$task['title'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)$task['project_name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="mt-2 text-xs text-zinc-500">Status: <?= htmlspecialchars((string)($taskStatusLabels[(string)$task['status']] ?? $task['status']), ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                  <div class="text-sm font-semibold text-[#FE5516]">R$ <?= number_format((float)$task['billable_amount'], 2, ',', '.') ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="text-sm font-semibold text-zinc-900">Tarefas informativas</div>
        <div class="mt-3 space-y-3">
          <?php if ($informativeTasks === []): ?>
            <div class="rounded-xl border border-dashed border-zinc-200 px-4 py-5 text-sm text-zinc-500">Nenhuma tarefa informativa no período.</div>
          <?php else: ?>
            <?php foreach ($informativeTasks as $task): ?>
              <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                <div class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars((string)$task['title'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)$task['project_name'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="mt-2 text-xs text-zinc-500">Status: <?= htmlspecialchars((string)($taskStatusLabels[(string)$task['status']] ?? $task['status']), ENT_QUOTES, 'UTF-8') ?></div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php
    return (string)ob_get_clean();
})();
require __DIR__ . '/../partials/app-shell.php';
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
