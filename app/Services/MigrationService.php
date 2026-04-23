<?php
declare(strict_types=1);

namespace App\Services;

use App\Helpers\DB;
use PDO;

final class MigrationService
{
    public function migrate(): array
    {
        $pdo = DB::pdo();
        $changes = [];

        $this->ensureColumn($pdo, 'projects', 'contract_value', "ALTER TABLE projects ADD COLUMN contract_value DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER budget_total", $changes);
        $this->ensureColumn($pdo, 'projects', 'payment_terms', "ALTER TABLE projects ADD COLUMN payment_terms TEXT NULL AFTER contract_value", $changes);
        $this->ensureColumn($pdo, 'projects', 'entry_amount', "ALTER TABLE projects ADD COLUMN entry_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER payment_terms", $changes);
        $this->ensureColumn($pdo, 'projects', 'installments_count', "ALTER TABLE projects ADD COLUMN installments_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER entry_amount", $changes);
        $this->ensureColumn($pdo, 'projects', 'first_installment_date', "ALTER TABLE projects ADD COLUMN first_installment_date DATE NULL AFTER installments_count", $changes);

        $this->ensureColumn($pdo, 'tasks', 'task_kind', "ALTER TABLE tasks ADD COLUMN task_kind ENUM('out_of_scope','one_off','in_scope') NOT NULL DEFAULT 'in_scope' AFTER priority", $changes);
        $this->ensureColumn($pdo, 'tasks', 'billing_type', "ALTER TABLE tasks ADD COLUMN billing_type ENUM('billable','informative') NOT NULL DEFAULT 'informative' AFTER task_kind", $changes);
        $this->ensureColumn($pdo, 'tasks', 'billable_amount', "ALTER TABLE tasks ADD COLUMN billable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER billing_type", $changes);

        $this->ensureColumn($pdo, 'invoices', 'installment_type', "ALTER TABLE invoices ADD COLUMN installment_type ENUM('entry','monthly','task_charge') NOT NULL DEFAULT 'monthly' AFTER status", $changes);
        $this->ensureColumn($pdo, 'invoices', 'installment_number', "ALTER TABLE invoices ADD COLUMN installment_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER installment_type", $changes);
        $this->ensureColumn($pdo, 'invoices', 'reference_month', "ALTER TABLE invoices ADD COLUMN reference_month TINYINT UNSIGNED NULL AFTER installment_number", $changes);
        $this->ensureColumn($pdo, 'invoices', 'reference_year', "ALTER TABLE invoices ADD COLUMN reference_year SMALLINT UNSIGNED NULL AFTER reference_month", $changes);
        $this->ensureColumn($pdo, 'invoices', 'source_task_id', "ALTER TABLE invoices ADD COLUMN source_task_id BIGINT UNSIGNED NULL AFTER reference_year", $changes);
        $this->ensureColumn($pdo, 'invoices', 'payment_terms', "ALTER TABLE invoices ADD COLUMN payment_terms TEXT NULL AFTER source_task_id", $changes);
        $this->ensureColumn($pdo, 'payments', 'receipt_number', "ALTER TABLE payments ADD COLUMN receipt_number VARCHAR(80) NULL AFTER note", $changes);
        $this->ensureColumn($pdo, 'payments', 'receipt_path', "ALTER TABLE payments ADD COLUMN receipt_path VARCHAR(255) NULL AFTER receipt_number", $changes);
        $this->ensureColumn($pdo, 'payments', 'receipt_generated_at', "ALTER TABLE payments ADD COLUMN receipt_generated_at DATETIME NULL AFTER receipt_path", $changes);
        $this->ensureColumn($pdo, 'payments', 'receipt_size_bytes', "ALTER TABLE payments ADD COLUMN receipt_size_bytes INT UNSIGNED NULL AFTER receipt_generated_at", $changes);

        $this->ensureIndex($pdo, 'invoices', 'idx_invoices_reference_period', 'ALTER TABLE invoices ADD INDEX idx_invoices_reference_period (reference_year, reference_month)', $changes);
        $this->ensureIndex($pdo, 'invoices', 'idx_invoices_source_task_id', 'ALTER TABLE invoices ADD INDEX idx_invoices_source_task_id (source_task_id)', $changes);
        $this->ensureIndex($pdo, 'payments', 'idx_payments_receipt_number', 'ALTER TABLE payments ADD INDEX idx_payments_receipt_number (receipt_number)', $changes);
        $this->ensureIndex($pdo, 'payments', 'uq_payments_receipt_number', 'ALTER TABLE payments ADD UNIQUE INDEX uq_payments_receipt_number (receipt_number)', $changes);

        $this->ensureTable($pdo, 'installment_plans', 'CREATE TABLE installment_plans (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $changes);

        $this->ensureTable($pdo, 'receipt_sequences', 'CREATE TABLE receipt_sequences (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  scope_key VARCHAR(40) NOT NULL,
  current_value INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_receipt_sequences_scope_key (scope_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $changes);

        $this->ensureTable($pdo, 'installment_anticipations', 'CREATE TABLE installment_anticipations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  project_id BIGINT UNSIGNED NULL,
  adjustment_mode ENUM("none","discount","interest") NOT NULL DEFAULT "none",
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $changes);

        $this->ensureTable($pdo, 'installment_anticipation_items', 'CREATE TABLE installment_anticipation_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $changes);

        $this->ensureTable($pdo, 'orcamentos', 'CREATE TABLE orcamentos (
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
  status ENUM("rascunho","enviado","aprovado","recusado") NOT NULL DEFAULT "rascunho",
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $changes);

        $this->ensureForeignKeyDeleteRule(
            $pdo,
            'invoices',
            'fk_invoices_project',
            'project_id',
            'CASCADE',
            'ALTER TABLE invoices ADD CONSTRAINT fk_invoices_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE',
            $changes
        );
        $this->ensureForeignKeyDeleteRule(
            $pdo,
            'financial_entries',
            'fk_financial_entries_project',
            'project_id',
            'CASCADE',
            'ALTER TABLE financial_entries ADD CONSTRAINT fk_financial_entries_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE',
            $changes
        );
        $this->ensureForeignKeyDeleteRule(
            $pdo,
            'financial_entries',
            'fk_financial_entries_invoice',
            'invoice_id',
            'CASCADE',
            'ALTER TABLE financial_entries ADD CONSTRAINT fk_financial_entries_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE ON UPDATE CASCADE',
            $changes
        );
        $this->ensureForeignKeyDeleteRule(
            $pdo,
            'installment_plans',
            'fk_installment_plans_project',
            'project_id',
            'CASCADE',
            'ALTER TABLE installment_plans ADD CONSTRAINT fk_installment_plans_project FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE ON UPDATE CASCADE',
            $changes
        );
        $this->ensureTrigger(
            $pdo,
            'trg_invoices_project_financial_guard_insert',
            'CREATE TRIGGER trg_invoices_project_financial_guard_insert
             BEFORE INSERT ON invoices
             FOR EACH ROW
             BEGIN
                 IF NEW.installment_type IN ("entry", "monthly") AND NEW.project_id IS NULL THEN
                     SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "Parcelas de projeto exigem project_id.";
                 END IF;
             END',
            $changes
        );
        $this->ensureTrigger(
            $pdo,
            'trg_invoices_project_financial_guard_update',
            'CREATE TRIGGER trg_invoices_project_financial_guard_update
             BEFORE UPDATE ON invoices
             FOR EACH ROW
             BEGIN
                 IF NEW.installment_type IN ("entry", "monthly") AND NEW.project_id IS NULL THEN
                     SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "Parcelas de projeto exigem project_id.";
                 END IF;
             END',
            $changes
        );
        $this->ensureTrigger(
            $pdo,
            'trg_financial_entries_project_guard_insert',
            'CREATE TRIGGER trg_financial_entries_project_guard_insert
             BEFORE INSERT ON financial_entries
             FOR EACH ROW
             BEGIN
                 IF NEW.category = "project_installment" AND NEW.project_id IS NULL THEN
                     SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "Lançamento de parcela de projeto exige project_id.";
                 END IF;
             END',
            $changes
        );
        $this->ensureTrigger(
            $pdo,
            'trg_financial_entries_project_guard_update',
            'CREATE TRIGGER trg_financial_entries_project_guard_update
             BEFORE UPDATE ON financial_entries
             FOR EACH ROW
             BEGIN
                 IF NEW.category = "project_installment" AND NEW.project_id IS NULL THEN
                     SIGNAL SQLSTATE "45000" SET MESSAGE_TEXT = "Lançamento de parcela de projeto exige project_id.";
                 END IF;
             END',
            $changes
        );

        return $changes;
    }

    private function ensureColumn(PDO $pdo, string $table, string $column, string $sql, array &$changes): void
    {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name'
        );
        $st->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        if ((int)$st->fetchColumn() === 0) {
            $pdo->exec($sql);
            $changes[] = $table . '.' . $column;
        }
    }

    private function ensureIndex(PDO $pdo, string $table, string $index, string $sql, array &$changes): void
    {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table_name AND index_name = :index_name'
        );
        $st->execute([
            ':table_name' => $table,
            ':index_name' => $index,
        ]);

        if ((int)$st->fetchColumn() === 0) {
            $pdo->exec($sql);
            $changes[] = $index;
        }
    }

    private function ensureTable(PDO $pdo, string $table, string $sql, array &$changes): void
    {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name'
        );
        $st->execute([':table_name' => $table]);

        if ((int)$st->fetchColumn() === 0) {
            $pdo->exec($sql);
            $changes[] = 'table:' . $table;
        }
    }

