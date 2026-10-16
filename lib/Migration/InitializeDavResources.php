<?php

namespace OCA\StickyCRM\Migration;

use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use OCA\StickyCRM\Service\AccessService;

class InitializeDavResources implements IRepairStep {
    private AccessService $accessService;

    public function __construct(AccessService $accessService) {
        $this->accessService = $accessService;
    }

    public function getName(): string {
        return 'Initialisiere Sticky CRM DAV Ressourcen';
    }

    public function run(IOutput $output): void {
        $output->info('-> [Sticky CRM CLI] Starte Ressourcen-Erstellung...');

        try {
            $output->info('-> [Sticky CRM CLI] Erstelle Adressbuch...');
            $addressBook = $this->accessService->ensureSystemAddressBookProperties();

            if ($addressBook === null) {
                $output->warning('-> [Sticky CRM CLI] Adressbuch konnte nicht erstellt/geladen werden, Freigaben werden übersprungen.');
                return;
            }

            $output->info('-> [Sticky CRM CLI] Synchronisiere Freigaben...');
            $this->accessService->syncAddressBookSharesFromSettings();
            $output->info('-> [Sticky CRM CLI] Fertig.');
        } catch (\Throwable $e) {
            $output->warning('-> [Sticky CRM CLI] Fehler: ' . get_class($e) . ': ' . $e->getMessage());
            $output->warning($e->getTraceAsString());
        }
    }
}