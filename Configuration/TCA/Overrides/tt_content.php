<?php
defined('TYPO3_MODE') || die ();

$ll = 'LLL:EXT:jst_assets/Resources/Private/Language/locallang.xlf:';
$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['jst_assets']);

if ($extConf['content_snippets']) {

	$content_fields = [
		'jst_assets_class' => [
			'exclude' => 0,
			'label' => $ll.'field.css_class',
			'l10n_mode' => 'mergeIfNotBlank',
			'config' => ['type' => 'input', 'max' => 255, 'size' => 50]
		],
		'jst_assets_style' => [
			'exclude' => 0,
			'label' => $ll.'field.custom_'.$extConf['style_snippet_mode'],
			'l10n_mode' => 'mergeIfNotBlank',
			'config' => ['type' => 'text', 'cols' => 110, 'rows' => 15, 'renderType' => 't3editor', 'format' => 'css'],
			/*'defaultExtras' => 'enable-tab',*/
		],
		'jst_assets_script' => [
			'exclude' => 0,
			'label' => $ll.'field.custom_'.$extConf['script_snippet_mode'],
			'l10n_mode' => 'mergeIfNotBlank',
			'config' => ['type' => 'text', 'cols' => 110, 'rows' => 15, 'renderType' => 't3editor', 'format' => 'javascript'],
			/*'defaultExtras' => 'enable-tab',*/
		]
	];

	// Tell TYPO3 the new fields exist
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('tt_content', $content_fields);

	// Show the fields in BE flexforms (in new tab 'Assets')
	$show_fields = 'jst_assets_class';
	if ($extConf['style_snippet_mode'] !== '0') 
		$show_fields .= ', jst_assets_style';
	if ($extConf['script_snippet_mode'] !== '0')
		$show_fields .= ', jst_assets_script';
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
		'tt_content', '--div--;'.$ll.'tt_content.tabs.assets, ' . $show_fields);
		
}