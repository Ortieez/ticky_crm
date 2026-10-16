<?php

namespace OCA\StickyCRM\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0003Date20261016 extends SimpleMigrationStep {

    private const CLIENTS_TABLE = 'sticky_clients';

    public function __construct(
        private IDBConnection $db,
    ) {
    }

    public function name(): string {
        return 'Add sponsorship CRM columns and custom fields support to sticky_clients';
    }

    public function description(): string {
        return 'Adds assigned_to, tier, categories, emails, phones, and custom_fields columns to sticky_clients.';
    }

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable(self::CLIENTS_TABLE)) {
            return null;
        }

        $table = $schema->getTable(self::CLIENTS_TABLE);
        $changed = false;

        if (!$table->hasColumn('assigned_to')) {
            $table->addColumn('assigned_to', 'string', [
                'length' => 128,
                'notnull' => false,
            ]);
            $changed = true;
        }

        if (!$table->hasColumn('tier')) {
            $table->addColumn('tier', 'string', [
                'length' => 64,
                'notnull' => false,
            ]);
            $changed = true;
        }

        if (!$table->hasColumn('categories')) {
            $table->addColumn('categories', 'text', [
                'notnull' => false,
            ]);
            $changed = true;
        }

        if (!$table->hasColumn('emails')) {
            $table->addColumn('emails', 'text', [
                'notnull' => false,
            ]);
            $changed = true;
        }

        if (!$table->hasColumn('phones')) {
            $table->addColumn('phones', 'text', [
                'notnull' => false,
            ]);
            $changed = true;
        }

        if (!$table->hasColumn('custom_fields')) {
            $table->addColumn('custom_fields', 'text', [
                'notnull' => false,
            ]);
            $changed = true;
        }

        if (!$changed) {
            return null;
        }

        return $schema;
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        if (!$schema->hasTable(self::CLIENTS_TABLE)) {
            return;
        }

        // Backfill emails, phones, and extract assignee from client_number patterns like "Mari-002" or "Jedlicka_011"
        $qb = $this->db->getQueryBuilder();
        $qb->select('id', 'client_number', 'contact_email', 'invoice_email', 'phone', 'assigned_to', 'emails', 'phones')
            ->from(self::CLIENTS_TABLE);
        $result = $qb->executeQuery();
        $rows = $result->fetchAll();
        $result->closeCursor();

        foreach ($rows as $row) {
            $updates = [];

            // Auto-extract assignee if client_number has a person prefix like "Mari-002" or "Jedlicka_011"
            if (empty($row['assigned_to']) && !empty($row['client_number'])) {
                $cn = trim((string)$row['client_number']);
                if (preg_match('/^([A-Za-zÀ-ž]{3,})[-_]\d+$/u', $cn, $matches)) {
                    $prefix = $matches[1];
                    if (!in_array(strtoupper($prefix), ['CLI', 'CUST', 'SPON', 'PART'], true)) {
                        $updates['assigned_to'] = $prefix;
                    }
                }
            }

            // Initialize emails JSON array from existing contact_email / invoice_email if empty
            if (empty($row['emails'])) {
                $emails = [];
                if (!empty($row['contact_email'])) {
                    $emails[] = ['label' => 'Primary', 'email' => $row['contact_email']];
                }
                if (!empty($row['invoice_email']) && $row['invoice_email'] !== $row['contact_email']) {
                    $emails[] = ['label' => 'Billing', 'email' => $row['invoice_email']];
                }
                if (!empty($emails)) {
                    $updates['emails'] = json_encode($emails, JSON_UNESCAPED_UNICODE);
                }
            }

            // Initialize phones JSON array from existing phone if empty
            if (empty($row['phones']) && !empty($row['phone'])) {
                $phones = [
                    ['label' => 'Primary', 'phone' => $row['phone']],
                ];
                $updates['phones'] = json_encode($phones, JSON_UNESCAPED_UNICODE);
            }

            if (!empty($updates)) {
                $uqb = $this->db->getQueryBuilder();
                $uqb->update(self::CLIENTS_TABLE);
                foreach ($updates as $col => $val) {
                    $uqb->set($col, $uqb->createNamedParameter($val));
                }
                $uqb->where($uqb->expr()->eq('id', $uqb->createNamedParameter((int)$row['id'])));
                $uqb->executeStatement();
            }
        }
    }
}
