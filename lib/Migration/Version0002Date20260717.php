<?php
namespace OCA\StickyCRM\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0002Date20260717 extends SimpleMigrationStep {

    public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
    }

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $stickyMappingTable = 'sticky_client_contacts';
        $stickyClientsTable = 'sticky_clients';

        if (!$schema->hasTable($stickyMappingTable)) {
            $table = $schema->createTable($stickyMappingTable);

            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);

            $table->addColumn('client_id', 'integer', [
                'notnull' => true,
                'unsigned' => true,
                'comment' => 'ID aus der sticky_clients Tabelle',
            ]);

            $table->addColumn('card_id', 'integer', [
                'notnull' => true,
                'unsigned' => true,
                'comment' => 'ID aus der Nextcloud oc_cards Tabelle',
            ]);

            $table->addColumn('created_at', 'datetime', [
                'notnull' => true,
                'default' => 'CURRENT_TIMESTAMP',
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['client_id'], 'sticky_client_idx');
            $table->addIndex(['card_id'], 'sticky_card_idx');

            $table->addUniqueIndex(['client_id', 'card_id'], 'sticky_uniq_mapping');
            $table->addForeignKeyConstraint(
                $schema->getTable($stickyClientsTable),
                ['client_id'],
                ['id'],
                ['onDelete' => 'CASCADE'],
                'sticky_crm_cc_client_fk'
            );
        }

        return $schema;
    }

    public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
    }
}