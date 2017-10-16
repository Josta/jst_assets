<?php
namespace Josta\JstAssets\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\SingletonInterface;
use FluidTYPO3\Vhs\ViewHelpers\Asset\AssetInterface;
use Josta\JstAssets\Service\AssetService;

/**
 * Overridden Asset Handling Service
 */
class VhsAssetService extends \FluidTYPO3\Vhs\Service\AssetService implements SingletonInterface {

	/**
	 * Inject precompiler into standard asset generation
	 */
	protected function buildAsset($asset) {
		return $this->precompileAsset($asset, parent::buildAsset($asset));	
	}
	
	/**
	 * Inject precompiler into fluid asset generation
	 */
	protected function renderAssetAsFluidTemplate($asset) {
		return $this->precompileAsset($asset, parent::renderAssetAsFluidTemplate($asset));
    }
	
	/**
	 * Precompiles the asset if the 'precompile' option is set.
	 */
	private function precompileAsset($asset, $content) {
		$settings = $this->extractAssetSettings($asset);
		$precompile = $settings['precompile'];
		if (isset($precompile)) {
			$dir = dirname(GeneralUtility::getFileAbsFileName($settings['path']));
			$options = [];
			if (($precompile == 'scss') && isset($settings['autoprefix'])) {
				$options['autoprefix'] = (boolean) $settings['autoprefix'];
			}
			$content = AssetService::getInstance()->compileContent($content, $dir, $precompile, $options);
			
		}
		return $content;
	}
	
	/**
	 * Override settings retrieval for type conversion.
	 *
     * @param mixed $asset An Asset ViewHelper instance or an array containing an Asset definition
     * @return array
     */
    protected function extractAssetSettings($asset) {
        $settings = parent::extractAssetSettings($asset);
		
		$type = $settings['type'];
		$typeConv = ['coffee' => 'js', 'scss' => 'css', 'sass' => 'css', 'less' => 'css'];
		if (isset($typeConv[$type])) {
			$settings['precompile'] = $type;
			$settings['type'] = $typeConv[$type];
		}
		return $settings;
    }
	
}
