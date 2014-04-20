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

namespace OCA\News\App;

use \OC\Files\View;
use \OCP\AppFramework\App;

use \OCA\News\Core\Logger;
use \OCA\News\Core\Db;
use \OCA\News\Core\Settings;

use \OCA\News\Controller\PageController;
use \OCA\News\Controller\FolderController;
use \OCA\News\Controller\FeedController;
use \OCA\News\Controller\ItemController;
use \OCA\News\Controller\ExportController;
use \OCA\News\Controller\ApiController;
use \OCA\News\Controller\FolderApiController;
use \OCA\News\Controller\FeedApiController;
use \OCA\News\Controller\ItemApiController;

use \OCA\News\BusinessLayer\FolderBusinessLayer;
use \OCA\News\BusinessLayer\FeedBusinessLayer;
use \OCA\News\BusinessLayer\ItemBusinessLayer;

use \OCA\News\Db\FolderMapper;
use \OCA\News\Db\FeedMapper;
use \OCA\News\Db\ItemMapper;
use \OCA\News\Db\StatusFlag;
use \OCA\News\Db\MapperFactory;

use \OCA\News\Utility\Config;
use \OCA\News\Utility\OPMLExporter;
use \OCA\News\Utility\Updater;
use \OCA\News\Utility\SimplePieAPIFactory;
use \OCA\News\Utility\TimeFactory;
use \OCA\News\Utility\FaviconFetcher;

use \OCA\News\Fetcher\Fetcher;
use \OCA\News\Fetcher\FeedFetcher;

use \OCA\News\ArticleEnhancer\Enhancer;
use \OCA\News\ArticleEnhancer\XPathArticleEnhancer;
use \OCA\News\ArticleEnhancer\RegexArticleEnhancer;

use \OCA\News\Middleware\CORSMiddleware;


require_once __DIR__ . '/../3rdparty/htmlpurifier/library/HTMLPurifier.auto.php';

// to prevent clashes with installed app framework versions
if(!class_exists('\SimplePie')) {
	require_once __DIR__ . '/../3rdparty/simplepie/autoloader.php';
}


class News extends App {

	public function __construct(array $urlParams=array()){
		parent::__construct('news', $urlParams);

		$container = $this->getContainer();


		/**
		 * Controllers
		 */
		$container->registerService('PageController', function($c) {
			return new PageController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('Settings'),
				$c->query('L10N')
			);
		});

