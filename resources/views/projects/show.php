<?php
$active = 'projects';
ob_start();
?>
<?php
$slot = (function () use ($detail, $taskSort, $tab, $users, $editTask, $role, $error, $ok): string {
    $project = $detail['project'];
    $installments = $detail['installments'];
    $tasks = $detail['tasks'];
    $billableTasksTotal = (float)$detail['billable_tasks_total'];
    $projectId = (int)$project['id'];
    $isEditingTask = is_array($editTask);
    $canManage = in_array((string)$role, ['admin', 'gestor'], true);
    $returnTo = '/projetos/' . $projectId . '?' . http_build_query([
        'tab' => 'dashboard',
        'task_sort' => $taskSort,
    ]);
    $taskFormAction = $isEditingTask ? '/tarefas/' . (int)$editTask['id'] . '/atualizar' : '/tarefas';
    $projectStatusLabels = [
        'active' => 'Em andamento',
        'paused' => 'Pausado',
        'done' => 'Concluído',
        'cancelled' => 'Cancelado',
        'inactive' => 'Inativo',
    ];
    $installmentStatusLabels = [
        'paid' => 'Paga',
        'pending' => 'Pendente',
        'overdue' => 'Vencida',
        'cancelled' => 'Cancelada',
    ];
    $taskStatusLabels = [
        'todo' => 'A fazer',
        'doing' => 'Em progresso',
        'done' => 'Concluído',
    ];
    $priorityLabels = [
        'high' => 'Alta',
        'medium' => 'Média',
        'low' => 'Baixa',
    ];
    ob_start();
?>
  <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
    <div>
      <div class="flex items-center gap-2 text-sm text-sky-600">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-sky-100">P</span>
        <span>Painel do Projeto</span>
      </div>
      <div class="mt-2 text-2xl font-semibold text-zinc-900"><?= htmlspecialchars((string)$project['name'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars((string)$project['client_name'], ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="/projetos" class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 transition hover:bg-zinc-50">Voltar</a>
      <a href="/tarefas" class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 transition hover:bg-zinc-50">Abrir tarefas</a>
      <span class="rounded-full border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-medium text-sky-700"><?= htmlspecialchars((string)($projectStatusLabels[(string)$project['status']] ?? $project['status']), ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  </div>

  <?php if (!empty($ok)): ?>
    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= htmlspecialchars((string)$ok, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="mt-4 flex flex-wrap gap-2">
    <a href="/projetos/<?= $projectId ?>?tab=details&task_sort=<?= urlencode((string)$taskSort) ?>" class="rounded-xl px-4 py-2 text-sm font-medium transition <?= $tab === 'details' ? 'bg-sky-600 text-white' : 'border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' ?>">Detalhes</a>
    <a href="/projetos/<?= $projectId ?>?tab=dashboard&task_sort=<?= urlencode((string)$taskSort) ?>" class="rounded-xl px-4 py-2 text-sm font-medium transition <?= $tab === 'dashboard' ? 'bg-indigo-600 text-white' : 'border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' ?>">Painel</a>
  </div>

  <div class="mt-6 grid gap-4 xl:grid-cols-4">
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Contrato</div>
      <div class="mt-3 text-2xl font-semibold text-zinc-900">R$ <?= number_format((float)$project['contract_value'], 2, ',', '.') ?></div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Pendente</div>
      <div class="mt-3 text-2xl font-semibold text-amber-600">R$ <?= number_format((float)$project['pending_amount'], 2, ',', '.') ?></div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Recebido</div>
      <div class="mt-3 text-2xl font-semibold text-emerald-600">R$ <?= number_format((float)$project['paid_amount'], 2, ',', '.') ?></div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Tarefas cobráveis</div>
      <div class="mt-3 text-2xl font-semibold text-[#FE5516]">R$ <?= number_format($billableTasksTotal, 2, ',', '.') ?></div>
    </div>
  </div>

  <div class="mt-6 grid gap-4 2xl:grid-cols-[1.15fr_1fr]">
    <div class="space-y-4">
      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="text-sm font-semibold text-zinc-900">Resumo do projeto</div>
        <div class="mt-4 grid gap-4 text-sm text-zinc-600 md:grid-cols-2">
          <div><span class="text-zinc-500">Cliente:</span> <?= htmlspecialchars((string)$project['client_name'], ENT_QUOTES, 'UTF-8') ?></div>
          <div><span class="text-zinc-500">Criado em:</span> <?= htmlspecialchars(\App\Helpers\Security::formatDate(substr((string)$project['created_at'], 0, 10)), ENT_QUOTES, 'UTF-8') ?></div>
          <div><span class="text-zinc-500">E-mail:</span> <?= htmlspecialchars((string)($project['client_email'] ?: 'Não informado'), ENT_QUOTES, 'UTF-8') ?></div>
          <div><span class="text-zinc-500">Telefone:</span> <?= htmlspecialchars((string)($project['client_phone'] ?: 'Não informado'), ENT_QUOTES, 'UTF-8') ?></div>
          <div><span class="text-zinc-500">Início:</span> <?= htmlspecialchars(\App\Helpers\Security::formatDate((string)$project['start_date']), ENT_QUOTES, 'UTF-8') ?: 'Não informado' ?></div>
          <div><span class="text-zinc-500">Prazo final:</span> <?= htmlspecialchars(\App\Helpers\Security::formatDate((string)$project['due_date']), ENT_QUOTES, 'UTF-8') ?: 'Não informado' ?></div>
          <div><span class="text-zinc-500">Primeira parcela:</span> <?= htmlspecialchars(\App\Helpers\Security::formatDate((string)$project['first_installment_date']), ENT_QUOTES, 'UTF-8') ?: 'Não informada' ?></div>
          <div><span class="text-zinc-500">Condição de pagamento:</span> <?= htmlspecialchars((string)($project['payment_terms'] ?: 'Não informada'), ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <?php if (!empty($project['description'])): ?>
          <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-600">
            <?= nl2br(htmlspecialchars((string)$project['description'], ENT_QUOTES, 'UTF-8')) ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-sm font-semibold text-zinc-900">Parcelas vinculadas</div>
            <div class="mt-1 text-xs text-zinc-500">Financeiro e painel compartilham o mesmo contexto do projeto.</div>
          </div>
          <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs text-zinc-600"><?= count($installments) ?> parcelas</span>
        </div>
        <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-200">
          <table class="min-w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50">
              <tr class="text-left text-zinc-500">
                <th class="px-4 py-3 font-medium">Parcela</th>
                <th class="px-4 py-3 font-medium">Vencimento</th>
                <th class="px-4 py-3 font-medium">Valor</th>
                <th class="px-4 py-3 font-medium">Status</th>
                <th class="px-4 py-3 font-medium text-right">Ações</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 bg-white">
              <?php if ($installments === []): ?>
                <tr><td colspan="5" class="px-4 py-6 text-center text-zinc-500">Nenhuma parcela vinculada.</td></tr>
              <?php else: ?>
                <?php foreach ($installments as $installment): ?>
                  <tr>
                    <td class="px-4 py-3 text-zinc-700"><?= htmlspecialchars((string)$installment['description'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 text-zinc-700"><?= htmlspecialchars(\App\Helpers\Security::formatDate((string)$installment['due_date']), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="px-4 py-3 font-medium text-zinc-900">R$ <?= number_format((float)$installment['amount_total'], 2, ',', '.') ?></td>
                    <td class="px-4 py-3">
                      <span class="rounded-full border px-2 py-1 text-xs <?= $installment['status'] === 'paid' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : ($installment['status'] === 'overdue' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700') ?>">
                        <?= htmlspecialchars((string)($installmentStatusLabels[(string)$installment['status']] ?? $installment['status']), ENT_QUOTES, 'UTF-8') ?>
                      </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <?php if ((string)$installment['status'] === 'paid' && (int)($installment['payment_id'] ?? 0) > 0): ?>
                        <a
                          href="/finance/payments/<?= (int)$installment['payment_id'] ?>/receipt?return_to=<?= urlencode('/projetos/' . (int)$projectId . '?tab=financial') ?>"
                          class="icon-btn icon-btn--sm"
                          title="Baixar recibo"
                          aria-label="Baixar recibo"
                          data-loading-link
                        >
                          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                          <span class="sr-only">Baixar recibo</span>
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="space-y-4">
      <div class="rounded-2xl border border-indigo-100 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-sm font-semibold text-zinc-900"><?= $isEditingTask ? 'Editar tarefa do projeto' : 'Nova tarefa no projeto' ?></div>
            <div class="mt-1 text-xs text-zinc-500">O vínculo com o projeto é aplicado automaticamente.</div>
          </div>
          <?php if ($isEditingTask): ?>
            <a href="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-xs text-zinc-700 transition hover:bg-zinc-50">Cancelar</a>
          <?php endif; ?>
        </div>

        <?php if ($canManage): ?>
          <form method="POST" action="<?= htmlspecialchars($taskFormAction, ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4" data-task-form data-loading-form>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="project_id" value="<?= $projectId ?>" />
            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>" />
            <label class="block">
              <div class="text-xs text-zinc-500">Título</div>
              <input name="title" required value="<?= htmlspecialchars((string)($editTask['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
            </label>
            <label class="block">
              <div class="text-xs text-zinc-500">Descrição</div>
              <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm"><?= htmlspecialchars((string)($editTask['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="block">
                <div class="text-xs text-zinc-500">Responsável</div>
                <select name="assignee_user_id" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
                  <option value="">Não atribuído</option>
                  <?php foreach ($users as $user): ?>
                    <option value="<?= (int)$user['id'] ?>" <?= (int)($editTask['assignee_user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="block">
                <div class="text-xs text-zinc-500">Prazo</div>
                <input name="due_date" value="<?= htmlspecialchars((string)($editTask['due_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="aaaa-mm-dd" pattern="\d{4}-\d{2}-\d{2}|\d{2}/\d{2}/\d{4}" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
              </label>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
              <label class="block">
                <div class="text-xs text-zinc-500">Status</div>
                <select name="status" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
                  <?php foreach (['todo' => 'A fazer', 'doing' => 'Em progresso', 'done' => 'Concluído'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= (($editTask['status'] ?? 'todo') === $value) ? 'selected' : '' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="block">
                <div class="text-xs text-zinc-500">Prioridade</div>
                <select name="priority" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
                  <?php foreach (['high' => 'Alta', 'medium' => 'Média', 'low' => 'Baixa'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= (($editTask['priority'] ?? 'medium') === $value) ? 'selected' : '' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="block">
                <div class="text-xs text-zinc-500">Classificação</div>
                <select name="task_kind" data-task-kind class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
                  <?php foreach (['in_scope' => 'No escopo', 'out_of_scope' => 'Fora de escopo', 'one_off' => 'Avulsa'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= (($editTask['task_kind'] ?? 'in_scope') === $value) ? 'selected' : '' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
            <label class="block" data-billable-wrap <?= in_array((string)($editTask['task_kind'] ?? 'in_scope'), ['out_of_scope', 'one_off'], true) ? '' : 'hidden' ?>>
              <div class="text-xs text-zinc-500">Valor cobrável</div>
              <input name="billable_amount" value="<?= htmlspecialchars(number_format((float)($editTask['billable_amount'] ?? 0), 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?>" data-money-mask placeholder="0,00" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
            </label>
            <div class="flex gap-2">
              <button type="submit" data-loading-button data-loading-text="Salvando..." class="flex-1 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700"><?= $isEditingTask ? 'Salvar tarefa' : 'Criar tarefa' ?></button>
            </div>
            <div class="hidden rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500" data-loading-indicator>Salvando tarefa no projeto...</div>
          </form>
        <?php else: ?>
          <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600">Seu perfil possui acesso de leitura neste painel.</div>
        <?php endif; ?>
      </div>

      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-sm font-semibold text-zinc-900">Tarefas do projeto</div>
            <div class="mt-1 text-xs text-zinc-500">Ordenação por prioridade, vencimento ou responsável.</div>
          </div>
          <form method="GET" action="/projetos/<?= $projectId ?>" class="flex gap-2" data-loading-form>
            <input type="hidden" name="tab" value="dashboard" />
            <select name="task_sort" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
              <option value="created_at" <?= $taskSort === 'created_at' ? 'selected' : '' ?>>Mais recentes</option>
              <option value="priority" <?= $taskSort === 'priority' ? 'selected' : '' ?>>Prioridade</option>
              <option value="due_date" <?= $taskSort === 'due_date' ? 'selected' : '' ?>>Vencimento</option>
              <option value="assignee" <?= $taskSort === 'assignee' ? 'selected' : '' ?>>Responsável</option>
            </select>
            <button type="submit" data-loading-button data-loading-text="Ordenando..." class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 transition hover:bg-zinc-50">Ordenar</button>
          </form>
        </div>

        <div class="mt-4 space-y-3">
          <?php if ($tasks === []): ?>
            <div class="rounded-xl border border-dashed border-zinc-200 px-4 py-6 text-sm text-zinc-500">Nenhuma tarefa vinculada a este projeto.</div>
          <?php else: ?>
            <?php foreach ($tasks as $task): ?>
              <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div class="min-w-0">
                    <div class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars((string)$task['title'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="mt-1 flex flex-wrap gap-3 text-xs text-zinc-500">
                      <span>Status: <?= htmlspecialchars((string)($taskStatusLabels[(string)$task['status']] ?? $task['status']), ENT_QUOTES, 'UTF-8') ?></span>
                      <span>Prioridade: <?= htmlspecialchars((string)($priorityLabels[(string)$task['priority']] ?? $task['priority']), ENT_QUOTES, 'UTF-8') ?></span>
                      <span>Responsável: <?= htmlspecialchars((string)($task['assignee_name'] ?: 'Não atribuído'), ENT_QUOTES, 'UTF-8') ?></span>
                      <span>Prazo: <?= htmlspecialchars(\App\Helpers\Security::formatDate((string)$task['due_date']), ENT_QUOTES, 'UTF-8') ?: 'Sem prazo' ?></span>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <?php if ((string)$task['billing_type'] === 'billable'): ?>
                      <span class="rounded-full border border-orange-200 bg-orange-50 px-2 py-1 text-xs font-medium text-[#FE5516]">R$ <?= number_format((float)$task['billable_amount'], 2, ',', '.') ?></span>
                    <?php endif; ?>
                    <a href="/tarefas/<?= (int)$task['id'] ?>" title="Visualizar tarefa" aria-label="Visualizar tarefa" data-loading-link class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-sky-600 transition hover:bg-sky-50">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </a>
                    <?php if ($canManage): ?>
                      <a href="/projetos/<?= $projectId ?>?tab=dashboard&task_sort=<?= urlencode((string)$taskSort) ?>&edit_task=<?= (int)$task['id'] ?>" title="Editar tarefa" aria-label="Editar tarefa" data-loading-link class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-amber-600 transition hover:bg-amber-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="m16.5 3.5 4 4L7 21l-4 1 1-4L16.5 3.5Z"></path></svg>
                      </a>
                      <form method="POST" action="/tarefas/<?= (int)$task['id'] ?>/excluir" onsubmit="return confirm('Excluir esta tarefa do projeto?');">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>" />
                        <button type="submit" title="Excluir tarefa" aria-label="Excluir tarefa" class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-red-600 transition hover:bg-red-50">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="m19 6-1 14H6L5 6"></path></svg>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
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