    private function ensureForeignKeyDeleteRule(
        PDO $pdo,
        string $table,
        string $constraintName,
        string $columnName,
        string $expectedDeleteRule,
        string $addSql,
        array &$changes
    ): void {
        $st = $pdo->prepare(
            'SELECT DELETE_RULE
             FROM information_schema.referential_constraints
             WHERE constraint_schema = DATABASE()
               AND table_name = :table_name
               AND constraint_name = :constraint_name
             LIMIT 1'
        );
        $st->execute([
            ':table_name' => $table,
            ':constraint_name' => $constraintName,
        ]);
        $rule = strtoupper((string)($st->fetchColumn() ?: ''));
        if ($rule !== strtoupper($expectedDeleteRule)) {
            // Remove qualquer FK existente no mesmo campo (inclusive legadas com nome diferente).
            $existingSt = $pdo->prepare(
                'SELECT DISTINCT constraint_name
                 FROM information_schema.key_column_usage
                 WHERE table_schema = DATABASE()
                   AND table_name = :table_name
                   AND column_name = :column_name
                   AND referenced_table_name IS NOT NULL'
            );
            $existingSt->execute([
                ':table_name' => $table,
                ':column_name' => $columnName,
            ]);
            $existing = $existingSt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($existing as $existingName) {
                if (!is_string($existingName) || $existingName === '') {
                    continue;
                }
                $pdo->exec(sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $table, $existingName));
            }
            $pdo->exec($addSql);
            $changes[] = 'fk:' . $constraintName . ':delete=' . strtoupper($expectedDeleteRule);
        }
    }

    private function ensureTrigger(PDO $pdo, string $triggerName, string $createSql, array &$changes): void
    {
        $st = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.triggers
             WHERE trigger_schema = DATABASE()
               AND trigger_name = :trigger_name'
        );
        $st->execute([':trigger_name' => $triggerName]);
        if ((int)$st->fetchColumn() === 0) {
            $pdo->exec($createSql);
            $changes[] = 'trigger:' . $triggerName;
        }
    }
}
