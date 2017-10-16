<?php
namespace Josta\JstAssets\Service;

use FluidTYPO3\Vhs\Asset;
use Josta\JstAssets\Service\AssetService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\Cache\CacheManager;

/**
 * The SnippetProcessor is used to collect all pages and content snippets and compile them in the end.
 */
class SnippetService implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * @var \TYPO3\CMS\Core\Cache\Frontend\FrontendInterface
	 */
	protected $cache;
	
	/**
	 * @var array
	 */
	protected $settings;
	
	/**
	 * @var array
	 */
	protected $extConf;

	/**
	 * The compiled asset cache entry
	 * @var array
	 */
	private $cache_entry;
	
	/**
	 * Collected script (type depending on script_snippet_mode)
	 * @var string
	 */
	private $script = '';
	
	/**
	 * Collected style (type depending on style_snippet_mode)
	 * @var string
	 */
	private $style = '';
	
	/**
	 * script_snippet_mode
	 * @var string
	 */
	private $scriptMode;
	
	/**
	 * Whether scripts should be wrapped in in jQuery ready handlers
	 * @var bool
	 */
	private $wrapScripts;
	
	/**
	 * Whether styles should be wrapped in in a parent element scope
	 * @var bool
	 */
	private $wrapStyles;
	
	/**
	 * style_snippet_mode
	 * @var string
	 */
	private $styleMode;
	
	
	
	/**
	 * The current page UID
	 * @var int
	 */
	private $pid;
	
	
	public static function getInstance() {
		return GeneralUtility::makeInstance(self::class);
	}
	
	/**
	 * Initializes the SnippetProcessor. Since it is a singleton, this is only
	 * executed once before the first use of the SnippetProcessor.
	 */
	function __construct() {
		$this->pid = intval($GLOBALS['TSFE']->id);
		$this->cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('jst_assets');
		$this->settings = &$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_jstassets.']['settings.']['snippets.'] ?: [];
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['jst_assets']);
		$this->scriptMode = $this->extConf['script_snippet_mode'];
		$this->styleMode = $this->extConf['style_snippet_mode'];
		$this->wrapScripts = $this->extConf['wrap_script_snippets'];
		$this->wrapStyles = $this->extConf['wrap_style_snippets'];
		
		// get cache entry
		$css = $this->cache->get($this->pid . 'css');
		$js  = $this->cache->get($this->pid . 'js');
		$this->cache_entry = ($js === FALSE || $css === FALSE) ? NULL : [$css, $js];

		// get page snippets (unless cache is valid)
		if (empty($this->cache_entry)) {
			
			// get pages		
			$depth = intval($this->settings['recursive'] ?: 0);
			$pids = explode(',', GeneralUtility::makeInstance(QueryGenerator::class)->getTreeList($this->pid, $depth, 0, 1));

			// fetch CSS/JS fields from pages	
			$repo = GeneralUtility::makeInstance(\TYPO3\CMS\Frontend\Page\PageRepository::class);
			$repo->init(false);
			foreach ($repo->getMenuForPages($pids, 'uid, jst_assets_script, jst_assets_style') as $page) {
				$this->addScript($page['jst_assets_script']);
				$this->addStyle($page['jst_assets_style'], '#p'.$page['uid']);
			}
		}
	}
	
	/**
	 * Collects asset snippets from the given content element record.
	 *
	 * @return void
	 */
	public function collectContentSnippets($content_record) {
		if (empty($this->cache_entry)) {
			$this->addScript($content_record['jst_assets_script']);
			$this->addStyle($content_record['jst_assets_style'], '#c'.$content_record['uid']);
		}
	}
	
	
	/**
	 * Compiles and includes all collected assets
	 * 
	 * @return void
	 */
	public function compileAndIncludeSnippets() {
		if (empty($this->cache_entry)) {	
		
			// compile assets
			$as = AssetService::getInstance();
			$css = (!$this->styleMode) ? '' : ($this->styleMode === 'css') ? $this->style
				: $as->rewriteRelativeResourceURLs($as->compileContent($this->style, '', $this->styleMode), '/');		
			$js = (!$this->scriptMode) ? '' : ($this->scriptMode === 'js') ? $this->script
				: $as->compileContent($this->script, '', $this->scriptMode);
			
			// store assets in cache
			$lifetime = $this->settings['cache_lifetime'] ?: 86400; // 1 day	
			$this->cache->set($this->pid . 'css', $css, ['css'], $lifetime);
			$this->cache->set($this->pid . 'js', $js,  ['js'], $lifetime);		
		} else {		
			// get assets from cache
			$css = $this->cache_entry[0];
			$js = $this->cache_entry[1];
		}
		
		// include assets using the VHS asset pipeline
		if (!empty($js)) {
			Asset::createFromSettings([
				'name' => 'page'.$this->pid.'js',
				'type' => 'js',
				'content' => $js,
				'dependencies' => ['jquery'],
				'standalone' => 1
			]);
		}
		if (!empty($css)) {
			Asset::createFromSettings([
				'name' => 'page'.$this->pid.'css',
				'type' => 'css',
				'content' => $css,
				'standalone' => 1
			]);
		}
	}
	
	
	// adds a CoffeeScript snippet to the collection
	private function addScript($str) {
		if (empty(trim($str))) return;	
		$this->script .= PHP_EOL;
		if ($this->scriptMode == 'coffee') {
			$this->script .= (!$this->wrapScripts) ? $str
				: '$ ->' . PHP_EOL . "\t" . preg_replace("/[\r\n]+/", PHP_EOL . "\t", $str);
		} elseif ($this->scriptMode == 'js') {
			$this->script .= (!$this->wrapScripts) ? $str
				: '$(document).ready(function(){' . $str . '});';
		}
	}

	// adds a SCSS (or LESS) snippet to the collection
	private function addStyle($str, $scope = '') {
		if (empty(trim($str))) return;
		$this->style .= PHP_EOL;
		if ($this->styleMode == 'css') {
			$this->style .= PHP_EOL . $str;
		} elseif ($this->styleMode == 'scss' || $this->styleMode == 'less') {
			$this->style .= (!$this->wrapStyles || empty($scope)) ? $str
				: $scope . ' {' . $str . '} ';
		}	
	}
	
}
