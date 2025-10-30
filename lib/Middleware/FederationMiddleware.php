<?php

namespace OCA\Deck\Middleware;

use OCP\AppFramework\Middleware;
use OCP\IRequest;
use OCA\Deck\Service\PermissionService;
use Psr\Log\LoggerInterface;

class FederationMiddleware extends Middleware {
    public function __construct(
		private LoggerInterface $logger,
        private PermissionService $permissionService,
        private IRequest $request
    ) {}

    public function beforeController($controller, $methodName) {
        $accessToken = $this->request->getHeader('deck-federation-accesstoken');
        if ($accessToken) {
            $this->permissionService->setAccessToken($accessToken);
        }
    }
}
