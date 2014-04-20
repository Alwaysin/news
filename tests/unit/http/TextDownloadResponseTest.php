<?php
/**
 * ownCloud - News
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Alessandro Cosentino <cosenal@gmail.com>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @copyright Alessandro Cosentino 2012
 * @copyright Bernhard Posselt 2012, 2014
 */


namespace OCA\News\Http;


require_once(__DIR__ . "/../../classloader.php");
require_once(__DIR__ . "/DownloadResponseTest.php");


class TextDownloadResponseTest extends DownloadResponseTest {


	protected function setUp() {
		$this->response = new TextDownloadResponse('sometext', 'file', 'content');
	}


	public function testRender() {
		$this->assertEquals('sometext', $this->response->render());
	}

}