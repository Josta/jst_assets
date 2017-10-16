<?php
namespace Josta\JstAssets\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

class AssetService implements \TYPO3\CMS\Core\SingletonInterface {

	/**
	 * TS key 'plugin.tx_jstassets.settings', with dots
	 * @var array
	 */
	var $settings;
	
	/**
	 * @var array
	 */
	protected $extConf;

	/**
	 * Instantiates the asset service.
	 */
	public static function getInstance() {
		return GeneralUtility::makeInstance(self::class);
	}

	/**
	 * Instantiates the asset service. Dont' use 'new' directly, use getInstance() instead.
	 */
	function __construct() {
		$this->settings = &$GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_jstassets.']['settings.'] ?: [];
		$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['jst_assets']);
		//require_once(GeneralUtility::getFileAbsFileName('EXT:jst_assets/Contrib/less.php-1.7.0/Less.php'));
		//require_once(GeneralUtility::getFileAbsFileName('EXT:jst_assets/Contrib/scssphp-0.6.7/scss.inc.php'));
		//require_once(GeneralUtility::getFileAbsFileName('EXT:jst_assets/Contrib/coffeescript-php-1.3.1/src/CoffeeScript/Init.php'));
		require_once(GeneralUtility::getFileAbsFileName('EXT:jst_assets/vendor/autoload.php'));
	}

	/**
	 * Compiles an asset file depending on its file extension (.scss, .sass, .less or .coffee).
	 * The result is stored in a cache file and referenced resource URLs are rewritten accordingly.
	 *
	 * @param string $filename	the original asset file
	 * @param array $options	e.g. option autoprefix or noIncludes
	 * @return string|false		the cache file, or FALSE
	 */
	public function compileFileCached($filename, $options) {
		$cacheDir = PATH_site.'typo3temp/jst_assets/';
		if(!file_exists($cacheDir))
			GeneralUtility::mkdir($cacheDir);

		$origFile = GeneralUtility::getFileAbsFileName($filename);
		$ext = pathinfo($origFile)['extension'];
		$ext = ($ext == 'coffee') ? 'js' : ((($ext == 'scss') || ($ext == 'sass') || ($ext == 'less')) ? 'css' : '');		
		if (empty($ext))
			return false;
		
		$cacheFileName = pathinfo($origFile)['filename'] .'.'. md5(filemtime($origFile)."\t".filesize($origFile)."\t".$origFile) .'.'. $ext;
		$cacheFile = GeneralUtility::getFileAbsFileName($cacheDir . $cacheFileName);

		if(!file_exists($cacheFile)) {
			$content = $this->compileFile($origFile, $options);				
			if ($content !== FALSE) {
				$reldir = PathUtility::getRelativePath($cacheDir, dirname($origFile));
				$content = self::rewriteRelativeResourceURLs($content, $reldir);
				file_put_contents($cacheFile, $content);
			} else {
				return false;
			}
		}
		return PathUtility::getRelativePath(PATH_site, $cacheDir) . $cacheFileName;
	}
	
	/**
	 * Removes all cached asset files created with compileFileCached().
	 */
	public static function clearFileCache() {
		GeneralUtility::rmdir(PATH_site . 'typo3temp/jst_assets', true);
	}
	
	/**
	 * Compiles an asset file depending on its file extension (.scss, .sass, .less or .coffee).
	 * Does NOT rewrite any resource URLs!
	 *
	 * @param string $filename	the original asset filename
	 * @param array $options	e.g. options noIncludes or autoprefix
	 * @return string|false		the compilation result as a string (not file!), or FALSE
	 */
	protected function compileFile($filename, $options) {
		$absfile = GeneralUtility::getFileAbsFileName($filename);
		$ext = pathinfo($absfile)['extension'];
		
		if (($ext == 'less') || ($ext == 'scss') || ($ext == 'coffee') || ($ext == 'sass')) {
			$filecontent = file_get_contents($absfile);
			$path = dirname($absfile);
			$output = $this->compileContent($filecontent, $path, $ext, $options);
			return $output;
		}
		return false;
	}
	
	/**
	 * Compiles an asset string with the compiler indicated by $type.
	 * Does NOT rewrite any resource URLs!
	 *
	 * @param string $content		less, scss or coffeescript code
	 * @param string $includePath	base path for imports
	 * @param string $type			'less', 'scss', 'sass' or 'coffee'
	 * @param array $options		e.g. options noIncludes or autoprefix
	 * @return string|false 		the compilation result as a string, or FALSE
	 */
	public function compileContent($content, $includePath, $type, $options=[]) {
		if ($type == 'less') {
			return $this->compileLESS($content, $includePath, $options);
		} elseif (($type == 'scss') || ($type == 'sass')) {
			return $this->compileSCSS($content, $includePath, $options);
		} elseif ($type == 'coffee') {
			return $this->compileCoffeeScript($content, $includePath, $options);
		}
		return false;
	}
	
	
	
	/**
	 * Compiles the given input string with CoffeeScript, using the given path for imports.
	 *
     * @param string $input		CoffeeScript code
     * @param string $path		import path (empty => web root)
	 * @param array $options	e.g. option noIncludes:bool
     * @return string			JS code
     * @throws \Exception		CoffeScript parser errors
     */
	protected function compileCoffeeScript($input, $path = '', $options = []) {
		if (empty(trim($input))) return '';
		$path = rtrim(empty($path) ? PATH_site : $path, '/') . '/';
		try {
			\CoffeeScript\Init::load();
			$output = \CoffeeScript\Compiler::compile($input,
				['filename' => $path.'anonymous.coffee', 'header' => '', bare => true]);

			if (!$options['noIncludes'] && $this->extConf['coffee_includes'])
				$output = $this->resolveScriptIncludes($output, $path);
				
			return $output;
		} catch (Exception $e) {
			throw new \Exception('CoffeeScript' . $e->getMessage());
		}
	}

	/**
	 * Compiles the given input string with SASS, using the given path for imports.
	 * Prepends includes and variables from plugin.tx_jstassets.settings.scss.
	 * Does NOT rewrite any resource URLs!
	 *
     * @param string $input		SCSS code
     * @param string $path		import path (empty => web root)
	 * @param array $options	e.g. option autoprefix:bool
     * @return string			CSS code
     * @throws \Exception		SASS parser errors
     */
	protected function compileSCSS($input, $path = '', $options = []) {
		if (empty(trim($input))) return '';
		$path = rtrim(empty($path) ? PATH_site : $path, '/') . '/';
		$includes = $this->getStyleIncludes('scss');
		try {
			$parser = new \Leafo\ScssPhp\Compiler();
			$parser->addImportPath($path);
			$parser->addImportPath(PATH_site);
			$parser->setFormatter(\Leafo\ScssPhp\Formatter\Crunched::class);
			$output = $parser->compile($includes . $input, $path);
			if ($options['autoprefix'] || ($this->extConf['autoprefix_mode'] == 'always'))
				$output = self::autoprefixCSS($output);
			return preg_replace('/\s+/', ' ', $output);
		} catch(Exception $e) {
			throw new \Exception('SCSS: ' . $e->getMessage());
		}
	}

	/**
	 * Compiles the given input string with LESS, using the given path for imports.
	 * Prepends includes and variables from plugin.tx_jstassets.settings.less.
	 * Does NOT rewrite any resource URLs!
	 *
     * @param string $input		LESS code
     * @param string $path		import path (empty => web root)
	 * @param array $options	e.g. option autoprefix:bool
     * @return string			CSS code
     * @throws \Exception		LESS parser errors
     */
    protected function compileLESS($input, $path = '', $options = []) {
		$path = rtrim(empty($path) ? PATH_site : $path, '/') . '/';
		$options = ['compress' => true,	'relativeUrls' => false, 'import_dirs' => [$path]];
		$includes = $this->getStyleIncludes('less');
		try {
			$parser = new \Less_Parser($parser_options);
			$parser->Parse($includes . $input);
			$output = $parser->getCss();
			if ($options['autoprefix'] || ($this->extConf['autoprefix_mode'] == 'always'))
				$output = self::autoprefixCSS($output);
			return $output;
		} catch (\Exception $e) {
			throw new \Exception('LESS:' . $e->getMessage());
		}
    }
	
	
	
	/**
	 * Includes all files from the subkey 'includes', and all variables from the subkey 'variables'.
	 * @param string $key 	'less', 'scss' or 'sass'
	 * @return string		the include string with all import and variable definitions
	 */
	protected function getStyleIncludes($key) {
		$keySettings = $this->settings[$key . '.'];
		$varPrefix = ($key == 'less') ? '@' : '$';
		$inc = '';
		
		if (($key == 'scss') || ($key == 'sass')) {
			$inc .= '@import "'.GeneralUtility::getFileAbsFileName(
				'EXT:jst_assets/Resources/Public/_mixin-icons-'.$this->extConf['icon_sprite_type'].'.scss').'";'.PHP_EOL;
		}
		
		if (isset($keySettings)) {
			if (isset($keySettings['includes.'])) {
				$includes = self::sortTSEntriesByDependency($keySettings['includes.']);
				foreach ($includes as $key => $include)
					$inc .= '@import "'.GeneralUtility::getFileAbsFileName($include['path']).'";'.PHP_EOL;
			}
			if (isset($keySettings['variables.'])) {
				foreach ($keySettings['variables.'] as $name => $value)
					$inc .= $varPrefix.$name.':'.$value.';'.PHP_EOL;
			}
		}
		return $inc;
	}

	
	
	
	/**
	 * Reorders an array of TS entries by dependencies.
	 * The key of each entry is its name, which can be referenced
	 * in the 'dependencies' CSV subkey of other entries.
	 * 
	 * @param array $ts		array of TS entries (with dots)
	 * @return array		reordered array
	 */
	protected static function sortTSEntriesByDependency($ts) {
		// heineously reappropriated from the VHS AssetService class :)
        $placed = [];
        $entryNames = (0 < count($ts)) ? array_keys($ts) : [];
        while ($entry = array_shift($ts)) {
            $postpone = false;
            $name = array_shift($entryNames);
            $dependencies = isset($entry['dependencies']) ? $entry['dependencies'] : [];
            if (!is_array($dependencies))
            	$dependencies = GeneralUtility::trimExplode(',', $dependencies, true);
            foreach ($dependencies as $dep) {
                if (array_key_exists($dep, $ts) && !isset($placed[$dep])) {
                    if (0 === count($ts))
                        throw new \RuntimeException(sprintf('Entry "%s" depends on "%s" '.
                        	'but "%s" was not found', $name, $dep, $dep), 1358603979);
                    $ts[$name] = $entry;
                    $entryNames[$name] = $name;
                    $postpone = true;
                }
            }
            if (false === $postpone)
                $placed[$name] = $entry;
        }
        return $placed;
	}
	
	/**
	 * Looks for referenced resources. Prepends relative resources with
	 * $relpath and resolves 'EXT:' paths.
	 *
	 * @param string $content	CSS code
	 * @param $relpath 			relative path from the CSS file location to the resources location
	 * @return string			CSS code with rewritten URLs
	 */
	public static function rewriteRelativeResourceURLs($content, $relpath) {	
		// find all 'url(...)' occurrences
		$regex = '/(\s|:)url\((".*?"|\'.*?\'|[^"\'].*?)\)/';	
		return preg_replace_callback($regex, function(array $m) use ($relpath) {
			// trim quotes and whitespace from contained URL
			$quote = substr($m[2], 0, 1);
			$quote = ($quote !== '"' && $quote !== "'") ? '' : $quote;
			$url = trim(trim($m[2], '" \t'), "'");
					
			if (preg_match('/^EXT:/', $url) === 1) {
				// resolve 'EXT:' urls
				$url = substr(GeneralUtility::getFileAbsFileName($url), strlen(PATH_site) - 1);
				return $m[1] .'url('. $quote.$url.$quote .')';
			} elseif (preg_match('~(^https?://)|(^/)|(^data:)~', $url) === 1) {
				// don't change absolute, external or data URLs
				return $m[0];
			} else {
				// prepend relative URLs with $relpath
				return $m[1] .'url('. $quote.$relpath.$url.$quote .')';
			}	
		} , $content);
		return $content;
	}
	
	/**
	 * Looks for include rules, retrieves the included files via URL, 'EXT:' path,
	 * absolute or relative path.
	 *
	 * @param string $content	(compiled) coffescript with '~include' rules
	 * @param string $path 		base path for relative URLs
	 * @return string			the compiled coffescript with resolved includes
	 */
	protected function resolveScriptIncludes($content, $path) {	
		// "~include '...'" rules have been converted to "~include('...');" by the compiler
		$regex = "/~include\\(('.*?'|\".*?\")\\);/";	
		return preg_replace_callback($regex, function(array $m) use ($path) {
			$url = trim($m[1], '"\'');	
			$external = false;
			if (preg_match('~^/~', $url) === 1) {
				// resolve absolute urls
				$url = GeneralUtility::getFileAbsFileName(ltrim($url, '/')); 
			} elseif (preg_match('/^EXT:/', $url) === 1) {
				// resolve 'EXT:' urls
				$url = GeneralUtility::getFileAbsFileName($url); 
			} elseif (preg_match('~(^https?://)~', $url) === 1) {
				// don't change external URLs
				$external = true;
			} else {
				// prepend path to relative URLs
				$url = GeneralUtility::getFileAbsFileName($path . ltrim($url, '/'));
			}
			// get included file
			$include = file_get_contents($url);
			
			// recursively parse CoffeeScript files
			if (pathinfo($url)['extension'] === 'coffee') {
				$include = $this->compileCoffeeScript($include, $external ? '' : dirname($url), ['noIncludes' => $external]);
			}
			
			return $include;	
		} , $content);
	}

	/**
	 * Adds vendor prefixed CSS properties, values and @-rules according to a static list.
	 * This is a very rudimentary replacement for the autoprefixer node.js package and in no way complete.
	 */
	protected function autoprefixCSS($content) {

		$prefix_properties = [
						
			// 'border-image' =>
			// 'font-feature-settings' => '-webkit-font-feature-settings,-moz-font-feature-settings',
			'box-sizing' => '-webkit-box-sizing,-moz-box-sizing',
			'filter' => '-webkit-filter',		
			
			'hyphens' => '-webkit-hyphens,-moz-hyphens,-ms-hyphens',
			'word-break' => '-ms-word-break',
			'object-fit' => '-o-object-fit',
			'appearance' => '-webkit-appearance,-moz-appearance',
			'user-select' => '-webkit-user-select,-moz-user-select,-ms-user-select',

			'perspective' => '-webkit-perspective,-moz-perspective',
			'perspective-origin' => '-webkit-perspective-origin,-moz-perspective-origin',
			'transform' =>  '-webkit-transform,-moz-transform,-ms-transform',
			'transform-origin' =>  '-webkit-transform-origin,-moz-transform-origin,-ms-transform-origin',
			'transform-style' =>  '-webkit-transform,-moz-transform',
			
			'flex' => '-webkit-box-flex,-moz-box-flex,-webkit-flex,-ms-flex',
			'order' => '-webkit-box-ordinal-group,-moz-box-ordinal-group,-ms-flex-order,-webkit-order',
			'flex-direction' => '-webkit-flex-direction,-moz-flex-direction',
			'flex-grow' => '-webkit-flex-grow,-moz-flex-grow',
			'flex-wrap' => '-webkit-flex-wrap,-moz-flex-wrap',
			'flex-shrink' => '-webkit-flex-shrink,-moz-flex-shrink',
			'flex-flow' => '-webkit-flex-flow,-moz-flex-flow',
			'flex-basis' => '-webkit-flex-basis,-moz-flex-basis',
			'justify-content' => '-webkit-justify-content,-moz-justify-content',
			'align-self' => '-webkit-align-self',
			'align-items' => '-webkit-align-items',
			'align-content' => '-webkit-align-content',
			
			'columns' => '-webkit-columns,-moz-columns',
			'column-count' => '-webkit-column-count,-moz-column-count',
			'column-gap' => '-webkit-column-gap,-moz-column-gap',
			'column-rule' => '-webkit-column-rule,-moz-column-rule',
			'column-rule-color' => '-webkit-column-rule-color,-moz-column-rule-color',
			'column-rule-style' => '-webkit-column-rule-style,-moz-column-rule-style',
			'column-rule-width' => '-webkit-column-rule-width,-moz-column-rule-width',
			'column-fill' => '-moz-column-fill',
			'column-span' => '-webkit-column-span',
			'column-width' => '-webkit-column-width,-moz-column-width',
			
			'transition' => '-webkit-transition,-moz-transition',
			'transition-delay' => '-webkit-transition-delay,-moz-transition-delay',
			'transition-duration' => '-webkit-transition-duration,-moz-transition-duration',
			'transition-property' => '-webkit-transition-property,-moz-transition-property',
			'transition-timing-function' => '-webkit-transition-timing-function,-moz-transition-timing-function',
			
			'animation' => '-webkit-animation',
			'animation-delay' => '-webkit-animation-delay',
			'animation-direction' => '-webkit-animation-direction',
			'animation-duration' => '-webkit-animation-duration',
			'animation-fill-mode' => '-webkit-animation-fill-mode',
			'animation-iteration-count' => '-webkit-animation-iteration-count',
			'animation-name' => '-webkit-animation-name',
			'animation-play-state' => '-webkit-animation-play-state',
			'animation-timing-function' => '-webkit-animation-timing-function',
			'backface-visibility' => '-webkit-backface-visibility',
			
			'@keyframes' => '@-webkit-keyframes,@-moz-keyframes',
		];
		$prefix_values = [
			'display' => [
				'flex' => '-webkit-box,-moz-box,-ms-flexbox,-webkit-flex,flex',
				'inline-flex' => '-ms-inline-flex,-webkit-inline-flex,inline-flex',
			],
			'word-break' => [
				'break-all' => 'break-word,break-all',
			]
			//'-moz-font-feature-settings' => ['liga' => 'liga=1', 'dlig' => 'dlig=1'],
			//'-webkit-font-feature-settings' => ['liga' => 'liga=1', 'dlig' => 'dlig=1']
		];
		

		// find "property: value" with "{;}" as delimiters
		$regex = "/ (?<=[\\{;]) ([^\\{]+?) : (([^\\{]|\".*?\"|'.*?')+?) (?=[\\};]) /x";
		$content = preg_replace_callback($regex, function(array $m) use ($prefix_properties, $prefix_values) {
			
			$property = trim($m[1]);
			$value = trim($m[2]);
			$props = '';
			
			// valueFunc() adds rules for all possible vendor values
			// for the given (vendor or standard) property based on the given standard value.
			$valueFunc = function($prop,$val) use ($prefix_values) {
				// TODO: $val could be more than one keyword, or with !important modifier...
				$res = '';
				if (isset($prefix_values[$prop]) && isset($prefix_values[$prop][$val])) {
					$vendorvals = explode(',' , $prefix_values[$prop][$val]);
					foreach ($vendorvals as $vendorval)
						$res .= trim($prop) . ':' . trim($vendorval) . ';';
				} else {
					$res .= trim($prop) . ':' . $val . ';';
				}
				return $res;
			};
			
			// add rules for all possible vendor properties based on the standard property
			if (isset($prefix_properties[$property])) {
				$vendorprops = explode(',' , $prefix_properties[$property]);
				foreach ($vendorprops as $vendorprop)
					$props .= $valueFunc(trim($vendorprop), $value);
			}
			
			// re-add original rule
			return $props . $valueFunc($property, $value);			
		} , $content);
		
		// find "@property value {more values with well-balanced brackets}"
		$atrule_regex = "/ (@[^\\s\\{]+) ([^\\{]*) (\\{ ([^\\{\\}] | (?3))* \\}) /x";
		$content =  preg_replace_callback($atrule_regex, function(array $m) use ($prefix_properties) {
			
			$property = $m[1];
			$value = $m[2].$m[3];
			$props = '';
			
			// add rules for all possible vendor atrules based on the standard atrules
			if (isset($prefix_properties[$property])) {
				$vendorprops = explode(',' , $prefix_properties[$property]);
				foreach ($vendorprops as $vendorprop)
					$props .= trim($vendorprop).$value;
			}
			
			// re-add original rule
			return $props . trim($property).$value;
		}, $content);
		
		return $content;
	}
	
	
}