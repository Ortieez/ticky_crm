<?php
namespace OCA\StickyCRM\Service;

use OCA\StickyCRM\DB\Client;
use OCA\StickyCRM\DB\ClientMapper;
use OCA\StickyCRM\Service\ActivityService;
use OCP\IDBConnection;
use DateTime;
use Exception;

class ClientService {

    public function __construct(
        private ClientMapper $mapper,
        private AddressService $addressService,
        private IDBConnection $db,
        private ActivityService $activityService
    ) {}

    public function all(): array {
        return $this->mapper->findAllClients();
    }

    public function find(string $uuid): Client {
        return $this->mapper->findByUuid($uuid);
    }

    private function normalizeEmails(array $data): array {
        $emails = [];
        if (isset($data['emails']) && is_array($data['emails'])) {
            foreach ($data['emails'] as $entry) {
                if (is_string($entry) && trim($entry) !== '') {
                    $emails[] = ['label' => 'Primary', 'email' => trim($entry)];
                } elseif (is_array($entry) && !empty(trim((string)($entry['email'] ?? '')))) {
                    $emails[] = [
                        'label' => trim((string)($entry['label'] ?? 'Email')) ?: 'Email',
                        'email' => trim((string)$entry['email']),
                    ];
                }
            }
        }
        if (empty($emails) && !empty($data['contact_email'])) {
            $emails[] = ['label' => 'Primary', 'email' => trim((string)$data['contact_email'])];
        }
        if (!empty($data['invoice_email'])) {
            $inv = trim((string)$data['invoice_email']);
            $exists = false;
            foreach ($emails as $e) {
                if ($e['email'] === $inv) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $emails[] = ['label' => 'Billing', 'email' => $inv];
            }
        }
        return $emails;
    }

    private function normalizePhones(array $data): array {
        $phones = [];
        if (isset($data['phones']) && is_array($data['phones'])) {
            foreach ($data['phones'] as $entry) {
                if (is_string($entry) && trim($entry) !== '') {
                    $phones[] = ['label' => 'Primary', 'phone' => trim($entry)];
                } elseif (is_array($entry) && !empty(trim((string)($entry['phone'] ?? '')))) {
                    $phones[] = [
                        'label' => trim((string)($entry['label'] ?? 'Phone')) ?: 'Phone',
                        'phone' => trim((string)$entry['phone']),
                    ];
                }
            }
        }
        if (empty($phones) && !empty($data['phone'])) {
            $phones[] = ['label' => 'Primary', 'phone' => trim((string)$data['phone'])];
        }
        return $phones;
    }

    private function normalizeCategories(mixed $categories): array {
        if (is_string($categories)) {
            $decoded = json_decode($categories, true);
            if (is_array($decoded)) {
                $categories = $decoded;
            } else {
                $categories = array_map('trim', explode(',', $categories));
            }
        }
        if (!is_array($categories)) {
            return [];
        }
        $clean = [];
        foreach ($categories as $cat) {
            if (is_string($cat) && trim($cat) !== '') {
                $clean[] = trim($cat);
            } elseif (is_array($cat) && !empty($cat['id'])) {
                $clean[] = trim((string)$cat['id']);
            }
        }
        return array_values(array_unique($clean));
    }

    private function normalizeCustomFields(mixed $customFields): array {
        if (is_string($customFields)) {
            $decoded = json_decode($customFields, true);
            return is_array($decoded) ? $decoded : [];
        }
        if (is_array($customFields)) {
            return $customFields;
        }
        if (is_object($customFields)) {
            return (array)$customFields;
        }
        return [];
    }

    private function generateClientNumber(): string {
        return 'SP-' . strtoupper(bin2hex(random_bytes(3)));
    }

    private function parseAssigneeFromClientNumber(string $cn): ?string {
        $cn = trim($cn);
        if ($cn === '') {
            return null;
        }
        if (preg_match('/^([A-Za-zÀ-ž]{2,})[-_]\d+/u', $cn, $m)) {
            if (!in_array(strtoupper($m[1]), ['KD', 'SP', 'CL', 'CLI', 'CUST', 'PART'], true)) {
                return $m[1];
            }
        }
        // Also match pure person names without digits (e.g. "Tery Vaci", "Tery Vesi")
        if (preg_match('/^[A-Za-zÀ-ž]+(?:\s+[A-Za-zÀ-ž]+)*$/u', $cn)) {
            if (!in_array(strtoupper($cn), ['KD', 'SP', 'CL', 'CLI', 'CUST', 'PART'], true)) {
                return $cn;
            }
        }
        return null;
    }

