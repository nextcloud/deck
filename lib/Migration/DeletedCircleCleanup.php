<?php

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Deck\Migration;

use OCA\Deck\Db\Acl;
use OCA\Deck\Db\AclMapper;
use OCA\Deck\Service\CirclesService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class DeletedCircleCleanup implements IRepairStep {
	private AclMapper $aclMapper;
	private CirclesService $circleService;

	public function __construct(AclMapper $aclMapper, CirclesService $circlesService) {
		$this->aclMapper = $aclMapper;
		$this->circleService = $circlesService;
	}

	public function getName() {
		return 'Cleanup Deck ACL entries for circles which have been already deleted';
	}

	public function run(IOutput $output) {
		if (!$this->circleService->isCirclesEnabled()) {
			return;
		}

		foreach ($this->aclMapper->findByType(Acl::PERMISSION_TYPE_CIRCLE) as $acl) {
			if ($this->circleService->getCircle($acl->getParticipant()) === null) {
				$this->aclMapper->delete($acl);
				$output->info('Removed circle with id ' . $acl->getParticipant());
			}
		}
	}
}
