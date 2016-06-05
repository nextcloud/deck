<?php
/**
 * ownCloud - deck
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @copyright Julius Härtl 2016
 */

use OCP\AppFramework\App;
use Test\TestCase;


/**
 * This test shows how to make a small Integration Test. Query your class
 * directly from the container, only pass in mocks if needed and run your tests
 * against the database
 */
class AppTest extends TestCase {

    private $container;

    public function setUp() {
        parent::setUp();
        $app = new App('deck');
        $this->container = $app->getContainer();
    }

    public function testAppInstalled() {
        $appManager = $this->container->query('OCP\App\IAppManager');
        $this->assertTrue($appManager->isInstalled('deck'));
    }

}