    private function syncImportedNotesAndContacts(int $clientId, array $data): void {
        if (!empty($data['notes']) && is_array($data['notes'])) {
            $nqb = $this->db->getQueryBuilder();
            $nqb->select('title', 'content')
                ->from('sticky_client_notes')
                ->where($nqb->expr()->eq('client_id', $nqb->createNamedParameter($clientId)));
            $nres = $nqb->executeQuery();
            $existingNotes = $nres->fetchAll();
            $nres->closeCursor();

            $existingKeys = [];
            foreach ($existingNotes as $en) {
                $existingKeys[trim((string)($en['title'] ?? '')) . '|' . trim((string)($en['content'] ?? ''))] = true;
            }

            foreach ($data['notes'] as $noteItem) {
                if (is_string($noteItem)) {
                    $noteItem = ['content' => $noteItem];
                }
                if (!is_array($noteItem)) {
                    continue;
                }
                $content = trim((string)($noteItem['content'] ?? ''));
                if ($content === '') {
                    continue;
                }
                $title = trim((string)($noteItem['title'] ?? ''));
                $key = $title . '|' . $content;
                if (isset($existingKeys[$key])) {
                    continue;
                }

                $nowStr = (new DateTime())->format('Y-m-d H:i:s');
                $insNote = $this->db->getQueryBuilder();
                $insNote->insert('sticky_client_notes')
                    ->values([
                        'client_id'  => $insNote->createNamedParameter($clientId),
                        'user_id'    => $insNote->createNamedParameter(!empty($noteItem['user_id']) ? (string)$noteItem['user_id'] : 'admin'),
                        'type'       => $insNote->createNamedParameter(!empty($noteItem['type']) ? (string)$noteItem['type'] : 'note'),
                        'title'      => $insNote->createNamedParameter($title !== '' ? $title : null),
                        'content'    => $insNote->createNamedParameter($content),
                        'created_at' => $insNote->createNamedParameter(!empty($noteItem['created_at']) ? $noteItem['created_at'] : $nowStr),
                        'updated_at' => $insNote->createNamedParameter(!empty($noteItem['updated_at']) ? $noteItem['updated_at'] : $nowStr),
                    ]);
                $insNote->executeStatement();
                $existingKeys[$key] = true;
            }
        }

        if (!empty($data['card_ids']) && is_array($data['card_ids'])) {
            try {
                $cqb = $this->db->getQueryBuilder();
                $cqb->select('card_id')
                    ->from('sticky_client_contacts')
                    ->where($cqb->expr()->eq('client_id', $cqb->createNamedParameter($clientId)));
                $cres = $cqb->executeQuery();
                $existingCardIds = array_map('intval', $cres->fetchAll(\PDO::FETCH_COLUMN));
                $cres->closeCursor();

                foreach ($data['card_ids'] as $cid) {
                    $cardId = (int)$cid;
                    if ($cardId > 0 && !in_array($cardId, $existingCardIds, true)) {
                        $insContact = $this->db->getQueryBuilder();
                        $insContact->insert('sticky_client_contacts')
                            ->values([
                                'client_id'  => $insContact->createNamedParameter($clientId),
                                'card_id'    => $insContact->createNamedParameter($cardId),
                                'created_at' => $insContact->createNamedParameter((new DateTime())->format('Y-m-d H:i:s')),
                            ]);
                        $insContact->executeStatement();
                        $existingCardIds[] = $cardId;
                    }
                }
            } catch (\Throwable) {
            }
        }
    }

