<?php
namespace OCA\StickyCRM\Middleware;

use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http;
use OCP\IUserSession;
use OCA\StickyCRM\Service\AccessService;

class AccessMiddleware extends Middleware {

    public function __construct(
        private IUserSession $userSession,
        private AccessService $accessService
    ) {}

    public function beforeController($controller, $methodName): void {
        $user = $this->userSession->getUser();
        $uid  = $user?->getUID();

        if (!$this->accessService->canAccess($uid)) {
            throw new \Exception('Forbidden', Http::STATUS_FORBIDDEN);
        }
    }

    public function afterException($controller, $methodName, \Exception $exception): JSONResponse {
        if ((int)$exception->getCode() === Http::STATUS_FORBIDDEN) {
            return new JSONResponse([
                'error'   => 'Forbidden',
                'message' => 'Du hast keine Berechtigung für Sticky CRM.',
            ], Http::STATUS_FORBIDDEN);
        }

        throw $exception;
    }
}