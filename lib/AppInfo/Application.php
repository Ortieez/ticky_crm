<?php
namespace OCA\StickyCRM\AppInfo;

use OCP\AppFramework\App;
use OCP\INavigationManager;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\DAV\Events\CardDeletedEvent;

use OCA\StickyCRM\Listener\CardDeletedListener;
use OCA\StickyCRM\Listener\NavigationListener;
use OCA\StickyCRM\Middleware\AccessMiddleware;
use OCA\StickyCRM\Notification\Notifier;

class Application extends App implements IBootstrap {
    public const APP_ID = 'sticky_crm';

    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerMiddleware(AccessMiddleware::class);
        $context->registerNotifierService(Notifier::class);
    }

    public function boot(IBootContext $context): void {
        $context->injectFn(function (
            INavigationManager $navigationManager,
            NavigationListener $listener
        ) {
            if (!$listener->hasAccess()) {
                return;
            }

            $navigationManager->add(function () use ($listener) {
                return $listener->getNavigationEntry();
            });
        });
    }
}