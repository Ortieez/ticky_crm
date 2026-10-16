<?php

namespace OCA\StickyCRM\Service;

use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IL10N;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\AddressBook;
use Psr\Log\LoggerInterface;

class AccessService {
    private IConfig $config;
    private IGroupManager $groupManager;
    private IUserManager $userManager;
    private CardDavBackend $cardDavBackend;
    private IL10N $l10n;
    private LoggerInterface $logger;

    private string $appName = 'sticky_crm';
    private string $principalUri = 'principals/app/sticky';
    private string $addressBookUri = 'stickycrm-contacts';

    public function getAddressBookUri(): string {
        return $this->addressBookUri;
    }

    public function getOwnerSlug(): string {
        $lastSlash = strrpos($this->principalUri, '/');
        return $lastSlash === false ? $this->principalUri : substr($this->principalUri, $lastSlash + 1);
    }

    public function __construct(
        IConfig $config,
        IGroupManager $groupManager,
        IUserManager $userManager,
        CardDavBackend $cardDavBackend,
        IL10N $l10n,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->groupManager = $groupManager;
        $this->userManager = $userManager;
        $this->cardDavBackend = $cardDavBackend;
        $this->l10n = $l10n;
        $this->logger = $logger;
    }

    public function canAccess(?string $userId): bool {
        if ($userId === null) {
            return false;
        }

        if ($this->groupManager->isAdmin($userId)) {
            return true;
        }

        $allowedUsers = json_decode($this->config->getAppValue($this->appName, 'allowed_users', '[]'), true);
        if (in_array($userId, $allowedUsers, true)) {
            return true;
        }

        $allowedGroups = json_decode($this->config->getAppValue($this->appName, 'allowed_groups', '[]'), true);
        foreach ($allowedGroups as $groupId) {
            if ($this->groupManager->isInGroup($userId, $groupId)) {
                return true;
            }
        }

        // If neither groups nor users are restricted, allow access
        if (empty($allowedUsers) && empty($allowedGroups)) {
            return true;
        }

        return false;
    }

    public function getDefaultCrmConfig(): array {
        return [
            'row_coloring_enabled' => true,
            'tiers' => [
                ['id' => 'general', 'label' => 'General', 'color' => '#8b5cf6'],
                ['id' => 'gold',    'label' => 'Gold',    'color' => '#eab308'],
                ['id' => 'silver',  'label' => 'Silver',  'color' => '#64748b'],
                ['id' => 'bronze',  'label' => 'Bronze',  'color' => '#d97706'],
                ['id' => 'starter', 'label' => 'Starter', 'color' => '#3b82f6'],
            ],
            'statuses' => [
                ['id' => 'lead',        'label' => 'Lead / To Contact',       'color' => '#6b7280'],
                ['id' => 'in_progress', 'label' => 'In Progress (V řešení)',  'color' => '#3b82f6'],
                ['id' => 'confirmed',   'label' => 'Confirmed (Podpoří)',     'color' => '#10b981'],
                ['id' => 'rejected',    'label' => 'Rejected (Nepodpoří)',    'color' => '#ef4444'],
                ['id' => 'active',      'label' => 'Active',                  'color' => '#059669'],
                ['id' => 'inactive',    'label' => 'Inactive',                'color' => '#9ca3af'],
            ],
            'categories' => [
                ['id' => 'obrabeni',  'label' => 'Obrábění',  'color' => '#0ea5e9'],
                ['id' => 'penize',    'label' => 'Peníze',    'color' => '#10b981'],
                ['id' => 'slevy',     'label' => 'Slevy',     'color' => '#f59e0b'],
                ['id' => 'material',  'label' => 'Materiál',  'color' => '#8b5cf6'],
                ['id' => 'software',  'label' => 'Software',  'color' => '#6366f1'],
                ['id' => 'logistika', 'label' => 'Logistika', 'color' => '#ec4899'],
            ],
            'columns' => [
                ['field' => 'name',          'label' => 'Name',          'visible' => true,  'isCustom' => false, 'type' => 'text'],
                ['field' => 'assigned_to',   'label' => 'Assignee',      'visible' => true,  'isCustom' => false, 'type' => 'user'],
                ['field' => 'status',        'label' => 'Status',        'visible' => true,  'isCustom' => false, 'type' => 'status'],
                ['field' => 'tier',          'label' => 'Tier',          'visible' => true,  'isCustom' => false, 'type' => 'tier'],
                ['field' => 'categories',    'label' => 'Categories',    'visible' => true,  'isCustom' => false, 'type' => 'categories'],
                ['field' => 'emails',        'label' => 'Email',         'visible' => true,  'isCustom' => false, 'type' => 'emails'],
                ['field' => 'phones',        'label' => 'Phone',         'visible' => true,  'isCustom' => false, 'type' => 'phones'],
                ['field' => 'client_number', 'label' => 'Client ID',     'visible' => true,  'isCustom' => false, 'type' => 'text'],
                ['field' => 'website',       'label' => 'Website',       'visible' => false, 'isCustom' => false, 'type' => 'url'],
                ['field' => 'vat_id',        'label' => 'VAT ID',        'visible' => false, 'isCustom' => false, 'type' => 'text'],
            ],
            'custom_columns' => [],
        ];
    }

