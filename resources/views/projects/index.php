<?php
$active = 'projects';
ob_start();
?>
<?php
$slot = (function () use ($clients, $projects, $tasksBoard, $projectOptions, $ok, $error): string {
    ob_start();
?>
  <div class="flex items-end justify-between gap-4">
    <div>
      <div class="text-sm text-zinc-500">Execução</div>
      <div class="mt-1 text-2xl font-semibold text-zinc-900">Projetos & Tarefas</div>
    </div>
    <a href="/clients" class="rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm text-zinc-700 hover:bg-zinc-50 transition">Ver clientes</a>
  </div>

  <?php if (!empty($ok)): ?>
    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= htmlspecialchars((string)$ok, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <div class="mt-6 grid gap-4 2xl:grid-cols-[1.2fr_1fr]">
    <div class="space-y-4">
      <div id="new-project" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="text-sm font-medium text-zinc-900">Novo projeto com financeiro</div>
        <form method="POST" action="/projects" class="mt-4 grid gap-3 md:grid-cols-2" data-installment-form>
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
          <label class="block md:col-span-2">
            <div class="text-xs text-zinc-500">Nome</div>
            <input name="name" required class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
          </label>
          <label class="block">
            <div class="text-xs text-zinc-500">Cliente</div>
            <select name="client_id" required <?= $clients === [] ? 'disabled' : '' ?> class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30 disabled:cursor-not-allowed disabled:opacity-60">
              <option value="">Selecione</option>
              <?php foreach ($clients as $client): ?>
                <option value="<?= (int)$client['id'] ?>"><?= htmlspecialchars((string)$client['name'], ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="block">
            <div class="text-xs text-zinc-500">Status</div>
            <select name="status" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30">
              <option value="active">Em andamento</option>
              <option value="paused">Pausado</option>
              <option value="done">Concluído</option>
            </select>
          </label>
          <label class="block">
            <div class="text-xs text-zinc-500">Data de início</div>
            <input name="start_date" data-date-mask placeholder="dd/mm/aaaa" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
          </label>
          <label class="block">
            <div class="text-xs text-zinc-500">Prazo final</div>
            <input name="due_date" data-date-mask placeholder="dd/mm/aaaa" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
          </label>
          <label class="block md:col-span-2">
            <div class="text-xs text-zinc-500">Descrição</div>
            <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30"></textarea>
          </label>
          <div class="md:col-span-2 grid gap-3 rounded-2xl border border-zinc-200 bg-zinc-50 p-4 md:grid-cols-2">
            <label class="block">
              <div class="text-xs text-zinc-500">Valor total</div>
              <input name="contract_value" required data-money-mask placeholder="0,00" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
            </label>
            <label class="block">
              <div class="text-xs text-zinc-500">Valor de entrada</div>
              <input name="entry_amount" data-money-mask placeholder="0,00" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
            </label>
            <label class="block">
              <div class="text-xs text-zinc-500">Primeira parcela</div>
              <input name="first_installment_date" data-date-mask placeholder="dd/mm/aaaa" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
            </label>
            <label class="block">
              <div class="text-xs text-zinc-500">Quantidade de parcelas</div>
              <input name="installments_count" type="number" min="0" value="1" data-installment-count class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
            </label>
            <label class="block md:col-span-2">
              <div class="text-xs text-zinc-500">Condições de pagamento</div>
              <textarea name="payment_terms" rows="2" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30"></textarea>
            </label>
            <div class="md:col-span-2">
              <div class="mb-2 flex items-center justify-between">
                <div class="text-sm font-medium text-zinc-900">Parcelas mensais</div>
                <button type="button" data-generate-installments class="rounded-xl border border-zinc-200 bg-white px-3 py-2 text-xs font-medium text-zinc-700 hover:border-[#FE5516] hover:text-[#FE5516] transition">Gerar parcelas</button>
              </div>
              <div class="space-y-3" data-installments-container></div>
            </div>
          </div>
          <div class="md:col-span-2">
            <button type="submit" <?= $clients === [] ? 'disabled' : '' ?> class="w-full rounded-xl bg-[#FE5516] px-4 py-3 text-sm font-semibold text-white hover:opacity-90 transition disabled:cursor-not-allowed disabled:opacity-60">Criar projeto</button>
          </div>
        </form>
      </div>

      <div id="new-task" class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="text-sm font-medium text-zinc-900">Nova tarefa</div>
        <form method="POST" action="/tasks" class="mt-4 grid gap-3 md:grid-cols-2" data-task-form>
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\App\Helpers\CSRF::token(), ENT_QUOTES, 'UTF-8') ?>" />
          <label class="block md:col-span-2">
            <div class="text-xs text-zinc-500">Título</div>
            <input name="title" required class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
          </label>
          <label class="block">
            <div class="text-xs text-zinc-500">Projeto</div>
            <select name="project_id" required <?= $projectOptions === [] ? 'disabled' : '' ?> class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30 disabled:cursor-not-allowed disabled:opacity-60">
              <option value="">Selecione</option>
              <?php foreach ($projectOptions as $project): ?>
                <option value="<?= (int)$project['id'] ?>"><?= htmlspecialchars((string)$project['name'], ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="block">
            <div class="text-xs text-zinc-500">Status</div>
            <select name="status" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30">
              <option value="todo">A fazer</option>
              <option value="doing">Em progresso</option>
              <option value="done">Concluído</option>
            </select>
          </label>
          <label class="block">
            <div class="text-xs text-zinc-500">Prioridade</div>
            <select name="priority" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30">
              <option value="medium">Média</option>
              <option value="high">Alta</option>
              <option value="low">Baixa</option>
            </select>
          </label>
          <label class="block">
            <div class="text-xs text-zinc-500">Prazo</div>
            <input name="due_date" placeholder="aaaa-mm-dd" pattern="\d{4}-\d{2}-\d{2}|\d{2}/\d{2}/\d{4}" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
          </label>
          <label class="block">
            <div class="text-xs text-zinc-500">Classificação</div>
            <select name="task_kind" data-task-kind class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30">
              <option value="in_scope">No escopo</option>
              <option value="out_of_scope">Fora de escopo</option>
              <option value="one_off">Avulsa</option>
            </select>
          </label>
          <label class="block" data-billable-wrap hidden>
            <div class="text-xs text-zinc-500">Valor cobrável</div>
            <input name="billable_amount" data-money-mask placeholder="0,00" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
          </label>
          <label class="block md:col-span-2">
            <div class="text-xs text-zinc-500">Descrição</div>
            <textarea name="description" rows="3" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30"></textarea>
          </label>
          <div class="md:col-span-2">
            <button type="submit" <?= $projectOptions === [] ? 'disabled' : '' ?> class="w-full rounded-xl bg-[#E8D9BB] px-4 py-3 text-sm font-semibold text-zinc-900 hover:opacity-90 transition disabled:cursor-not-allowed disabled:opacity-60">Criar tarefa</button>
          </div>
        </form>
      </div>
    </div>

    <div class="space-y-4">
      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <div class="text-sm font-medium text-zinc-900">Projetos</div>
          <div class="text-xs text-zinc-500"><?= count($projects) ?> registrados</div>
        </div>
        <div class="mt-4 space-y-3">
          <?php if ($projects === []): ?>
            <div class="rounded-xl border border-dashed border-zinc-200 px-4 py-5 text-sm text-zinc-500">Nenhum projeto criado ainda.</div>
          <?php else: ?>
            <?php foreach ($projects as $project): ?>
              <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="text-sm font-medium text-zinc-900"><?= htmlspecialchars((string)$project['name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)$project['client_name'], ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                  <div class="flex items-center gap-2">
                    <a
                      href="/projects/show?id=<?= (int)$project['id'] ?>"
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
                    <span class="rounded-full border border-zinc-200 bg-white px-2 py-1 text-xs text-zinc-600">
                      <?= htmlspecialchars((string)([
                          'active' => 'Em andamento',
                          'paused' => 'Pausado',
                          'done' => 'Concluído',
                          'cancelled' => 'Cancelado',
                          'inactive' => 'Inativo',
                      ][(string)$project['status']] ?? $project['status']), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </div>
                </div>
                <div class="mt-3 grid gap-2 text-xs text-zinc-500 md:grid-cols-2">
                  <span><?= (int)$project['tasks_count'] ?> tarefas</span>
                  <span><?= htmlspecialchars((string)($project['due_date'] ?: 'Sem prazo'), ENT_QUOTES, 'UTF-8') ?></span>
                  <span>Contrato: <strong class="text-zinc-700">R$ <?= number_format((float)$project['contract_value'], 2, ',', '.') ?></strong></span>
                  <span>Pendente: <strong class="text-amber-600">R$ <?= number_format((float)$project['pending_amount'], 2, ',', '.') ?></strong></span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm">
        <div class="text-sm font-medium text-zinc-900">Kanban</div>
        <div class="mt-4 grid gap-3 lg:grid-cols-3">
          <?php
          $columns = [
              'todo' => 'A fazer',
              'doing' => 'Em progresso',
              'done' => 'Concluído',
          ];
          ?>
          <?php foreach ($columns as $status => $label): ?>
            <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-3">
              <div class="text-xs uppercase tracking-wide text-zinc-500"><?= $label ?></div>
              <div class="mt-3 space-y-3">
                <?php if (($tasksBoard[$status] ?? []) === []): ?>
                  <div class="rounded-lg border border-dashed border-zinc-200 px-3 py-4 text-xs text-zinc-500">Sem tarefas nesta coluna.</div>
                <?php else: ?>
                  <?php foreach ($tasksBoard[$status] as $task): ?>
                    <div class="rounded-lg border border-zinc-200 bg-white p-3">
                      <div class="text-sm font-medium text-zinc-900"><?= htmlspecialchars((string)$task['title'], ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="mt-1 text-xs text-zinc-500"><?= htmlspecialchars((string)$task['project_name'], ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-zinc-500">
                        <span><?= htmlspecialchars((string)([
                            'high' => 'Alta',
                            'medium' => 'Média',
                            'low' => 'Baixa',
                        ][(string)$task['priority']] ?? $task['priority']), ENT_QUOTES, 'UTF-8') ?></span>
                        <span><?= htmlspecialchars((string)($task['due_date'] ?: 'Sem prazo'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="<?= ($task['billing_type'] ?? 'informative') === 'billable' ? 'text-[#FE5516] font-semibold' : 'text-zinc-500' ?>">
                          <?= ($task['billing_type'] ?? 'informative') === 'billable' ? 'Cobrável' : 'Informativa' ?>
                        </span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
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
