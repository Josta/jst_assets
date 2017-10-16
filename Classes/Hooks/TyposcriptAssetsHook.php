<?php
namespace Josta\JstAssets\Hooks;

use Josta\JstAssets\Service\AssetService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use FluidTYPO3\Vhs\ViewHelpers\Asset\AssetInterface;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use Josta\JstAssets\Utility\IconUtility;

class TyposcriptAssetsHook {

    /**
     * @param array $params
     * @param \TYPO3\CMS\Core\Page\PageRenderer $pagerenderer
     */
    public function precompileStandardAssets(&$params, &$pagerenderer) {
        if (TYPO3_MODE != 'FE') return;
		$assetSvc = AssetService::getInstance();
		$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['jst_assets']);

		// Run precompilers
		foreach (['jsLibs', 'jsFooterLibs', 'jsFiles', 'jsFooterFiles', 'cssLibs', 'cssFiles'] as $type) {
			if (!is_array($params[$type])) continue;
			$out = [];
			foreach ($params[$type] as $file => $settings) {
				$options = [];				
				if (($extConf['autoprefix_mode'] == 'allwrap') && ($settings['allWrap'] == 'autoprefix')) {
					$settings['allWrap'] = NULL;
					$options['autoprefix'] = TRUE;
				}
				
				$output_file = $assetSvc->compileFileCached($settings['file'], $options);
				
				if ($output_file === FALSE) {
					$out[$file] = $settings;
					continue;
				}

				$settings['file'] = $output_file;
				$out[$output_file] = $settings;

			}
			$params[$type] = $out;
		}

		// Include CSS icon sprite	
		$sprite_type = $extConf['icon_sprite_type'];
		$delivery = $extConf['icon_sprite_delivery'];	
		if ($delivery == 'file') {
			$sprite = IconUtility::getSpriteFile($sprite_type);	
		}	
		if (($sprite_type == 'css_bg') || ($sprite_type == 'css_mask') || ($sprite_type == 'css_maskfont')) {		
			if ($delivery == 'file') {			
				if (!is_array($params['cssFiles'])) {
					$params['cssFiles'] = [];
				}
				$params['cssFiles'][$sprite] = [
					'file' => $sprite,
					'rel' => 'stylesheet',
					'media' => 'all',
					'title' => '',
					'compress' => TRUE,
					'forceOnTop' => FALSE,
					'allWrap' => NULL,
					'excludeFromConcatenation' => TRUE,
					'splitChar' => NULL
				];	
			} elseif ($delivery == 'inline' && $delivery == 'inline_used') {
				if (!is_array($params['inlineCss'])) {
					$params['cssInline'] = [];
				}
				$onlyUsedIcons = ($delivery == 'inline_used');
				$params['cssInline']['jst_assets_icons'] = [
					'code' => IconUtility::getInlineSprite($sprite_type, $onlyUsedIcons),			
				];	
			}		
		}
		
    }
	
	/**
	 * Called after content processing, before the page parts are assembled.
	 * Includes an invisible SVG sprite directly after the body tag (SVG symbols inline mode).
	 *
     * @param array $params
     * @param \TYPO3\CMS\Core\Page\PageRenderer $pagerenderer
     */
	public function includeSvgIconSprite(&$params, &$pagerenderer) {
		if (TYPO3_MODE != 'FE') return;
		$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['jst_assets']);
		$onlyUsedIcons = ($extConf['icon_sprite_delivery'] == 'inline_used');
		$sprite = IconUtility::getInlineSprite('svg_symbols', $onlyUsedIcons);	
		$params['bodyContent'] = preg_replace('/(<body.*?>)/', '$1'.$sprite, $params['bodyContent']);
	}
	
	/**
	 * @param array $params
	 * @param \TYPO3\CMS\Core\DataHandling\DataHandler $pObj
	 */
    public function clearCache(array $params, DataHandler &$pObj) {
        if (isset($params['cacheCmd']) && ($params['cacheCmd'] == 'all'))
			AssetService::getInstance()->clearFileCache();
    }
	
	
	
}