    public function getCrmConfig(): array {
        $defaults = $this->getDefaultCrmConfig();
        $raw = $this->config->getAppValue($this->appName, 'crm_config', '');
        if ($raw === '') {
            return $defaults;
        }
        $saved = json_decode($raw, true);
        if (!is_array($saved)) {
            return $defaults;
        }

        return [
            'row_coloring_enabled' => isset($saved['row_coloring_enabled']) ? (bool)$saved['row_coloring_enabled'] : $defaults['row_coloring_enabled'],
            'tiers'          => isset($saved['tiers']) && is_array($saved['tiers']) ? $saved['tiers'] : $defaults['tiers'],
            'statuses'       => isset($saved['statuses']) && is_array($saved['statuses']) ? $saved['statuses'] : $defaults['statuses'],
            'categories'     => isset($saved['categories']) && is_array($saved['categories']) ? $saved['categories'] : $defaults['categories'],
            'columns'        => isset($saved['columns']) && is_array($saved['columns']) ? $saved['columns'] : $defaults['columns'],
            'custom_columns' => isset($saved['custom_columns']) && is_array($saved['custom_columns']) ? $saved['custom_columns'] : $defaults['custom_columns'],
        ];
    }

    public function saveCrmConfig(array $crmConfig): array {
        $current = $this->getCrmConfig();
        $merged = [
            'row_coloring_enabled' => array_key_exists('row_coloring_enabled', $crmConfig)
                ? (bool)$crmConfig['row_coloring_enabled']
                : $current['row_coloring_enabled'],
            'tiers'          => isset($crmConfig['tiers']) && is_array($crmConfig['tiers']) ? array_values($crmConfig['tiers']) : $current['tiers'],
            'statuses'       => isset($crmConfig['statuses']) && is_array($crmConfig['statuses']) ? array_values($crmConfig['statuses']) : $current['statuses'],
            'categories'     => isset($crmConfig['categories']) && is_array($crmConfig['categories']) ? array_values($crmConfig['categories']) : $current['categories'],
            'columns'        => isset($crmConfig['columns']) && is_array($crmConfig['columns']) ? array_values($crmConfig['columns']) : $current['columns'],
            'custom_columns' => isset($crmConfig['custom_columns']) && is_array($crmConfig['custom_columns']) ? array_values($crmConfig['custom_columns']) : $current['custom_columns'],
        ];

        $this->config->setAppValue($this->appName, 'crm_config', json_encode($merged, JSON_UNESCAPED_UNICODE));
        return $merged;
    }

    public function getAllowedSettings(): array {
        return [
            'groups' => json_decode($this->config->getAppValue($this->appName, 'allowed_groups', '[]'), true),
            'users' => json_decode($this->config->getAppValue($this->appName, 'allowed_users', '[]'), true),
        ];
    }

