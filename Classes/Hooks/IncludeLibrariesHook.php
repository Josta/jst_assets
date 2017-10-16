<?php

namespace Josta\JstAssets\Hooks;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\TypoScript\TemplateService;

class IncludeLibrariesHook {

    /**
     * Includes jQuery Anywhere hack
     *
     * @param array $params
     * @param TemplateService $pObj
     * @return void
     */
    public function includeJQueryAnywhereHack(array &$params, TemplateService $pObj) {
        if (true === isset($params['row']['root']) && true === (boolean) $params['row']['root']) {
			$pObj->config[] = '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:jst_assets/Configuration/TypoScript/JQueryAnywhere/setup.txt">';
			//$params['row']['include_static_file'] .= ',EXT:jst_assets/Configuration/TypoScript/JQueryAnywhere';
        }
    }
	
	/**
     * Includes jQuery at the end of TS
     *
     * @param array $params
     * @param TemplateService $pObj
     * @return void
     */
    public function includeJQuery(array &$params, TemplateService $pObj) {
        if (true === isset($params['row']['root']) && true === (boolean) $params['row']['root']) {
			// since static templates are called recursively in the rootline and the
			// 'includeStaticTypoScriptSourcesAtEnd' hook is executed after the recursion,
			// we are effectively at the end of TS generation
			$conf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['jst_assets']);
			$type = ($conf['jquery_placement'] == 'header') ? 'Libs' : 'Footerlibs';
			$key = 'page.includeJS'. $type .'.jquery';
			$pObj->config[] = 'page.javascriptLibs.jQuery = 0' . PHP_EOL 
				.$key.' = EXT:jst_assets/Resources/Public/Vendor/jquery/'.$conf['jquery']. PHP_EOL
				.$key.'.forceOnTop = 1';
        }
    }
	
}