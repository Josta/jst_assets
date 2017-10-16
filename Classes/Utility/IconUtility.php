<?php
namespace Josta\JstAssets\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class IconUtility {
	
	/**
	 * @var array
	 */
	protected static $iconPaths = [];
	
	/**
	 * @var array
	 */
	protected static $usedIcons = [];
	
	
	/**
	 * Registers a folder containing SVG icons.
	 *
	 * @param string $path	the folder path, optionally in 'EXT:' syntax
	 * @return void
	 * @api
	 */
	public static function addIconPath($path) {
		static::$iconPaths[] = $path;
	}
	
	/**
	 * Tells the icon utility that an icon was used in page rendering.
	 * 
	 * @param string icon	the icon name (without the "icon-" prefix)
	 * @return void
	 * @api
	 */
	public static function recordIconUse($icon) {
		static::$usedIcons[$icon] = true;
	}
	
	/**
	 * Creates a temp SVG/CSS sprite file containing all registered icons.
	 * 
	 * @param string $mode	one of 'css_bg', 'css_maskfont', 'css_mask' or 'svg_symbols'
	 * @return string 	the sprite filename (relative to PATH_site)
	 * @api
	 */
	public static function getSpriteFile($mode) {
		$cacheDir = PATH_site.'typo3temp/jst_assets/';
		if(!file_exists($cacheDir))
			GeneralUtility::mkdir($cacheDir);
		$filename = ($mode == 'svg_symbols') ? 'jst_assets_icons.svg' : 'jst_assets_icons.css';
		$cacheFile = GeneralUtility::getFileAbsFileName($cacheDir . $filename);

		if(!file_exists($cacheFile)) {
			file_put_contents($cacheFile, static::getInlineSprite($mode, false));
		}
		return substr($cacheFile, strlen(PATH_site));
	}

	/**
	 * Creates a sprite snippet ready for inline inclusion.
	 *
	 * @param string $mode	one of 'css_bg', 'css_maskfont', 'css_mask' or 'svg_symbols'
	 * @param boolean $onlyUsedIcons	whether all registered icons should be included, or only those used in a viewhelper
	 * @return string	the snippet (an SVG tag, or pure CSS, not wrapped in a style tag)
	 * @api
	 */
	public static function getInlineSprite($mode, $onlyUsedIcons = true) {
		$icons = $onlyUsedIcons ? static::collectUsedIcons() : static::collectIcons();
		if ($mode == 'css_bg') {
			return static::buildCssBgSprite($icons);
		} elseif ($mode == 'css_maskfont') {
			return static::buildCssMaskFontSprite($icons);
		} elseif ($mode == 'css_mask') {
			return static::buildCssMaskSprite($icons);
		} elseif ($mode == 'svg_symbols') {
			return static::buildSvgSprite($icons);
		}
		return '';
	}

	
	
	
	/**
	 * Returns all registered icons that are also used.
	 * @return array 	associative array(iconname => filename)
	 */
	protected static function collectUsedIcons() {
		return array_filter(static::collectIcons(), function($name) {
			return isset(static::$usedIcons[$name]);
		}, ARRAY_FILTER_USE_KEY);
	} 
	
	/**
	 * Collects all registered icons. Note that icons with the same filename will be overridden.
	 * @return array 	associative array(iconname => filename)
	 */
	protected static function collectIcons() {
		$settings = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_jstassets.']['settings.'];
		$paths = array_merge(static::$iconPaths, array_values($settings['icons.'] ?: []));
		
		$collection = [];
		foreach ($paths as $path) {
			$absPath = GeneralUtility::getFileAbsFileName($path);
			foreach (GeneralUtility::getFilesInDir($absPath, 'svg', true) as $iconFile) {
				$iconName = strtolower(pathinfo($iconFile, PATHINFO_FILENAME));
				$collection[$iconName] = $iconFile;
			}
		}
		return $collection;
	}
	
	protected static function buildSvgSprite($collection) {
		$sprite = '';
		foreach ($collection as $iconName => $iconFile) {
			$iconContent = file_get_contents($iconFile);
			
			// match SVG tag attributes and content
			$parts = [];
			if (!preg_match('~<svg(.*?)>(.*?)</svg>~s', $iconContent, $parts))
				continue;			
			$content = $parts[2];
			
			// find viewpox property
			$dims = [];
			$props = [];
			preg_match_all('~\\s(width|height|viewBox)=(".*?"|\'.*?\')~i', $parts[1], $props, PREG_SET_ORDER);			
			foreach ($props as $prop) {
				$dims[strtolower($prop[1])] = trim($prop[2], '"\'');
			}
			$viewbox = $dims['viewbox']; // ?: isset($dims['width']) ? '0 0 '.$dims['width'].' '.$dims['height'] : '0 0 33 32';

			$sprite .= '<symbol id="icon-'.$iconName.'" viewBox="'.$viewbox.'">'.$content.'</symbol>';
		}
		return '<svg style="position:absolute;width:0;height:0;overflow:hidden;" version="1.1" viewBox="0 0 35 35" '
			.'xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">' . $sprite . '</svg>';
	}
	
	protected static function buildCssBgSprite($collection) {
		$sprite = '';			
		foreach ($collection as $iconName => $iconFile) {
			$iconContent = rawurlencode(preg_replace('/^.*<svg/s', '<svg', file_get_contents($iconFile)));
			$sprite .= ".icon-".$iconName.":before{background-image:url('data:image/svg+xml;utf8,".$iconContent."')}";
		}
		return $sprite;
	}
	
	protected static function buildCssMaskFontSprite($collection) {
		return static::buildCssMaskSprite($collection);
	}
	
	protected static function buildCssMaskSprite($collection) {
		$sprite = '';			
		foreach ($collection as $iconName => $iconFile) {
			$iconContent = rawurlencode(preg_replace('/^.*<svg/s', '<svg', file_get_contents($iconFile)));
			$sprite .= ".icon-".$iconName.":before{"
				."-webkit-mask-image:url('data:image/svg+xml;utf8,".$iconContent."');"
				."mask-image:url('data:image/svg+xml;utf8,".$iconContent."')}";
		}
		return $sprite;
	}
	
}