<?php
$active = 'tasks';
ob_start();
?>
<?php
$slot = (function () use ($task, $role, $ok, $error): string {
    $canManage = in_array((string)$role, ['admin', 'gestor'], true);
    $statusLabels = [
        'todo' => 'A fazer',
        'doing' => 'Em progresso',
        'done' => 'Concluído',
    ];
    $priorityLabels = [
        'high' => 'Alta',
        'medium' => 'Média',
        'low' => 'Baixa',
    ];
    $kindLabels = [
        'in_scope' => 'No escopo',
        'out_of_scope' => 'Fora de escopo',
        'one_off' => 'Avulsa',
    ];
    $billingLabels = [
        'billable' => 'Cobrável',
        'informative' => 'Informativa',
    ];
    ob_start();
?>
  <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
    <div>
      <div class="flex items-center gap-2 text-sm text-violet-600">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-violet-100">T</span>
        <span>Detalhes da Tarefa</span>
      </div>
      <div class="mt-2 text-2xl font-semibold text-zinc-900"><?= htmlspecialchars((string)$task['title'], ENT_QUOTES, 'UTF-8') ?></div>
      <div class="mt-1 text-sm text-zinc-500"><?= htmlspecialchars((string)$task['project_name'], ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="/tarefas" class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 transition hover:bg-zinc-50">Voltar</a>
      <a href="/projetos/<?= (int)$task['project_id'] ?>?tab=dashboard" class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 transition hover:bg-zinc-50">Projeto</a>
      <?php if ($canManage): ?>
        <a href="/tarefas?edit=<?= (int)$task['id'] ?>" class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-700">Editar</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($ok)): ?>
    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= htmlspecialchars((string)$ok, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="mt-6 grid gap-4 xl:grid-cols-4">
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Status</div>
      <div class="mt-3 text-2xl font-semibold text-zinc-900"><?= htmlspecialchars((string)($statusLabels[(string)$task['status']] ?? $task['status']), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Prioridade</div>
      <div class="mt-3 text-2xl font-semibold text-zinc-900"><?= htmlspecialchars((string)($priorityLabels[(string)$task['priority']] ?? $task['priority']), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Responsável</div>
      <div class="mt-3 text-2xl font-semibold text-zinc-900"><?= htmlspecialchars((string)($task['assignee_name'] ?: 'Não atribuído'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-xs uppercase tracking-wide text-zinc-500">Prazo</div>
      <div class="mt-3 text-2xl font-semibold text-zinc-900"><?= htmlspecialchars(\App\Helpers\Security::formatDate((string)$task['due_date']), ENT_QUOTES, 'UTF-8') ?: 'Sem prazo' ?></div>
    </div>
  </div>

  <div class="mt-6 grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-sm font-semibold text-zinc-900">Descrição</div>
      <div class="mt-4 rounded-2xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700">
        <?= nl2br(htmlspecialchars((string)($task['description'] ?: 'Nenhuma descrição informada.'), ENT_QUOTES, 'UTF-8')) ?>
      </div>
    </div>

    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
      <div class="text-sm font-semibold text-zinc-900">Contexto</div>
      <div class="mt-4 space-y-3 text-sm text-zinc-600">
        <div><span class="text-zinc-500">Projeto:</span> <?= htmlspecialchars((string)$task['project_name'], ENT_QUOTES, 'UTF-8') ?></div>
        <div><span class="text-zinc-500">Classificação:</span> <?= htmlspecialchars((string)($kindLabels[(string)$task['task_kind']] ?? $task['task_kind']), ENT_QUOTES, 'UTF-8') ?></div>
        <div><span class="text-zinc-500">Tipo:</span> <?= htmlspecialchars((string)($billingLabels[(string)$task['billing_type']] ?? $task['billing_type']), ENT_QUOTES, 'UTF-8') ?></div>
        <div><span class="text-zinc-500">Valor cobrável:</span> R$ <?= number_format((float)$task['billable_amount'], 2, ',', '.') ?></div>
        <div><span class="text-zinc-500">Criada em:</span> <?= htmlspecialchars(\App\Helpers\Security::formatDate(substr((string)$task['created_at'], 0, 10)), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
    </div>
  </div>
<?php
    return (string)ob_get_clean();
})();
require __DIR__ . '/../partials/app-shell.php';
$content = ob_get_clean();
require __DIR__ . '/../layouts/base.php';
