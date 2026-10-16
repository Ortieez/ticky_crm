<?php
namespace OCA\StickyCRM\Listener;

use OCP\IURLGenerator;
use OCP\IUserSession;
use OCA\StickyCRM\Service\AccessService;

class NavigationListener {

    public function __construct(
        private IUserSession $userSession,
        private AccessService $accessService,
        private IURLGenerator $urlGenerator
    ) {}

    public function hasAccess(): bool {
        $user = $this->userSession->getUser();
        return $this->accessService->canAccess($user?->getUID());
    }

    public function getNavigationEntry(): array {
        try {
            $icon = $this->urlGenerator->imagePath('sticky_crm', 'sticky.svg');
        } catch (\RuntimeException $e) {
            $icon = $this->urlGenerator->imagePath('core', 'places/default-app-icon.svg');
        }

        return [
            'id'    => 'sticky_crm',
            'order' => 10,
            'href'  => $this->urlGenerator->linkToRoute('sticky_crm.page.index'),
            'icon'  => $icon,
            'name'  => 'Sticky CRM',
            'app'   => 'sticky_crm', // Pflichtfeld damit isAlwaysEnabled() nicht null bekommt
        ];
    }
}