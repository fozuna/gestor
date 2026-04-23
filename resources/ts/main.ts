function applyTheme(theme: 'light' | 'dark') {
  const root = document.documentElement
  if (theme === 'dark') root.classList.add('dark')
  else root.classList.remove('dark')
}

function getPreferredTheme(): 'light' | 'dark' {
  const stored = localStorage.getItem('theme')
  if (stored === 'light' || stored === 'dark') return stored
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
  return prefersDark ? 'dark' : 'light'
}

function initTheme() {
  applyTheme(getPreferredTheme())

  document.querySelectorAll<HTMLElement>('[data-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark'
      localStorage.setItem('theme', next)
      applyTheme(next)
    })
  })
}

function formatMoneyValue(value: string): string {
  const digits = value.replace(/\D/g, '')
  if (!digits) return ''
  const normalized = (Number(digits) / 100).toFixed(2)
  const [integer, decimal] = normalized.split('.')
  return `${integer.replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${decimal}`
}

function maskDateValue(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 8)
  if (digits.length <= 2) return digits
  if (digits.length <= 4) return `${digits.slice(0, 2)}/${digits.slice(2)}`
  return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`
}

function maskPhoneValue(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 11)
  if (digits.length <= 2) return digits
  if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`
}

function initMasks() {
  document.querySelectorAll<HTMLInputElement>('[data-money-mask]').forEach((input) => {
    input.addEventListener('input', () => {
      input.value = formatMoneyValue(input.value)
    })
  })

  document.querySelectorAll<HTMLInputElement>('[data-date-mask]').forEach((input) => {
    input.addEventListener('input', () => {
      input.value = maskDateValue(input.value)
    })
  })

  document.querySelectorAll<HTMLInputElement>('[data-phone-mask]').forEach((input) => {
    input.addEventListener('input', () => {
      input.value = maskPhoneValue(input.value)
    })
  })
}

function buildInstallmentRow(index: number, dueDate: string, amount: string): string {
  return `
    <div class="grid gap-3 rounded-2xl border border-zinc-200 bg-white p-3 md:grid-cols-[1fr_1fr_auto]">
      <label class="block">
        <div class="text-xs text-zinc-500">Vencimento da parcela ${index + 1}</div>
        <input name="installments[${index}][due_date]" value="${dueDate}" data-date-mask placeholder="dd/mm/aaaa" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
      </label>
      <label class="block">
        <div class="text-xs text-zinc-500">Valor</div>
        <input name="installments[${index}][amount]" value="${amount}" data-money-mask placeholder="0,00" class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:ring-2 focus:ring-[#FE5516]/30" />
      </label>
      <button type="button" data-remove-installment class="mt-5 rounded-xl border border-zinc-200 px-3 py-2 text-xs font-medium text-zinc-600 hover:border-red-300 hover:text-red-600 transition">Remover</button>
    </div>
  `
}

function addMonths(date: Date, months: number): Date {
  const clone = new Date(date.getTime())
  clone.setMonth(clone.getMonth() + months)
  return clone
}

function toBrDate(date: Date): string {
  const day = `${date.getDate()}`.padStart(2, '0')
  const month = `${date.getMonth() + 1}`.padStart(2, '0')
  return `${day}/${month}/${date.getFullYear()}`
}

