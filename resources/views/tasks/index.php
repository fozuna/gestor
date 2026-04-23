<?php
$active = 'tasks';
ob_start();
?>
<?php
$slot = (function () use ($tasks, $pagination, $filters, $board, $projects, $clients, $users, $editTask, $role, $ok, $error): string {
    $isEditing = is_array($editTask);
    $canManage = in_array((string)$role, ['admin', 'gestor'], true);
    $formAction = $isEditing ? '/tarefas/' . (int)$editTask['id'] . '/atualizar' : '/tarefas';
    $formTitle = $isEditing ? 'Editar tarefa' : 'Nova tarefa';
    $returnTo = '/tarefas';
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
    ob_start();
?>
  <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
    <div>
      <div class="flex items-center gap-2 text-sm text-violet-600">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-violet-100">T</span>
        <span>Módulo de Tarefas</span>
      </div>
      <div class="mt-2 text-2xl font-semibold text-zinc-900">Tarefas</div>
      <div class="mt-1 text-sm text-zinc-500">CRUD completo, filtros, ordenação e Kanban persistente com rollback visual em erro.</div>
    </div>
    <div class="flex gap-2">
      <a href="/projetos" class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 transition hover:bg-zinc-50">Abrir projetos</a>
      <a href="/dashboard" class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-700">Painel</a>
    </div>
  </div>

  <?php if (!empty($ok)): ?>
    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= htmlspecialchars((string)$ok, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="mt-4 hidden rounded-xl border px-4 py-3 text-sm" data-kanban-feedback></div>

  <div class="mt-6 grid gap-4 2xl:grid-cols-[360px_minmax(0,1fr)]">
    <div class="rounded-2xl border border-violet-100 bg-white p-5 shadow-sm">
      <div class="text-sm font-semibold text-zinc-900"><?= $formTitle ?></div>
      <?php if (!$canManage): ?>
        <div class="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600">Seu perfil possui acesso de leitura. Visualização e filtros continuam disponíveis.</div>
      <?php else: ?>
        <form method="POST" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>" class="mt-4 space-y-4" data-task-form data-loading-form>
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>" />
          <label class="block">
            <div class="text-xs text-zinc-500">Projeto</div>
            <select name="project_id" required class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
              <option value="">Selecione</option>
              <?php foreach ($projects as $project): ?>
                <option value="<?= (int)$project['id'] ?>" <?= (int)($editTask['project_id'] ?? 0) === (int)$project['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars((string)$project['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="block">
            <div class="text-xs text-zinc-500">Título</div>
            <input name="title" required value="<?= htmlspecialchars((string)($editTask['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm" />
          </label>
          <label class="block">
            <div class="text-xs text-zinc-500">Descrição</div>
            <textarea name="description" rows="4" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm"><?= htmlspecialchars((string)($editTask['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
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
                <?php foreach ($statusLabels as $value => $label): ?>
                  <option value="<?= $value ?>" <?= (($editTask['status'] ?? 'todo') === $value) ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="block">
              <div class="text-xs text-zinc-500">Prioridade</div>
              <select name="priority" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
                <?php foreach ($priorityLabels as $value => $label): ?>
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
            <button type="submit" data-loading-button data-loading-text="Salvando..." class="flex-1 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-700"><?= $isEditing ? 'Salvar alterações' : 'Criar tarefa' ?></button>
            <?php if ($isEditing): ?>
              <a href="/tarefas" class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 transition hover:bg-zinc-50">Cancelar</a>
            <?php endif; ?>
          </div>
          <div class="hidden rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500" data-loading-indicator>Processando tarefa...</div>
        </form>
      <?php endif; ?>
    </div>

    <div class="space-y-4">
      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div class="text-sm font-semibold text-zinc-900">Listagem de tarefas</div>
            <div class="mt-1 text-sm text-zinc-500"><?= (int)$pagination['total'] ?> registros encontrados</div>
          </div>
          <form method="GET" action="/tarefas" class="grid gap-2 sm:grid-cols-5" data-loading-form>
            <select name="client" data-task-client-filter class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
              <option value="">Todos os clientes</option>
              <?php foreach ($clients as $client): ?>
                <option value="<?= (int)$client['id'] ?>" <?= $filters['client'] === (string)$client['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars((string)$client['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <select name="status" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
              <option value="">Todos os status</option>
              <option value="todo" <?= $filters['status'] === 'todo' ? 'selected' : '' ?>>A fazer</option>
              <option value="doing" <?= $filters['status'] === 'doing' ? 'selected' : '' ?>>Em progresso</option>
              <option value="done" <?= $filters['status'] === 'done' ? 'selected' : '' ?>>Concluído</option>
            </select>
            <select name="assignee" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
              <option value="">Todos os responsáveis</option>
              <option value="unassigned" <?= $filters['assignee'] === 'unassigned' ? 'selected' : '' ?>>Não atribuído</option>
              <?php foreach ($users as $user): ?>
                <option value="<?= (int)$user['id'] ?>" <?= $filters['assignee'] === (string)$user['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <select name="priority" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
              <option value="">Todas as prioridades</option>
              <option value="high" <?= $filters['priority'] === 'high' ? 'selected' : '' ?>>Alta</option>
              <option value="medium" <?= $filters['priority'] === 'medium' ? 'selected' : '' ?>>Média</option>
              <option value="low" <?= $filters['priority'] === 'low' ? 'selected' : '' ?>>Baixa</option>
            </select>
            <select name="sort" class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm">
              <option value="created_at" <?= $filters['sort'] === 'created_at' ? 'selected' : '' ?>>Mais recentes</option>
              <option value="priority" <?= $filters['sort'] === 'priority' ? 'selected' : '' ?>>Prioridade</option>
              <option value="due_date" <?= $filters['sort'] === 'due_date' ? 'selected' : '' ?>>Vencimento</option>
              <option value="assignee" <?= $filters['sort'] === 'assignee' ? 'selected' : '' ?>>Responsável</option>
            </select>
            <div class="sm:col-span-5 flex gap-2">
              <button type="submit" data-loading-button data-loading-text="Filtrando..." class="flex-1 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-violet-700">Aplicar filtros</button>
              <button type="button" data-task-client-clear class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50">Limpar filtro</button>
            </div>
          </form>
        </div>

        <div class="mt-4 space-y-3">
          <?php if ($tasks === []): ?>
            <div class="rounded-xl border border-dashed border-zinc-200 px-4 py-8 text-center text-sm text-zinc-500">Nenhuma tarefa encontrada para os filtros aplicados.</div>
          <?php else: ?>
            <?php foreach ($tasks as $task): ?>
              <?php $isDone = ((string)($task['status'] ?? 'todo') === 'done'); ?>
              <div
                class="task-card <?= $isDone ? 'task-card--done' : 'task-card--pending' ?> rounded-2xl border border-zinc-200 p-4"
                data-task-row
                data-task-status="<?= htmlspecialchars((string)($task['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                data-task-client-id="<?= (int)($task['client_id'] ?? 0) ?>"
              >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-zinc-900"><?= htmlspecialchars((string)$task['title'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)$task['project_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)($task['client_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="mt-3 flex flex-wrap gap-4 text-xs text-zinc-500">
                      <span>Status: <?= htmlspecialchars((string)($statusLabels[(string)$task['status']] ?? $task['status']), ENT_QUOTES, 'UTF-8') ?></span>
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
                      <a href="/tarefas?edit=<?= (int)$task['id'] ?>" title="Editar tarefa" aria-label="Editar tarefa" data-loading-link class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-zinc-200 bg-white text-amber-600 transition hover:bg-amber-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="m16.5 3.5 4 4L7 21l-4 1 1-4L16.5 3.5Z"></path></svg>
                      </a>
                      <form method="POST" action="/tarefas/<?= (int)$task['id'] ?>/excluir" onsubmit="return confirm('Excluir esta tarefa?');">
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
          <div class="hidden rounded-xl border border-dashed border-zinc-200 px-4 py-8 text-center text-sm text-zinc-500" data-task-list-empty-dynamic>
            Nenhuma tarefa encontrada para o cliente selecionado.
          </div>
        </div>

        <?php if (($pagination['pages'] ?? 1) > 1): ?>
          <div class="mt-4 flex flex-wrap gap-2">
            <?php for ($page = 1; $page <= (int)$pagination['pages']; $page++): ?>
              <a
                href="/tarefas?<?= http_build_query(['client' => $filters['client'], 'status' => $filters['status'], 'priority' => $filters['priority'], 'assignee' => $filters['assignee'], 'sort' => $filters['sort'], 'page' => $page]) ?>"
                class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-sm <?= (int)$pagination['page'] === $page ? 'border-violet-600 bg-violet-600 text-white' : 'border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50' ?>"
              ><?= $page ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
          <div>
            <div class="text-sm font-semibold text-zinc-900">Kanban</div>
            <div class="mt-1 text-xs text-zinc-500">Arraste os cards entre colunas. A persistência acontece imediatamente e o card volta ao lugar se houver erro.</div>
          </div>
          <span class="rounded-full border border-zinc-200 bg-zinc-50 px-3 py-1 text-xs text-zinc-600">Layout responsivo para desktop e mobile</span>
        </div>

        <div class="mt-4 grid gap-3 xl:grid-cols-3" data-kanban-board data-kanban-csrf="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>">
          <?php foreach (['todo' => 'A fazer', 'doing' => 'Em progresso', 'done' => 'Concluído'] as $status => $label): ?>
            <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-3">
              <div class="mb-3 flex items-center justify-between">
                <div class="text-xs uppercase tracking-wide text-zinc-500"><?= $label ?></div>
                <span class="rounded-full border border-zinc-200 bg-white px-2 py-1 text-xs text-zinc-600"><?= count($board[$status] ?? []) ?></span>
              </div>
              <div class="space-y-3 min-h-[120px]" data-kanban-column="<?= $status ?>">
                <?php foreach (($board[$status] ?? []) as $task): ?>
                  <?php $isDoneBoard = ((string)($task['status'] ?? $status) === 'done'); ?>
                  <article
                    class="task-card <?= $isDoneBoard ? 'task-card--done' : 'task-card--pending' ?> rounded-xl border border-zinc-200 p-3 shadow-sm transition"
                    draggable="<?= $canManage ? 'true' : 'false' ?>"
                    data-kanban-card
                    data-task-id="<?= (int)$task['id'] ?>"
                    data-task-status="<?= htmlspecialchars((string)($task['status'] ?? $status), ENT_QUOTES, 'UTF-8') ?>"
                    data-task-client-id="<?= (int)($task['client_id'] ?? 0) ?>"
                  >
                    <div class="flex items-start justify-between gap-2">
                      <div class="text-sm font-semibold text-zinc-900"><?= htmlspecialchars((string)$task['title'], ENT_QUOTES, 'UTF-8') ?></div>
                      <?php if ($canManage): ?>
                        <span class="cursor-grab text-xs text-zinc-400" title="Arrastar tarefa">::</span>
                      <?php endif; ?>
                    </div>
                    <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)$task['project_name'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)($task['client_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                      <span class="rounded-full border border-zinc-200 bg-zinc-50 px-2 py-1 text-zinc-600"><?= htmlspecialchars((string)($priorityLabels[(string)$task['priority']] ?? $task['priority']), ENT_QUOTES, 'UTF-8') ?></span>
                      <span class="rounded-full border border-zinc-200 bg-zinc-50 px-2 py-1 text-zinc-600"><?= htmlspecialchars((string)($task['assignee_name'] ?: 'Sem responsável'), ENT_QUOTES, 'UTF-8') ?></span>
                      <?php if (!empty($task['due_date'])): ?>
                        <span class="rounded-full border border-zinc-200 bg-zinc-50 px-2 py-1 text-zinc-600"><?= htmlspecialchars(\App\Helpers\Security::formatDate((string)$task['due_date']), ENT_QUOTES, 'UTF-8') ?></span>
                      <?php endif; ?>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
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