		$container->registerService('FolderController', function($c) {
			return new FolderController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('FolderBusinessLayer'),
				$c->query('FeedBusinessLayer'),
				$c->query('ItemBusinessLayer'),
				$c->query('UserId')
			);
		});

		$container->registerService('FeedController', function($c) {
			return new FeedController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('FolderBusinessLayer'),
				$c->query('FeedBusinessLayer'),
				$c->query('ItemBusinessLayer'),
				$c->query('UserId'),
				$c->query('Settings')
			);
		});

		$container->registerService('ItemController', function($c) {
			return new ItemController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('FeedBusinessLayer'),
				$c->query('ItemBusinessLayer'),
				$c->query('UserId'),
				$c->query('Settings')
			);
		});

		$container->registerService('ExportController', function($c) {
			return new ExportController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('FeedBusinessLayer'),
				$c->query('FolderBusinessLayer'),
				$c->query('ItemBusinessLayer'),
				$c->query('OPMLExporter'),
				$c->query('UserId')
			);
		});

		$container->registerService('ApiController', function($c) {
			return new ApiController(
				$c->query('AppName'), 
				$c->query('Request'), 
				$c->query('Updater'),
				$c->query('Settings')
			);
		});

		$container->registerService('FolderApiController', function($c) {
			return new FolderApiController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('FolderBusinessLayer'),
				$c->query('ItemBusinessLayer'),
				$c->query('UserId')
			);
		});

		$container->registerService('FeedApiController', function($c) {
			return new FeedApiController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('FolderBusinessLayer'),
				$c->query('FeedBusinessLayer'),
				$c->query('ItemBusinessLayer'),
				$c->query('Logger'),
				$c->query('UserId')
			);
		});

		$container->registerService('ItemApiController', function($c) {
			return new ItemApiController(
				$c->query('AppName'), 
				$c->query('Request'),
				$c->query('ItemBusinessLayer'),
				$c->query('UserId')
			);
		});


		/**
		 * Business Layer
		 */
		$container->registerService('FolderBusinessLayer', function($c) {
			return new FolderBusinessLayer(
				$c->query('FolderMapper'),
				$c->query('L10N'),
				$c->query('TimeFactory'),
				$c->query('Config')
			);
		});

		$container->registerService('FeedBusinessLayer', function($c) {
			return new FeedBusinessLayer(
				$c->query('FeedMapper'),
				$c->query('Fetcher'),
				$c->query('ItemMapper'),
				$c->query('Logger'),
				$c->query('L10N'),
				$c->query('TimeFactory'),
				$c->query('Config'),
				$c->query('Enhancer'),
				$c->query('HTMLPurifier')
			);
		});

		$container->registerService('ItemBusinessLayer', function($c) {
			return new ItemBusinessLayer(
				$c->query('ItemMapper'),
				$c->query('StatusFlag'),
				$c->query('TimeFactory'),
				$c->query('Config')
			);
		});


		/**
		 * Mappers
		 */
		$container->registerService('MapperFactory', function($c) {
			return new MapperFactory(
				$c->query('Settings'), $c->query('Db')
			);
		});

		$container->registerService('FolderMapper', function($c) {
			return new FolderMapper(
				$c->query('Db')
			);
		});

		$container->registerService('FeedMapper', function($c) {
			return new FeedMapper(
				$c->query('Db')
			);
		});

		$container->registerService('ItemMapper', function($c) {
			return $c->query('MapperFactory')->getItemMapper(
				$c->query('Db')
			);
		});

		/**
		 * Core
		 */		
		$container->registerService('L10N', function($c) {
			return \OC_L10N::get($c['AppName']);
		});

		$container->registerService('UserId', function($c) {
			return \OCP\User::getUser();
		});

		$container->registerService('Logger', function($c) {
			return new Logger($c['AppName']);
		});

		$container->registerService('Db', function($c) {
			return new Db();
		});

		$container->registerService('Settings', function($c) {
			return new Settings($c['AppName'], $c['UserId']);
		});


		/**
		 * Utility
		 */
		$container->registerService('ConfigView', function($c) {
			$view = new View('/news/config');
			if (!$view->file_exists('')) {
				$view->mkdir('');
			}

			return $view;
		});

		$container->registerService('Config', function($c) {
			$config = new Config($c->query('ConfigView'), $c->query('Logger'));
			$config->read('config.ini', true);
			return $config;
		});

		$container->registerService('simplePieCacheDirectory', function($c) {
			$directory = $c->query('Settings')->getSystemValue('datadirectory') .
				'/news/cache/simplepie';

			if(!is_dir($directory)) {
				mkdir($directory, 0770, true);
			}
			return $directory;
		});

		$container->registerService('HTMLPurifier', function($c) {
			$directory = $c->query('Settings')->getSystemValue('datadirectory') .
				'/news/cache/purifier';

			if(!is_dir($directory)) {
				mkdir($directory, 0770, true);
			}

			$config = \HTMLPurifier_Config::createDefault();
			$config->set('HTML.ForbiddenAttributes', 'class');
			$config->set('Cache.SerializerPath', $directory);
			$config->set('HTML.SafeIframe', true);
			$config->set('URI.SafeIframeRegexp',
				'%^(?:https?:)?//(' . 
				'www.youtube(?:-nocookie)?.com/embed/|' .
				'player.vimeo.com/video/)%'); //allow YouTube and Vimeo
			return new \HTMLPurifier($config);
		});

		$container->registerService('Enhancer', function($c) {
			$enhancer = new Enhancer();

			// register simple enhancers from config json file
			$xpathEnhancerConfig = file_get_contents(
				__DIR__ . '/../articleenhancer/xpathenhancers.json'
			);
			
			foreach(json_decode($xpathEnhancerConfig, true) as $feed => $config) {
				$articleEnhancer = new XPathArticleEnhancer(
					$c->query('SimplePieAPIFactory'),
					$config,
					$c->query('Config')
				);
				$enhancer->registerEnhancer($feed, $articleEnhancer);
			}

			$regexEnhancerConfig = file_get_contents(
				__DIR__ . '/../articleenhancer/regexenhancers.json'
			);
			foreach(json_decode($regexEnhancerConfig, true) as $feed => $config) {
				foreach ($config as $matchArticleUrl => $regex) {
					$articleEnhancer = new RegexArticleEnhancer($matchArticleUrl, $regex);
					$enhancer->registerEnhancer($feed, $articleEnhancer);
				}
			}

			return $enhancer;
		});

		/**
		 * Fetchers
		 */
		$container->registerService('Fetcher', function($c) {
			$fetcher = new Fetcher();

			// register fetchers in order
			// the most generic fetcher should be the last one
			$fetcher->registerFetcher($c->query('FeedFetcher'));

			return $fetcher;
		});

		$container->registerService('FeedFetcher', function($c) {
			return new FeedFetcher($c->query('SimplePieAPIFactory'),
				$c->query('FaviconFetcher'),
				$c->query('TimeFactory'),
				$c->query('simplePieCacheDirectory'),
				$c->query('Config')
			);
		});

		$container->registerService('StatusFlag', function($c) {
			return new StatusFlag();
		});

		$container->registerService('OPMLExporter', function($c) {
			return new OPMLExporter();
		});

		$container->registerService('Updater', function($c) {
			return new Updater(
				$c->query('FolderBusinessLayer'),
				$c->query('FeedBusinessLayer'),
				$c->query('ItemBusinessLayer')
			);
		});

		$container->registerService('SimplePieAPIFactory', function($c) {
			return new SimplePieAPIFactory();
		});

		$container->registerService('FaviconFetcher', function($c) {
			return new FaviconFetcher(
				$c->query('SimplePieAPIFactory'),
				$c->query('Config')
			);
		});

		/** 
		 * Middleware
		 */
		$container->registerService('CORSMiddleware', function($c) {
			return new CORSMiddleware(
				$c->query('Request')
			);
		});		

		$container->registerMiddleWare('CORSMiddleware');

	}
}