function initInstallmentBuilder() {
  const form = document.querySelector<HTMLFormElement>('[data-installment-form]')
  if (!form) return

  const countInput = form.querySelector<HTMLInputElement>('[data-installment-count]')
  const totalInput = form.querySelector<HTMLInputElement>('input[name="contract_value"]')
  const entryInput = form.querySelector<HTMLInputElement>('input[name="entry_amount"]')
  const firstDateInput = form.querySelector<HTMLInputElement>('input[name="first_installment_date"]')
  const container = form.querySelector<HTMLElement>('[data-installments-container]')
  const generateButton = form.querySelector<HTMLButtonElement>('[data-generate-installments]')

  if (!countInput || !totalInput || !entryInput || !firstDateInput || !container || !generateButton) return

  const render = () => {
    const count = Math.max(0, Number(countInput.value || '0'))
    const total = Number((totalInput.value || '').replace(/\./g, '').replace(',', '.')) || 0
    const entry = Number((entryInput.value || '').replace(/\./g, '').replace(',', '.')) || 0
    const remaining = Math.max(0, total - entry)
    const installmentValue = count > 0 ? formatMoneyValue(String(Math.round((remaining / count) * 100))) : ''
    const [day, month, year] = (firstDateInput.value || '').split('/')
    const baseDate = day && month && year ? new Date(Number(year), Number(month) - 1, Number(day)) : new Date()

    container.innerHTML = ''
    for (let i = 0; i < count; i += 1) {
      const currentDate = toBrDate(addMonths(baseDate, i))
      container.insertAdjacentHTML('beforeend', buildInstallmentRow(i, currentDate, installmentValue))
    }
    initMasks()
  }

  generateButton.addEventListener('click', render)
  container.addEventListener('click', (event) => {
    const target = event.target as HTMLElement | null
    if (!target?.matches('[data-remove-installment]')) return
    target.closest('div.rounded-2xl')?.remove()
  })
  render()
}

function initTaskBillingToggle() {
  const form = document.querySelector<HTMLFormElement>('[data-task-form]')
  if (!form) return

  const kind = form.querySelector<HTMLSelectElement>('[data-task-kind]')
  const billableWrap = form.querySelector<HTMLElement>('[data-billable-wrap]')
  const amountInput = billableWrap?.querySelector<HTMLInputElement>('input[name="billable_amount"]')
  if (!kind || !billableWrap || !amountInput) return

  const sync = () => {
    const billable = kind.value === 'out_of_scope' || kind.value === 'one_off'
    billableWrap.hidden = !billable
    amountInput.required = billable
    if (!billable) amountInput.value = ''
  }

  kind.addEventListener('change', sync)
  sync()
}

function initLoadingForms() {
  document.querySelectorAll<HTMLFormElement>('[data-loading-form]').forEach((form) => {
    const button = form.querySelector<HTMLButtonElement>('[data-loading-button]')
    const indicator = form.querySelector<HTMLElement>('[data-loading-indicator]')
    if (!button) return

    const originalText = button.textContent ?? 'Enviar'
    const loadingText = button.dataset.loadingText ?? 'Carregando...'

    form.addEventListener('submit', () => {
      button.disabled = true
      button.textContent = loadingText
      indicator?.classList.remove('hidden')
      window.setTimeout(() => {
        button.disabled = false
        button.textContent = originalText
        indicator?.classList.add('hidden')
      }, 10000)
    })
  })
}

function initLoadingLinks() {
  document.querySelectorAll<HTMLAnchorElement>('[data-loading-link]').forEach((link) => {
    link.addEventListener('click', () => {
      link.setAttribute('aria-busy', 'true')
      link.classList.add('opacity-70', 'animate-pulse', 'pointer-events-none')
    })
  })
}

