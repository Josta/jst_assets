<?php
namespace Josta\JstAssets\ViewHelpers;

use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;
use Josta\JstAssets\Utility\IconUtility;

class IconViewHelper extends AbstractViewHelper {
	
    /**
     * @var boolean
     */
    protected $escapeOutput = false;
	
	/**
	 * @var array
	 */
	protected static $extConf;
	
    public function initializeArguments() {
        $this->registerArgument('name', 'string', 'Icon name (without "icon-" prefix)', true, '');
		$this->registerArgument('classes', 'string', 'Additional Classes', false, '');
		static::$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['jst_assets']);
    }

    /**
     * @return string
     */
    public function render() {
		IconUtility::recordIconUse($this->arguments['name']);
		
		$type = static::$extConf['icon_sprite_type'];
		$delivery = static::$extConf['icon_sprite_delivery'];
		$name = 'icon-'.$this->arguments['name'];
		$classes = empty($this->arguments['classes']) ? '' : ' '.$this->arguments['classes'];
		$extfile = '/typo3temp/jst_assets/jst_assets_icons.svg';
	
		if (($type == 'css_bg') || ($type == 'css_bg') || ($type == 'css_maskfont')) {
			return '<span class="icon '.$name.$classes.'"></span>';
		}
		if ($type == 'svg_symbols') {
			if (($delivery == 'inline') || ($delivery == 'inline_used')) {
				return '<svg class="icon '.$name.$classes.'"><use href="#'.$name.'" xlink:href="#'.$name.'"></use></svg>';
			} elseif ($delivery == 'file') {
				return '<svg class="icon '.$name.$classes.'"><use href="'.$extfile.'#'.$name.'" xlink:href="'.$extfile.'#'.$name.'"></use></svg>';
			}
		}
		/*if ($type == 'svg_view' && $delivery == 'file') {
			return '<img class="icon '.$name.$classes.'" src="/typo3temp/jst_assets/jst_assets_icons.svg#'.$name.'" />';
		}*/
		
		debug('No suitable icon rendering was found for ' . $type . ' ' . $delivery . ' sprites.');
		return '';
    }
	
	/**
	 * @param string $name
	 * @param string $classes
	 * @return string
	 */
	public function forwardRender($name, $classes) {
		$this->setArguments(['name' => $name, 'classes' => $classes]);
		return $this->initializeArgumentsAndRender();
	} 

}
