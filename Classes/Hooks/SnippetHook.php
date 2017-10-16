<?php
namespace Josta\JstAssets\Hooks;

use Josta\JstAssets\Service\SnippetService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Cache\CacheManager;

class SnippetHook implements \TYPO3\CMS\Frontend\ContentObject\ContentObjectPostInitHookInterface {

	/** 
	 * Hook: tslib/class.tslib_content.php >> postInit (Called once for every rendered content object)
	 *
	 * Extracts SASS/CoffeScript snippets from all rendered tt_content elements.
	 * @return void
	 */
	public function postProcessContentObjectInitialization(\TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer &$pobj) {
		if ($pobj->getCurrentTable() == 'tt_content')
			SnippetService::getInstance()->collectContentSnippets($pobj->data);
	}
	
	/** 
	 * Hook: t3lib/class.t3lib_pagerenderer.php >> render-postProcess (Called once after all page content has been rendered)
	 *
	 * Compiles and includes all collected SASS/CoffeeScript snippets
	 * @return void
	 */
	public function postProcessPageRendering($content, $conf) {
		SnippetService::getInstance()->compileAndIncludeSnippets();
	}
	
	/**
	 * Hook: t3lib/class.t3lib_tcemain.php >> processDatamapClass (Called whenever a pages or content record is changed)
	 *
	 * Clears the complete asset cache. (This hook could be optimized to only clear
	 * the cache of this page and ancestor pages up to pagetree_inclusion_depth)
	 * @return void
	 */
    public function processDatamap_afterDatabaseOperations(&$status, &$table, &$id, &$fieldArray, DataHandler $parentObj) {
        if ($table === 'tt_content' || $table === 'pages') {
            $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('jst_assets');
			$cache->flush();    
        }
    }
	
}