function initKanbanBoard() {
  const board = document.querySelector<HTMLElement>('[data-kanban-board]')
  if (!board) return

  const feedback = document.querySelector<HTMLElement>('[data-kanban-feedback]')
  const csrf = board.dataset.kanbanCsrf ?? ''
  let draggedCard: HTMLElement | null = null
  let originColumn: HTMLElement | null = null
  let originNextSibling: Element | null = null

  const showFeedback = (type: 'success' | 'error' | 'loading', message: string) => {
    if (!feedback) return
    feedback.textContent = message
    feedback.classList.remove('hidden', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-700', 'border-red-200', 'bg-red-50', 'text-red-700', 'border-violet-200', 'bg-violet-50', 'text-violet-700')
    if (type === 'success') feedback.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700')
    if (type === 'error') feedback.classList.add('border-red-200', 'bg-red-50', 'text-red-700')
    if (type === 'loading') feedback.classList.add('border-violet-200', 'bg-violet-50', 'text-violet-700')
  }

  const syncColumnCounters = () => {
    board.querySelectorAll<HTMLElement>('[data-kanban-column]').forEach((column) => {
      const wrapper = column.closest('div.rounded-2xl')
      const badge = wrapper?.querySelector<HTMLElement>('span.rounded-full')
      if (!badge) return
      badge.textContent = `${column.querySelectorAll('[data-kanban-card]').length}`
    })
  }

  const getDragAfterElement = (container: HTMLElement, y: number): HTMLElement | null => {
    const cards = [...container.querySelectorAll<HTMLElement>('[data-kanban-card]:not(.opacity-60)')]
    let closest: { offset: number; element: HTMLElement | null } = { offset: Number.NEGATIVE_INFINITY, element: null }

    cards.forEach((card) => {
      const box = card.getBoundingClientRect()
      const offset = y - box.top - box.height / 2
      if (offset < 0 && offset > closest.offset) {
        closest = { offset, element: card }
      }
    })

    return closest.element
  }

  board.querySelectorAll<HTMLElement>('[data-kanban-card]').forEach((card) => {
    if (card.getAttribute('draggable') !== 'true') return

    card.addEventListener('dragstart', () => {
      draggedCard = card
      originColumn = card.parentElement as HTMLElement | null
      originNextSibling = card.nextElementSibling
      card.classList.add('opacity-60')
    })

    card.addEventListener('dragend', () => {
      card.classList.remove('opacity-60')
    })
  })

  board.querySelectorAll<HTMLElement>('[data-kanban-column]').forEach((column) => {
    column.addEventListener('dragover', (event) => {
      event.preventDefault()
      if (!draggedCard) return
      const afterElement = getDragAfterElement(column, event.clientY)
      if (afterElement) column.insertBefore(draggedCard, afterElement)
      else column.appendChild(draggedCard)
    })

    column.addEventListener('drop', async (event) => {
      event.preventDefault()
      if (!draggedCard || !originColumn) return

      const taskId = draggedCard.dataset.taskId
      if (!taskId) return

      const status = column.dataset.kanbanColumn ?? ''
      const orderedIds = [...column.querySelectorAll<HTMLElement>('[data-kanban-card]')].map((item) => item.dataset.taskId ?? '').filter(Boolean)
      board.classList.add('pointer-events-none', 'opacity-70')
      showFeedback('loading', 'Salvando movimentação do Kanban...')

      try {
        const formData = new FormData()
        formData.append('_csrf', csrf)
        formData.append('status', status)
        orderedIds.forEach((id) => formData.append('ordered_ids[]', id))

        const response = await fetch(`/tarefas/${taskId}/mover`, {
          method: 'POST',
          body: formData,
          headers: { Accept: 'application/json' },
        })

        const result = await response.json() as { success?: boolean; message?: string }
        if (!response.ok || !result.success) {
          throw new Error(result.message || 'Nao foi possivel mover a tarefa.')
        }

        showFeedback('success', result.message || 'Tarefa movida com sucesso.')
        applyTaskCardState(draggedCard, status)
        syncColumnCounters()
      } catch (error) {
        if (originNextSibling) originColumn.insertBefore(draggedCard, originNextSibling)
        else originColumn.appendChild(draggedCard)
        syncColumnCounters()
        const message = error instanceof Error ? error.message : 'Nao foi possivel mover a tarefa.'
        showFeedback('error', message)
      } finally {
        board.classList.remove('pointer-events-none', 'opacity-70')
        draggedCard = null
        originColumn = null
        originNextSibling = null
      }
    })
  })
}

function initTaskClientFilter() {
  const select = document.querySelector<HTMLSelectElement>('[data-task-client-filter]')
  if (!select) return

  const clearBtn = document.querySelector<HTMLButtonElement>('[data-task-client-clear]')
  const listRows = () => [...document.querySelectorAll<HTMLElement>('[data-task-row]')]
  const kanbanCards = () => [...document.querySelectorAll<HTMLElement>('[data-kanban-card]')]
  const dynamicEmpty = document.querySelector<HTMLElement>('[data-task-list-empty-dynamic]')
  const storageKey = 'tasks.client.filter'

  const hasOption = (value: string): boolean => {
    if (value === '') return true
    return [...select.options].some((opt) => opt.value === value)
  }

  const applyTaskCardState = (card: HTMLElement, status: string) => {
    const done = status === 'done'
    card.dataset.taskStatus = status
    card.classList.toggle('task-card--done', done)
    card.classList.toggle('task-card--pending', !done)
  }

  board.querySelectorAll<HTMLElement>('[data-kanban-card]').forEach((card) => {
    applyTaskCardState(card, (card.dataset.taskStatus ?? '').trim())
  })

  const updateUrl = (value: string) => {
    const url = new URL(window.location.href)
    if (value === '') url.searchParams.delete('client')
    else url.searchParams.set('client', value)
    url.searchParams.delete('page')
    window.history.replaceState({}, '', url.toString())
  }

  const syncKanbanCounters = () => {
    document.querySelectorAll<HTMLElement>('[data-kanban-column]').forEach((column) => {
      const wrapper = column.closest('div.rounded-2xl')
      const badge = wrapper?.querySelector<HTMLElement>('span.rounded-full')
      if (!badge) return
      const visible = [...column.querySelectorAll<HTMLElement>('[data-kanban-card]')]
        .filter((card) => !card.classList.contains('hidden')).length
      badge.textContent = `${visible}`
    })
  }

  const applyFilter = (value: string) => {
    const normalized = value.trim()

    listRows().forEach((row) => {
      const clientId = (row.dataset.taskClientId ?? '').trim()
      const show = normalized === '' || clientId === normalized
      row.classList.toggle('hidden', !show)
    })

    kanbanCards().forEach((card) => {
      const clientId = (card.dataset.taskClientId ?? '').trim()
      const show = normalized === '' || clientId === normalized
      card.classList.toggle('hidden', !show)
    })

    const visibleRows = listRows().filter((row) => !row.classList.contains('hidden')).length
    if (dynamicEmpty) {
      dynamicEmpty.classList.toggle('hidden', visibleRows !== 0)
    }
    syncKanbanCounters()
  }

  const paramValue = new URL(window.location.href).searchParams.get('client') ?? ''
  const storedValue = localStorage.getItem(storageKey) ?? ''
  const initial = hasOption(paramValue) ? paramValue : (hasOption(storedValue) ? storedValue : '')
  select.value = initial
  applyFilter(initial)
  localStorage.setItem(storageKey, initial)
  updateUrl(initial)

  select.addEventListener('change', () => {
    const value = select.value
    localStorage.setItem(storageKey, value)
    updateUrl(value)
    applyFilter(value)
  })

  clearBtn?.addEventListener('click', () => {
    select.value = ''
    localStorage.setItem(storageKey, '')
    updateUrl('')
    applyFilter('')
  })
}

function initFinanceInstallmentPlan() {
  const form = document.querySelector<HTMLFormElement>('[data-finance-plan-form]')
  if (!form) return

  const project = form.querySelector<HTMLSelectElement>('[data-finance-project]')
  const clientId = form.querySelector<HTMLInputElement>('[data-finance-client-id]')
  const total = form.querySelector<HTMLInputElement>('[data-finance-total]')
  const entry = form.querySelector<HTMLInputElement>('[data-finance-entry]')
  const entryDate = form.querySelector<HTMLInputElement>('[data-finance-entry-date]')
  const count = form.querySelector<HTMLInputElement>('[data-finance-count]')
  const firstDate = form.querySelector<HTMLInputElement>('[data-finance-first-date]')
  const remaining = form.querySelector<HTMLInputElement>('[data-finance-remaining]')
  const installment = form.querySelector<HTMLInputElement>('[data-finance-installment]')
  const errorBox = form.querySelector<HTMLElement>('[data-finance-plan-error]')

  if (!project || !clientId || !total || !entry || !entryDate || !count || !firstDate || !remaining || !installment || !errorBox) return

  let touched = false

  const parseMoney = (value: string): number => {
    const n = Number((value || '').replace(/\./g, '').replace(',', '.'))
    return Number.isFinite(n) ? n : 0
  }

  const showError = (message: string) => {
    if (message === '') {
      errorBox.classList.add('hidden')
      errorBox.textContent = ''
      return
    }
    errorBox.textContent = message
    errorBox.classList.remove('hidden')
  }

  const toIso = (br: string): string | null => {
    const [d, m, y] = (br || '').split('/')
    if (!d || !m || !y) return null
    if (d.length !== 2 || m.length !== 2 || y.length !== 4) return null
    return `${y}-${m}-${d}`
  }

  const syncFromProject = () => {
    touched = true
    const opt = project.selectedOptions[0] as HTMLOptionElement | undefined
    const cid = opt?.dataset.clientId ?? ''
    const t = opt?.dataset.total ?? ''
    clientId.value = cid
    if ((total.value || '').trim() === '' && t !== '') total.value = t
    initMasks()
    sync()
  }

  const sync = () => {
    const isEmpty = project.value === '' && total.value.trim() === '' && entry.value.trim() === '' && entryDate.value.trim() === '' && firstDate.value.trim() === ''
    if (!touched && isEmpty) {
      remaining.value = 'R$ 0,00'
      installment.value = 'R$ 0,00'
      showError('')
      return
    }

    const totalValue = parseMoney(total.value)
    const entryValue = parseMoney(entry.value)
    const c = Math.max(0, Number(count.value || '0'))
    const hasEntry = entry.value.trim() !== '' && entryValue > 0

    if (totalValue <= 0) {
      remaining.value = 'R$ 0,00'
      installment.value = 'R$ 0,00'
      showError('Informe um valor total válido.')
      return
    }

    if (hasEntry && entryValue > totalValue) {
      showError('O valor de entrada não pode ser maior que o valor total.')
    } else if (hasEntry && entryDate.value.trim() === '') {
      showError('Informe a data da entrada quando houver valor de entrada.')
    } else if (!hasEntry) {
      showError('')
    }

    const remainingValue = Math.max(0, totalValue - (hasEntry ? entryValue : 0))
    remaining.value = `R$ ${formatMoneyValue(String(Math.round(remainingValue * 100)))}`

    if (remainingValue > 0 && c <= 0) {
      installment.value = 'R$ 0,00'
      showError('Informe a quantidade de parcelas.')
      return
    }

    const installmentValue = remainingValue > 0 ? remainingValue / Math.max(1, c) : 0
    installment.value = `R$ ${formatMoneyValue(String(Math.round(installmentValue * 100)))}`

    const entryIso = toIso(entryDate.value.trim())
    const firstIso = toIso(firstDate.value.trim())
    if (hasEntry && entryIso && firstIso && firstIso <= entryIso) {
      showError('A data da primeira parcela deve ser maior que a data da entrada.')
      return
    }

    if (remainingValue > 0 && firstDate.value.trim() === '') {
      showError('Informe a data da primeira parcela.')
    }
  }

  project.addEventListener('change', syncFromProject)
  total.addEventListener('input', () => { touched = true; sync() })
  entry.addEventListener('input', () => { touched = true; sync() })
  entryDate.addEventListener('input', () => { touched = true; sync() })
  count.addEventListener('input', () => { touched = true; sync() })
  firstDate.addEventListener('input', () => { touched = true; sync() })

  syncFromProject()
}

document.addEventListener('DOMContentLoaded', () => {
  initTheme()
  initMasks()
  initInstallmentBuilder()
  initTaskBillingToggle()
  initLoadingForms()
  initLoadingLinks()
  initTaskClientFilter()
  initKanbanBoard()
  initFinanceInstallmentPlan()
})

