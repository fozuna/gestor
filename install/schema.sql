CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL DEFAULT 'Administrador',
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tenants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NULL,
  logo_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tenants_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS memberships (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('admin','manager','user') NOT NULL DEFAULT 'user',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_membership (tenant_id, user_id),
  KEY idx_memberships_tenant_id (tenant_id),
  KEY idx_memberships_user_id (user_id),
  KEY idx_memberships_role (role),
  CONSTRAINT fk_memberships_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  company_name VARCHAR(120) NULL,
  company_email VARCHAR(255) NULL,
  company_phone VARCHAR(50) NULL,
  logo_path VARCHAR(255) NULL,
  primary_color VARCHAR(7) NOT NULL DEFAULT '#FE5516',
  secondary_color VARCHAR(7) NOT NULL DEFAULT '#F5F5DC',
  accent_color VARCHAR(7) NOT NULL DEFAULT '#E8D9BB',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_tenant_id (tenant_id),
  CONSTRAINT fk_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(200) NOT NULL,
  legal_name VARCHAR(200) NULL,
  document_number VARCHAR(50) NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(50) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clients_tenant_id (tenant_id),
  KEY idx_clients_status (status),
  KEY idx_clients_name (name),
  CONSTRAINT fk_clients_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS client_interactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  interaction_type VARCHAR(60) NOT NULL,
  content TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_client_interactions_tenant_id (tenant_id),
  KEY idx_client_interactions_client_id (client_id),
  KEY idx_client_interactions_user_id (user_id),
  CONSTRAINT fk_client_interactions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_client_interactions_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_client_interactions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contract_templates (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(180) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contract_templates_tenant_id (tenant_id),
  KEY idx_contract_templates_default (is_default),
  CONSTRAINT fk_contract_templates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quotes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(60) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  status ENUM('draft','sent','approved','rejected') NOT NULL DEFAULT 'draft',
  issue_date DATE NULL,
  valid_until DATE NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  pdf_path VARCHAR(255) NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_quotes_tenant_code (tenant_id, code),
  KEY idx_quotes_tenant_id (tenant_id),
  KEY idx_quotes_client_id (client_id),
  KEY idx_quotes_status (status),
  CONSTRAINT fk_quotes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_quotes_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_quotes_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quote_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  quote_id BIGINT UNSIGNED NOT NULL,
  position INT NOT NULL DEFAULT 0,
  service_name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  quantity DECIMAL(12,2) NOT NULL DEFAULT 1.00,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_quote_items_quote_id (quote_id),
  CONSTRAINT fk_quote_items_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projects (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  quote_id BIGINT UNSIGNED NULL,
  name VARCHAR(200) NOT NULL,
  description TEXT NULL,
  status ENUM('active','paused','done') NOT NULL DEFAULT 'active',
  start_date DATE NULL,
  due_date DATE NULL,
  budget_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  contract_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_terms TEXT NULL,
  entry_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  installments_count INT UNSIGNED NOT NULL DEFAULT 0,
  first_installment_date DATE NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_projects_tenant_id (tenant_id),
  KEY idx_projects_client_id (client_id),
  KEY idx_projects_status (status),
  KEY idx_projects_quote_id (quote_id),
  KEY idx_projects_created_by (created_by_user_id),
  CONSTRAINT fk_projects_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_projects_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_projects_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_projects_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contracts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NULL,
  quote_id BIGINT UNSIGNED NULL,
  template_id BIGINT UNSIGNED NULL,
  code VARCHAR(60) NOT NULL,
  title VARCHAR(180) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  status ENUM('draft','sent','signed','cancelled') NOT NULL DEFAULT 'draft',
  pdf_path VARCHAR(255) NULL,
  signed_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_contracts_tenant_code (tenant_id, code),
  KEY idx_contracts_tenant_id (tenant_id),
  KEY idx_contracts_client_id (client_id),
  KEY idx_contracts_project_id (project_id),
  KEY idx_contracts_quote_id (quote_id),
  KEY idx_contracts_template_id (template_id),
  KEY idx_contracts_status (status),
  CONSTRAINT fk_contracts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_contracts_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_contracts_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_contracts_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_contracts_template FOREIGN KEY (template_id) REFERENCES contract_templates (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_contracts_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_timelines (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(60) NOT NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_project_timelines_tenant_id (tenant_id),
  KEY idx_project_timelines_project_id (project_id),
  KEY idx_project_timelines_user_id (user_id),
  CONSTRAINT fk_project_timelines_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_project_timelines_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_project_timelines_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('todo','doing','done') NOT NULL DEFAULT 'todo',
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  task_kind ENUM('out_of_scope','one_off','in_scope') NOT NULL DEFAULT 'in_scope',
  billing_type ENUM('billable','informative') NOT NULL DEFAULT 'informative',
  billable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  position INT NOT NULL DEFAULT 0,
  assignee_user_id BIGINT UNSIGNED NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  due_date DATE NULL,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tasks_tenant_id (tenant_id),
  KEY idx_tasks_project_id (project_id),
  KEY idx_tasks_status (status),
  KEY idx_tasks_assignee_user_id (assignee_user_id),
  KEY idx_tasks_due_date (due_date),
  CONSTRAINT fk_tasks_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_tasks_assignee FOREIGN KEY (assignee_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_tasks_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS task_comments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  task_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_task_comments_tenant_id (tenant_id),
  KEY idx_task_comments_task_id (task_id),
  KEY idx_task_comments_user_id (user_id),
  CONSTRAINT fk_task_comments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_task_comments_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_task_comments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NULL,
  quote_id BIGINT UNSIGNED NULL,
  code VARCHAR(60) NOT NULL,
  description TEXT NULL,
  amount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('pending','paid','overdue') NOT NULL DEFAULT 'pending',
  installment_type ENUM('entry','monthly','task_charge') NOT NULL DEFAULT 'monthly',
  installment_number INT UNSIGNED NOT NULL DEFAULT 1,
  reference_month TINYINT UNSIGNED NULL,
  reference_year SMALLINT UNSIGNED NULL,
  source_task_id BIGINT UNSIGNED NULL,
  payment_terms TEXT NULL,
  due_date DATE NOT NULL,
  paid_at DATETIME NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoices_tenant_code (tenant_id, code),
  KEY idx_invoices_tenant_id (tenant_id),
  KEY idx_invoices_client_id (client_id),
  KEY idx_invoices_project_id (project_id),
  KEY idx_invoices_status (status),
  KEY idx_invoices_due_date (due_date),
  KEY idx_invoices_reference_period (reference_year, reference_month),
  KEY idx_invoices_source_task_id (source_task_id),
  CONSTRAINT fk_invoices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_invoices_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_invoices_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_invoices_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_invoices_source_task FOREIGN KEY (source_task_id) REFERENCES tasks (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_invoices_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  invoice_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  payment_method VARCHAR(60) NULL,
  note TEXT NULL,
  receipt_number VARCHAR(80) NULL,
  receipt_path VARCHAR(255) NULL,
  receipt_generated_at DATETIME NULL,
  receipt_size_bytes INT UNSIGNED NULL,
  received_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payments_tenant_id (tenant_id),
  KEY idx_payments_invoice_id (invoice_id),
  UNIQUE KEY uq_payments_receipt_number (receipt_number),
  KEY idx_payments_receipt_number (receipt_number),
  KEY idx_payments_paid_at (paid_at),
  CONSTRAINT fk_payments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_payments_received_by FOREIGN KEY (received_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS receipt_sequences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scope_key VARCHAR(40) NOT NULL,
  current_value INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_receipt_sequences_scope_key (scope_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NULL,
  project_id BIGINT UNSIGNED NULL,
  invoice_id BIGINT UNSIGNED NULL,
  type ENUM('income','expense') NOT NULL,
  category VARCHAR(120) NULL,
  amount DECIMAL(12,2) NOT NULL,
  entry_date DATE NOT NULL,
  status ENUM('pending','settled','overdue','cancelled') NOT NULL DEFAULT 'pending',
  note TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_financial_entries_tenant_id (tenant_id),
  KEY idx_financial_entries_client_id (client_id),
  KEY idx_financial_entries_project_id (project_id),
  KEY idx_financial_entries_invoice_id (invoice_id),
  KEY idx_financial_entries_type (type),
  KEY idx_financial_entries_status (status),
  KEY idx_financial_entries_entry_date (entry_date),
  CONSTRAINT fk_financial_entries_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_financial_entries_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_financial_entries_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_financial_entries_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_financial_entries_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installment_plans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NOT NULL,
  valor_total DECIMAL(12,2) NOT NULL,
  valor_entrada DECIMAL(12,2) NULL,
  data_entrada DATE NULL,
  saldo_restante DECIMAL(12,2) NOT NULL,
  quantidade_parcelas INT UNSIGNED NOT NULL DEFAULT 0,
  valor_parcela DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  data_primeira_parcela DATE NULL,
  formas_pagamento TEXT NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_installment_plans_tenant_project (tenant_id, project_id),
  KEY idx_installment_plans_tenant_id (tenant_id),
  KEY idx_installment_plans_project_id (project_id),
  CONSTRAINT fk_installment_plans_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_installment_plans_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_installment_plans_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installment_anticipations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NULL,
  adjustment_mode ENUM('none','discount','interest') NOT NULL DEFAULT 'none',
  adjustment_rate DECIMAL(7,4) NOT NULL DEFAULT 0.0000,
  base_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  adjustment_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_date DATE NOT NULL,
  payment_method VARCHAR(60) NOT NULL,
  note TEXT NULL,
  processed_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_installment_anticipations_tenant_id (tenant_id),
  KEY idx_installment_anticipations_client_id (client_id),
  KEY idx_installment_anticipations_project_id (project_id),
  KEY idx_installment_anticipations_payment_date (payment_date),
  CONSTRAINT fk_installment_anticipations_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_installment_anticipations_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_installment_anticipations_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_installment_anticipations_user FOREIGN KEY (processed_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS installment_anticipation_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  anticipation_id BIGINT UNSIGNED NOT NULL,
  invoice_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NOT NULL,
  original_due_date DATE NOT NULL,
  original_amount_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  original_amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  settled_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  adjustment_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  receipt_number VARCHAR(80) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_installment_anticipation_invoice (anticipation_id, invoice_id),
  KEY idx_installment_anticipation_items_anticipation_id (anticipation_id),
  KEY idx_installment_anticipation_items_invoice_id (invoice_id),
  KEY idx_installment_anticipation_items_payment_id (payment_id),
  CONSTRAINT fk_installment_anticipation_items_anticipation FOREIGN KEY (anticipation_id) REFERENCES installment_anticipations (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_installment_anticipation_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_installment_anticipation_items_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orcamentos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  nome_proposta VARCHAR(180) NOT NULL,
  descricao TEXT NULL,
  valor_total DECIMAL(12,2) NOT NULL,
  valor_entrada DECIMAL(12,2) NULL,
  quantidade_parcelas INT UNSIGNED NOT NULL DEFAULT 0,
  valor_parcela DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  data_primeira_parcela DATE NULL,
  condicoes_pagamento TEXT NULL,
  status ENUM('rascunho','enviado','aprovado','recusado') NOT NULL DEFAULT 'rascunho',
  data_validade DATE NULL,
  projeto_id BIGINT UNSIGNED NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_orcamentos_tenant_id (tenant_id),
  KEY idx_orcamentos_client_id (client_id),
  KEY idx_orcamentos_status (status),
  KEY idx_orcamentos_projeto_id (projeto_id),
  CONSTRAINT fk_orcamentos_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_orcamentos_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_orcamentos_projeto FOREIGN KEY (projeto_id) REFERENCES projects (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_orcamentos_created_by FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS action_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NULL,
  entity_type VARCHAR(120) NULL,
  entity_id BIGINT UNSIGNED NULL,
  action VARCHAR(160) NOT NULL,
  meta_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_action_logs_tenant_id (tenant_id),
  KEY idx_action_logs_user_id (user_id),
  KEY idx_action_logs_entity (entity_type, entity_id),
  CONSTRAINT fk_action_logs_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_action_logs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS trg_invoices_project_financial_guard_insert;
DROP TRIGGER IF EXISTS trg_invoices_project_financial_guard_update;
DROP TRIGGER IF EXISTS trg_financial_entries_project_guard_insert;
DROP TRIGGER IF EXISTS trg_financial_entries_project_guard_update;

CREATE TRIGGER trg_invoices_project_financial_guard_insert
BEFORE INSERT ON invoices
FOR EACH ROW
BEGIN
  IF NEW.installment_type IN ('entry', 'monthly') AND NEW.project_id IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Parcelas de projeto exigem project_id.';
  END IF;
END;

CREATE TRIGGER trg_invoices_project_financial_guard_update
BEFORE UPDATE ON invoices
FOR EACH ROW
BEGIN
  IF NEW.installment_type IN ('entry', 'monthly') AND NEW.project_id IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Parcelas de projeto exigem project_id.';
  END IF;
END;

CREATE TRIGGER trg_financial_entries_project_guard_insert
BEFORE INSERT ON financial_entries
FOR EACH ROW
BEGIN
  IF NEW.category = 'project_installment' AND NEW.project_id IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Lançamento de parcela de projeto exige project_id.';
  END IF;
END;

CREATE TRIGGER trg_financial_entries_project_guard_update
BEFORE UPDATE ON financial_entries
FOR EACH ROW
BEGIN
  IF NEW.category = 'project_installment' AND NEW.project_id IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Lançamento de parcela de projeto exige project_id.';
  END IF;
END;

