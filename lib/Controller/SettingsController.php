<?php
namespace OCA\StickyCRM\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;
use OCA\StickyCRM\Service\AccessService;
use OCA\DAV\CardDAV\CardDavBackend;
use OCP\App\IAppManager;
use OCP\Server;

class SettingsController extends Controller {

    public function __construct(
        string $appName,
        IRequest $request,
        private AccessService $accessService,
        private IGroupManager $groupManager,
        private IUserManager $userManager,
        private IAppManager $appManager,
        private IUserSession $userSession,
        private IDBConnection $db,
    ) {
        parent::__construct($appName, $request);
    }

    private function readLegacyTickyDatabase(): array {
        try {
            $qb = $this->db->getQueryBuilder();
            $qb->select('*')->from('ticky_clients')->orderBy('id', 'ASC');
            $res = $qb->executeQuery();
            $clients = $res->fetchAll();
            $res->closeCursor();

            $addressesByClient = [];
            try {
                $aqb = $this->db->getQueryBuilder();
                $aqb->select('*')->from('ticky_client_addresses')->orderBy('id', 'ASC');
                $ares = $aqb->executeQuery();
                while ($arow = $ares->fetch()) {
                    $addressesByClient[(int)$arow['client_id']][] = $arow;
                }
                $ares->closeCursor();
            } catch (\Throwable) {
            }

            $notesByClient = [];
            try {
                $nqb = $this->db->getQueryBuilder();
                $nqb->select('*')->from('ticky_client_notes')->orderBy('id', 'ASC');
                $nres = $nqb->executeQuery();
                while ($nrow = $nres->fetch()) {
                    $notesByClient[(int)$nrow['client_id']][] = [
                        'id'         => (int)$nrow['id'],
                        'client_id'  => (int)$nrow['client_id'],
                        'user_id'    => (string)($nrow['user_id'] ?? ''),
                        'type'       => (string)($nrow['type'] ?? 'note'),
                        'title'      => (string)($nrow['title'] ?? ''),
                        'content'    => (string)($nrow['content'] ?? ''),
                        'created_at' => (string)($nrow['created_at'] ?? ''),
                        'updated_at' => (string)($nrow['updated_at'] ?? ''),
                    ];
                }
                $nres->closeCursor();
            } catch (\Throwable) {
            }

            $contactsByClient = [];
            try {
                $cqb = $this->db->getQueryBuilder();
                $cqb->select('*')->from('ticky_client_contacts')->orderBy('id', 'ASC');
                $cres = $cqb->executeQuery();
                while ($crow = $cres->fetch()) {
                    $contactsByClient[(int)$crow['client_id']][] = (int)$crow['card_id'];
                }
                $cres->closeCursor();
            } catch (\Throwable) {
            }

            foreach ($clients as &$c) {
                $cid = (int)($c['id'] ?? 0);
                $c['addresses'] = $addressesByClient[$cid] ?? [];
                $c['notes']     = $notesByClient[$cid] ?? [];
                $c['card_ids']  = $contactsByClient[$cid] ?? [];
            }
            unset($c);

            return $clients;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function getSettings(): JSONResponse {
        $settings = $this->accessService->getAllowedSettings();
        $crmConfig = $this->accessService->getCrmConfig();

        $groups = array_map(
            fn($group) => ['id' => $group->getGID(), 'label' => $group->getDisplayName()],
            $this->groupManager->search('')
        );

        $users = [];
        foreach ($this->userManager->search('') as $user) {
            $users[] = ['id' => $user->getUID(), 'label' => $user->getDisplayName()];
        }

        $currentUser = $this->userSession->getUser();

        return new JSONResponse([
            'allowed_groups'       => $settings['groups'],
            'allowed_users'        => $settings['users'],
            'all_groups'           => array_values($groups),
            'all_users'            => array_values($users),
            'crm_config'           => $crmConfig,
            'legacy_ticky_clients' => $this->readLegacyTickyDatabase(),
            'current_user'         => $currentUser ? [
                'id'    => $currentUser->getUID(),
                'label' => $currentUser->getDisplayName(),
            ] : null,
        ]);
    }

    /**
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function saveSettings(): JSONResponse {
        $groups = $this->request->getParam('groups', null);
        $users = $this->request->getParam('users', null);
        $crmConfig = $this->request->getParam('crm_config', null);

        if (is_array($groups) && is_array($users)) {
            $this->accessService->saveSettings($groups, $users);
        }

        $savedConfig = null;
        if (is_array($crmConfig)) {
            $savedConfig = $this->accessService->saveCrmConfig($crmConfig);
        } else {
            $savedConfig = $this->accessService->getCrmConfig();
        }

        return new JSONResponse([
            'success' => true,
            'crm_config' => $savedConfig,
        ]);
    }

    /**
     * Erstellt ein zentrales Adressbuch für die App
     * @NoAdminRequired
     */
    #[NoAdminRequired]
    public function createAddressBook(): JSONResponse {
        try {
            if (!$this->appManager->isEnabledForAnyone('contacts')) {
                return new JSONResponse([
                    'success' => false,
                    'error' => 'Contacts app is not installed'
                ], 400);
            }

            $principalUri = 'principals/app/sticky';

            /** @var CardDavBackend $cardDavBackend */
            $cardDavBackend = Server::get(CardDavBackend::class);

            $existing = $cardDavBackend->getAddressBooksForUser($principalUri);
            foreach ($existing as $ab) {
                if ($ab['{DAV:}displayname'] === 'Sticky CRM Kontakte') {
                    return new JSONResponse([
                        'success' => false,
                        'error' => 'System address book already exists',
                        'addressbook_id' => $ab['id'],
                        'uri' => $ab['uri']
                    ], 409);
                }
            }

            $addressBookId = $cardDavBackend->createAddressBook(
                $principalUri,
                'sticky-crm-kontakte',
                [
                    '{DAV:}displayname' => 'Sticky CRM Kontakte',
                ]
            );

            return new JSONResponse([
                'success' => true,
                'addressbook_id' => $addressBookId,
                'uri' => 'sticky-crm-kontakte',
                'principaluri' => $principalUri
            ]);
        } catch (\Exception $e) {
            return new JSONResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}