    public function create(array $data): Client {
        $this->db->beginTransaction();
        try {
            $client = new Client();
            $providedUuid = !empty($data['uuid']) && is_string($data['uuid']) ? trim($data['uuid']) : '';
            $client->setUuid($providedUuid !== '' ? $providedUuid : bin2hex(random_bytes(16)));

            $clientNumber = trim((string)($data['client_number'] ?? ''));
            if ($clientNumber === '') {
                $clientNumber = $this->generateClientNumber();
            }
            $client->setClientNumber($clientNumber);
            $client->setName(trim((string)($data['name'] ?? '')));
            $client->setType($data['type'] ?? 'company');
            $client->setStatus($data['status'] ?? 'in_progress');

            $assignedTo = !empty($data['assigned_to']) ? trim((string)$data['assigned_to']) : null;
            if ($assignedTo === null) {
                $assignedTo = $this->parseAssigneeFromClientNumber($clientNumber);
            }
            $client->setAssignedTo($assignedTo);
            $client->setTier(!empty($data['tier']) ? trim((string)$data['tier']) : null);

            $categories = $this->normalizeCategories($data['categories'] ?? []);
            $client->setCategories(json_encode($categories, JSON_UNESCAPED_UNICODE));

            $emails = $this->normalizeEmails($data);
            $client->setEmails(json_encode($emails, JSON_UNESCAPED_UNICODE));
            $client->setContactEmail(!empty($data['contact_email']) ? $data['contact_email'] : ($emails[0]['email'] ?? null));
            $client->setInvoiceEmail($data['invoice_email'] ?? null);

            $phones = $this->normalizePhones($data);
            $client->setPhones(json_encode($phones, JSON_UNESCAPED_UNICODE));
            $client->setPhone(!empty($data['phone']) ? $data['phone'] : ($phones[0]['phone'] ?? null));

            $customFields = $this->normalizeCustomFields($data['custom_fields'] ?? []);
            $client->setCustomFields(json_encode($customFields, JSON_UNESCAPED_UNICODE));

            $client->setWebsite($data['website'] ?? null);
            $client->setVatId($data['vat_id'] ?? null);
            $client->setTaxNumber($data['tax_number'] ?? null);
            $client->setRegisterCourt($data['register_court'] ?? null);
            $client->setRegisterNumber($data['register_number'] ?? null);
            $client->setCreatedAt(new DateTime());
            $client->setUpdatedAt(new DateTime());

            $insertedClient = $this->mapper->insert($client);

            $addresses = $data['addresses'] ?? [];
            foreach ($addresses as $addressData) {
                $this->addressService->createAddress($insertedClient->getId(), $addressData);
            }

            $this->syncImportedNotesAndContacts((int)$insertedClient->getId(), $data);

            $this->activityService->log('client', 'created', $client->getName(), $insertedClient->getId(), ['uuid' => $insertedClient->getUuid()]);

            $this->db->commit();

            return $this->mapper->findByUuid($insertedClient->getUuid());

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(string $uuid, array $data): Client {
        $this->db->beginTransaction();

        try {
            $client = $this->mapper->findByUuid($uuid, false);

            $addressesData = $data['addresses'] ?? null;
            unset($data['addresses']);

            if (isset($data['name']))            $client->setName(trim((string)$data['name']));
            if (array_key_exists('client_number', $data)) {
                $cn = trim((string)$data['client_number']);
                $client->setClientNumber($cn !== '' ? $cn : $client->getClientNumber());
            }
            if (isset($data['type']))            $client->setType($data['type']);
            if (isset($data['status']))          $client->setStatus($data['status']);
            if (array_key_exists('assigned_to', $data)) {
                $assignedVal = !empty($data['assigned_to']) ? trim((string)$data['assigned_to']) : null;
                if ($assignedVal === null && empty($client->getAssignedTo())) {
                    $assignedVal = $this->parseAssigneeFromClientNumber((string)$client->getClientNumber());
                }
                $client->setAssignedTo($assignedVal);
            }
            if (array_key_exists('tier', $data)) {
                $client->setTier(!empty($data['tier']) ? trim((string)$data['tier']) : null);
            }
            if (array_key_exists('categories', $data)) {
                $categories = $this->normalizeCategories($data['categories']);
                $client->setCategories(json_encode($categories, JSON_UNESCAPED_UNICODE));
            }
            if (array_key_exists('emails', $data) || array_key_exists('contact_email', $data)) {
                $emails = $this->normalizeEmails($data);
                $client->setEmails(json_encode($emails, JSON_UNESCAPED_UNICODE));
                $client->setContactEmail($emails[0]['email'] ?? ($data['contact_email'] ?? null));
            }
            if (array_key_exists('invoice_email', $data)) {
                $client->setInvoiceEmail($data['invoice_email']);
            }
            if (array_key_exists('phones', $data) || array_key_exists('phone', $data)) {
                $phones = $this->normalizePhones($data);
                $client->setPhones(json_encode($phones, JSON_UNESCAPED_UNICODE));
                $client->setPhone($phones[0]['phone'] ?? ($data['phone'] ?? null));
            }
            if (array_key_exists('custom_fields', $data)) {
                $customFields = $this->normalizeCustomFields($data['custom_fields']);
                $client->setCustomFields(json_encode($customFields, JSON_UNESCAPED_UNICODE));
            }

            if (array_key_exists('website', $data))         $client->setWebsite($data['website']);
            if (array_key_exists('vat_id', $data))          $client->setVatId($data['vat_id']);
            if (array_key_exists('tax_number', $data))      $client->setTaxNumber($data['tax_number']);
            if (array_key_exists('register_court', $data))  $client->setRegisterCourt($data['register_court']);
            if (array_key_exists('register_number', $data)) $client->setRegisterNumber($data['register_number']);

            $client->setUpdatedAt(new DateTime());
            $updatedClient = $this->mapper->update($client);

            if ($addressesData !== null) {
                $clientId = $updatedClient->getId();

                $currentDbAddresses = $this->addressService->getAddressesForClient($clientId);
                $dbAddressIds = array_map(function($addr) {
                    return is_array($addr) ? $addr['id'] : $addr->getId();
                }, $currentDbAddresses);

                $frontendAddressIds = [];

                foreach ($addressesData as $addressData) {
                    if (!empty($addressData['id'])) {
                        $addressId = (int)$addressData['id'];
                        $frontendAddressIds[] = $addressId;
                        $this->addressService->updateAddress($addressId, $addressData);
                    } else {
                        $this->addressService->createAddress($clientId, $addressData);
                    }
                }

                $idsToDelete = array_diff($dbAddressIds, $frontendAddressIds);
                foreach ($idsToDelete as $deleteId) {
                    $this->addressService->deleteAddressById($deleteId);
                }
            }

            $this->syncImportedNotesAndContacts((int)$updatedClient->getId(), $data);

            $this->activityService->log('client', 'updated', $client->getName(), $client->getId(), ['uuid' => $client->getUuid()]);

            $this->db->commit();

            return $this->mapper->findByUuid($uuid);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(string $uuid): void {
        $this->mapper->delete($this->mapper->findByUuid($uuid));
    }

    /**
     * Bulk import or upsert clients from CSV/JSON import payload
     */
    public function importBulk(array $records): array {
        $existingClients = $this->all();
        $byUuid = [];
        $byClientNumber = [];
        $byName = [];

        foreach ($existingClients as $c) {
            if ($c->getUuid()) {
                $byUuid[$c->getUuid()] = $c;
            }
            if ($c->getClientNumber()) {
                $byClientNumber[strtolower(trim($c->getClientNumber()))] = $c;
            }
            if ($c->getName()) {
                $byName[strtolower(trim($c->getName()))] = $c;
            }
        }

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($records as $index => $record) {
            try {
                $name = trim((string)($record['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $target = null;
                if (!empty($record['uuid']) && isset($byUuid[$record['uuid']])) {
                    $target = $byUuid[$record['uuid']];
                } elseif (!empty($record['client_number']) && isset($byClientNumber[strtolower(trim((string)$record['client_number']))])) {
                    $target = $byClientNumber[strtolower(trim((string)$record['client_number']))];
                } elseif (isset($byName[strtolower($name)])) {
                    $target = $byName[strtolower($name)];
                }

                if ($target !== null) {
                    $this->update($target->getUuid(), $record);
                    $updated++;
                } else {
                    $newClient = $this->create($record);
                    $byUuid[$newClient->getUuid()] = $newClient;
                    $byClientNumber[strtolower($newClient->getClientNumber())] = $newClient;
                    $byName[strtolower($newClient->getName())] = $newClient;
                    $created++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Row " . ($index + 1) . ": " . $e->getMessage();
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
            'clients' => $this->all(),
        ];
    }

    /**
     * Helper to extract assignees from client_number values like "Mari-002", "Jedlicka_011", or "Tery Vaci"
     */
    public function extractAssigneesFromClientNumber(bool $overwrite = false): int {
        $clients = $this->all();
        $count = 0;
        foreach ($clients as $client) {
            if (!$overwrite && !empty($client->getAssignedTo())) {
                continue;
            }
            $extracted = $this->parseAssigneeFromClientNumber((string)$client->getClientNumber());
            if ($extracted !== null) {
                $client->setAssignedTo($extracted);
                $client->setUpdatedAt(new DateTime());
                $this->mapper->update($client);
                $count++;
            }
        }
        return $count;
    }
}