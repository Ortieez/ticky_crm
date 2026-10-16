<?php
namespace OCA\StickyCRM\DB;

use OCP\AppFramework\Db\QBMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Client>
 */
class ClientMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'sticky_clients', Client::class);
    }

    public function findByUuid(string $uuid, bool $withAddress = true): Client {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->tableName)
            ->where($qb->expr()->eq('uuid', $qb->createNamedParameter($uuid)));

        $client = $this->findEntity($qb);

        if ($withAddress) {
            $client->setAddresses($this->getAddressesForClient($client->getId()));
            $client->setNotes($this->getNotesForClient($client->getId()));
        }

        return $client;
    }

    public function findAllClients(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->tableName)->orderBy('name', 'ASC');

        $clients = $this->findEntities($qb);

        foreach ($clients as $client) {
            $client->setAddresses($this->getAddressesForClient($client->getId()));
            $client->setNotes($this->getNotesForClient($client->getId()));
        }

        return $clients;
    }

    private function getAddressesForClient(int $clientId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('sticky_client_addresses')
            ->where($qb->expr()->eq('client_id', $qb->createNamedParameter($clientId)));

        $result = $qb->executeQuery();
        $addresses = [];

        while ($row = $result->fetch()) {
            $addresses[] = [
                'id'               => (int)$row['id'],
                'client_id'        => (int)$row['client_id'],
                'type'             => $row['type'],
                'label'            => $row['label'],
                'street'           => $row['street'],
                'house_number'     => $row['house_number'],
                'address_addition' => $row['address_addition'],
                'postal_code'      => $row['postal_code'],
                'city'             => $row['city'],
                'country_code'     => $row['country_code']
            ];
        }

        $result->closeCursor();
        return $addresses;
    }

    private function getNotesForClient(int $clientId): array {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('*')
                ->from('sticky_client_notes')
                ->where($qb->expr()->eq('client_id', $qb->createNamedParameter($clientId)))
                ->orderBy('id', 'ASC');

            $result = $qb->executeQuery();
            $notes = [];

            while ($row = $result->fetch()) {
                $notes[] = [
                    'id'         => (int)$row['id'],
                    'client_id'  => (int)$row['client_id'],
                    'user_id'    => (string)($row['user_id'] ?? ''),
                    'type'       => (string)($row['type'] ?? 'note'),
                    'title'      => (string)($row['title'] ?? ''),
                    'content'    => (string)($row['content'] ?? ''),
                    'created_at' => (string)($row['created_at'] ?? ''),
                    'updated_at' => (string)($row['updated_at'] ?? ''),
                ];
            }

            $result->closeCursor();
            return $notes;
        } catch (\Throwable) {
            return [];
        }
    }
}