    public function saveSettings(array $groups, array $users): void {
        $this->config->setAppValue($this->appName, 'allowed_groups', json_encode($groups));
        $this->config->setAppValue($this->appName, 'allowed_users', json_encode($users));

        $this->syncAddressBookShares($groups, $users);
    }

    public function syncAddressBookSharesFromSettings(): void {
        $settings = $this->getAllowedSettings();
        $this->syncAddressBookShares($settings['groups'], $settings['users']);
    }

    private function syncAddressBookShares(array $groups, array $users): void {
        $addressBookProperties = $this->ensureSystemAddressBookProperties();
        if (!$addressBookProperties) {
            $this->logger->warning('Sticky CRM: Adressbuch-Freigaben übersprungen.', ['app' => 'sticky_crm']);
            return;
        }

        $allAllowedUserIds = [];
        foreach ($users as $userId) {
            if ($this->userManager->userExists($userId)) {
                $allAllowedUserIds[] = $userId;
            }
        }
        foreach ($groups as $groupId) {
            $group = $this->groupManager->get($groupId);
            if ($group !== null) {
                foreach ($group->getUsers() as $user) {
                    $allAllowedUserIds[] = $user->getUID();
                }
            }
        }
        $adminGroup = $this->groupManager->get('admin');
        if ($adminGroup !== null) {
            foreach ($adminGroup->getUsers() as $admin) {
                $allAllowedUserIds[] = $admin->getUID();
            }
        }
        $allAllowedUserIds = array_unique($allAllowedUserIds);

        $desiredHrefs = [];
        foreach ($allAllowedUserIds as $userId) {
            $desiredHrefs['principal:principals/users/' . $userId] = $userId;
        }

        $currentShares = $this->cardDavBackend->getShares($addressBookProperties['id']);
        $currentHrefs = [];
        foreach ($currentShares as $share) {
            $href = is_array($share) ? $share['href'] : $share->href;
            $currentHrefs[$href] = true;
        }

        $sharesToRemove = array_values(array_diff(array_keys($currentHrefs), array_keys($desiredHrefs)));

        $sharesToAdd = [];
        foreach ($desiredHrefs as $href => $userId) {
            $sharesToAdd[] = [
                'href' => $href,
                'commonName' => $userId,
                'readOnly' => false,
            ];
        }

        try {
            if (!isset($addressBookProperties['principaluri'])) {
                $addressBookProperties['principaluri'] = $this->principalUri;
            }

            $addressBook = new AddressBook($this->cardDavBackend, $addressBookProperties, $this->l10n);
            $addressBook->updateShares($sharesToAdd, $sharesToRemove);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Sticky CRM: Adressbuch-Freigaben konnten nicht synchronisiert werden: ' . $e->getMessage(),
                ['app' => 'sticky_crm', 'exception' => $e]
            );
        }
    }

    public function ensureSystemAddressBookProperties(): ?array {
        try {
            $existingBooks = $this->cardDavBackend->getAddressBooksForUser($this->principalUri);

            foreach ($existingBooks as $book) {
                if ($book['uri'] === $this->addressBookUri) {
                    return $book;
                }
            }

            $this->cardDavBackend->createAddressBook(
                $this->principalUri,
                $this->addressBookUri,
                [
                    '{DAV:}displayname' => 'Sticky CRM Kontakte',
                ]
            );

            $existingBooks = $this->cardDavBackend->getAddressBooksForUser($this->principalUri);
            foreach ($existingBooks as $book) {
                if ($book['uri'] === $this->addressBookUri) {
                    return $book;
                }
            }

            $this->logger->error(
                'Sticky CRM: Adressbuch wurde erstellt, konnte danach aber nicht wiedergefunden werden.',
                ['app' => 'sticky_crm', 'principalUri' => $this->principalUri, 'uri' => $this->addressBookUri]
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Sticky CRM Adressbuch konnte nicht erstellt werden: ' . $e->getMessage(),
                ['app' => 'sticky_crm', 'exception' => $e]
            );
        }

        return null;
    }
}