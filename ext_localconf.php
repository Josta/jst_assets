<?php
if (!defined('TYPO3_MODE')) die ();

$conf = unserialize($_EXTCONF);
$cache_conf = &$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'];
$hook_conf = &$GLOBALS ['TYPO3_CONF_VARS']['SC_OPTIONS'];

// Caching Framework: register snippet cache 
if (($conf['page_snippets'] || $conf['content_snippets']) && !is_array($cache_conf['jst_assets'])) {
	$cache_conf['jst_assets'] = [
		'backend' => \TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
		'options' => ['defaultLifetime' => 3600],
		'groups' => ['pages']
	];
}

// Include our Static template right after standard content rendering
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript('JstAssets', 'setup',
	'<INCLUDE_TYPOSCRIPT: source="FILE:EXT:jst_assets/Configuration/TypoScript/Basic/setup.txt">',
	'defaultContentRendering');

// Include Twitter Bootstrap
if ($conf['bootstrap']) {
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript('JstAssets', 'setup',
		'<INCLUDE_TYPOSCRIPT: source="FILE:EXT:jst_assets/Configuration/TypoScript/Bootstrap/'.$conf['bootstrap'].'.txt">',
		'defaultContentRendering');
}

// Hook: add jQueryAnywhere hack at the end of TS
if ($conf['jquery_placement'] == 'footerhack') {
	$hook_conf['t3lib/class.t3lib_tstemplate.php']['includeStaticTypoScriptSourcesAtEnd']['jstassets_jquery_anywhere'] =
		\Josta\JstAssets\Hooks\IncludeLibrariesHook::class . '->includeJQueryAnywhereHack';
}

// Include jQuery at the end of TS
if ($conf['jquery']) {
	$hook_conf['t3lib/class.t3lib_tstemplate.php']['includeStaticTypoScriptSourcesAtEnd']['jstassets_jquery'] =
		\Josta\JstAssets\Hooks\IncludeLibrariesHook::class . '->includeJQuery';
}

// Hook: collect content SASS/CoffeeScript snippets
if ($conf['content_snippets']) {
	$hook_conf['tslib/class.tslib_content.php']['postInit']['jstassets_collect_snippets'] =
		\Josta\JstAssets\Hooks\SnippetHook::class;
}

// Hook: compile and include SASS/CoffeeScript snippets
if ($conf['page_snippets'] || $conf['content_snippets']) {
	$hook_conf['t3lib/class.t3lib_pagerenderer.php']['render-postProcess']['jstassets_include_snippets'] =
		\Josta\JstAssets\Hooks\SnippetHook::class . '->postProcessPageRendering';
}

// XCLASS: preprocess VHS assets
if ($conf['precompile_vhs_assets'] && \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('vhs')) {
	$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects']['FluidTYPO3\\Vhs\\Service\\AssetService'] = [
	   'className' => Josta\JstAssets\Service\VhsAssetService::class];
}

// Hook: preprocess standard Typoscript assets, and include CSS icon sprite
if ($conf['precompile_standard_assets']) {
	$hook_conf['t3lib/class.t3lib_pagerenderer.php']['render-preProcess']['jstassets_precompile_standard_assets'] =
	\Josta\JstAssets\Hooks\TyposcriptAssetsHook::class . '->precompileStandardAssets';
}

// Hook: include SVG icon sprite (if inline)
if (($conf['icon_sprite_type'] == 'svg_symbols') && (($conf['icon_sprite_delivery'] == 'inline') || ($conf['icon_sprite_delivery'] == 'inline_used'))) {
	$hook_conf['t3lib/class.t3lib_pagerenderer.php']['render-postProcess']['jstassets_include_svg_icon_sprite'] = 
		\Josta\JstAssets\Hooks\TyposcriptAssetsHook::class . '->includeSvgIconSprite';
}

// Add basic icon CSS depending on sprite type
if ($conf['icon_sprite_type'] != 'none') {
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(
		'page.includeCSS.jst_assets_icons_base = EXT:jst_assets/Resources/Public/icons-'.$conf['icon_sprite_type'].'.css');
}

if (TYPO3_MODE === 'BE') {

	// Hook: invalidate compiled snippet cache (on page/content save)
	if ($conf['page_snippets'] || $conf['content_snippets']) {
		$hook_conf['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['jstassets_clear_snippet_cache'] =
			\Josta\JstAssets\Hooks\SnippetHook::class;
	}

	// Hook: remove compiler cache folder on cache clear 'all'
	if ($conf['precompile_standard_assets']) {
		$hook_conf['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['jstassets_clear_asset_cache'] =
			\Josta\JstAssets\Hooks\TyposcriptAssetsHook::class . '->clearCache';
	}

}

