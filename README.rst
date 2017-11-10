
=====================================
JST Assets: Advanced asset management
=====================================

.. default-role:: code


:Project:
      ``TYPO3`` extension ``ext:jst_assets``

:Author:
      `Josua Stabenow <josua.stabenow@gmx.de>`__

:Repository:
      Github `josta/jst_assets <https://github.com/josta/jst_assets>`__

:Tags: TYPO3, Extension, Asset Management, SASS, LESS, CoffeeScript, Icons

**Overview:**

.. contents::
   :local:
   :depth: 2
   :backlinks: none


What does it do?
================

JST Assets enhances Frontend asset handling in TYPO3 in a lot of ways:

**Asset precompiling**

- include ``SCSS``, ``LESS`` and ``CoffeeScript`` files
- use precompiling in the standard ``TYPO3`` pipeline or in ``VHS`` assets
- define/include universal ``SCSS/LESS`` mixins and variables in ``TypoScript``
- additional features: ``CSS autoprefixer`` and ``CoffeeScript include directives``

**Asset snippets**

- attach styles/scripts to page or content records
- use precompiling for snippets
- automatic scoping of ``SCSS/LESS`` rules to the respective record
- add arbitrary CSS classes to page or content records

**Frontend Icon API**

- register new SVG icons and icon folders in PHP or TypoScript
- auto generated icon sprites (SVG symbols or CSS data-URI background-images)
- sprites in external file or inline
- optional IconViewHelper embeds icons in the configured format
- option to only include actually used icons

**Inclusion of common libraries**

- include ``jQuery`` as the very first (header or footer) asset
- ``jQueryAnywhere Hack``: make ``jQuery`` ready registration available even before ``jQuery`` inclusion
- ``Bootstrap 3`` or ``4`` (plus ``popper.js``) from sources: ``SASS``, ``precompiled`` or ``CDN``
- control ``Bootstrap SASS`` through universal ``TypoScript`` variables (see above)

``JST Assets`` is a great tool for integration and creating site packages. However, if you consider using it for regular extension development, there are some tool and guidelines for that, too.
	
Requirements
============

The extension requires

- ``TYPO3 8.7.4`` or higher
- ``ext:fluid_styled_content`` or ``ext:bootstrap_package``
- optionally ``jQuery`` (needed if you use the script wrap feature. jQuery is also shipped with ``JST Assets``)
- optionally ``ext:vhs`` (is extended by ``JST Assets``)

The used precompilers are all native PHP and are shipped with the extension:

- ``coffeescript-php``
- ``scssphp``
- ``less.php``

| ``JST Assets`` may be incompatible to or require ``TypoScript`` tweaking for some extensions.
| In particular, any extension is potentially incompatible that:

- overrides the ``TypoScript`` keys ``lib.page.class``,  ``page.bodyTag``  or  ``page.bodyTagCObject``
- overrides ``fluid_styled_content`` layouts (``"Default.html"``)
- xclasses the ``VHS "AssetService"``
- also precompiles assets
- also includes icons with the CSS class prefix ``icon-``

If you know of any concrete extension that is incompatible, please let me know.

If another extension includes ``jQuery`` or ``Bootstrap``, you obviously have to disable one of the inclusions.

Installation
============

1. Install the extension from TER (or Github)
2. When installing from Github, you may have to run Composer to build the vendor directory.
3. Configure the extension in the Extension Manager


Asset Pipeline Precompilation
=============================

To precompile an asset, add it to the corresponding TypoScript key just as you would do with any other asset. If the file extension is
``'.scss', '.sass', '.less'`` or ``'.coffee'``, the file will be precompiled. Precompiled files will be cached in ``typo3temp/jst_assets/``
and be subject to ``TYPO3`` asset compression and concatenation (if activated).

Autoprefixing
--------------------

By default, the inbuilt ``CSS property autoprefixer`` is not enabled. If you want autoprefixing, you can either
enable it for all assets in the Extension Manager (not recommended) or add a ``TypoScript`` hint when defining the asset:

::

    page.includeCSSLibs {
        my_lib = fileadmin/my_lib.scss
        my_lib.allWrap = autoprefix
    }

or for VHS assets:

::

    plugin.tx_vhs.settings.asset {
        my_lib.path = fileadmin/my_lib.scss
        my_lib.autoprefix = 1
    }

CoffeeScript Includes
---------------------

If you want to include other scripts into your ``CoffeeScript`` files, you can use the following syntax:

::

    ~include 'my/relative/file.coffee'
    ~include '/fileadmin/absolute/file.js'
    ~include 'EXT:myext/Resources/Public/file.coffee'
    ~include 'https://example.org/file.js'

Includes also work recursively in local ``CoffeeScript`` files.



Asset Snippets
==============

This feature helps you define styles and scripts in text fields that have been added to the edit view
of page and content records. The styles are scoped to the respective element, while scripts are by
default wrapped in a ``jQuery`` ready handler to avoid availability or namespace issues.

Snippets allow to selectively change style aspects of particular pages, sections or content elements.
If you want to create a recurring or reusable style, consider writing it into an asset file instead
and only attach a CSS class to the element that is to be styled. There is a new field for that, too.

Asset snippets are collected, precompiled and cached during page generation and then forwarded to
the ``VHS`` asset pipeline, which adds them to the ``HTML head`` as inline ``CSS`` / ``Javascript``.




Frontend Icon API
=================

Registering Icons
-----------------

``JST Assets`` comes with an easy to use Icon API. In your extension, you can simply collect all your
SVG icons in a folder and call the following line in ``ext_localconf.php``:

::

	\Josta\JstAssets\Utility\IconUtility::addIconPath('EXT:myext/Resources/Public/Icons/Frontend');

You can also add icons with TypoScript, but be sure to add the TypoScript to the Page with the Root Template to avoid caching errors:

::

	plugin.tx_jstassets.settings.icons {
		some_icons = fileadmin/icons/
	}
	
It should also be noted that all registered icons share a common namespace, and icons that are registered later will override earlier icons with the same name. E.g. TypoScript defined icons will override icons registered through the API.
 
Choosing an Icon Pipeline
-------------------------

There are different sprite methods between which you can choose, each with its own cons and pros:

- **CSS Background Images**
	- Pros: Widespread support, easy to size (properties ``width/height``)
	- Cons: hard to apply CSS color (property ``filter`` + data URI)
- **CSS Masks**
	- Pros: easy to size and color (properties ``width/height`` and ``background-color``)
	- Cons: not yet supported in IE, Edge and Opera. Currently double the file size.
- **CSS masks/char mix**
	- Pros: can be styled just like icon fonts (properties ``font-size`` and ``color``)
	- Cons: experimental, not yet supported in IE, Edge and Opera. Currently double the file size.
- **SVG symbols**
	- Pros: extensive styling possibilities (properties ``width/height``, ``fill``, and a lot more)
	- Cons: complicated use (see below), good but not perfect support
	
I'd say that ``SVG Symbols`` are the way to go, but they require some getting used to. I also considered and discarded other sprite methods, which all had major drawbacks:

- **TTF/OTF/WOFF icon fonts**
	- Pros: used and styled the familiar way
	- Cons: there's no PHP native SVG to TTF/OTF/WOFF converter
- **SVG icon fonts** 
	- Pros: used and styled the familiar way. There's a PHP native library available.
	- Cons: Browsers are actually dropping support
- **SVG views**
	- Pros: nice HTML markup (like ``<img src="icon-sprite#icon-name" />``)
	- Cons: imperfect browser support. Requires creating a sprite grid, which I may implement later
	

Using Icons
-----------

Depending on how you configured the Icon pipeline, the way to use icons will differ:

+----------------------------+----------------------------------------------------------------------------------------------------------------------------+
| **CSS**                    | ``<span class="icon icon-x more-classes"></span>``                                                                         |
+--------------+-------------+----------------------------------------------------------------------------------------------------------------------------+
| **SVG Symbols (inline)**   | ``<svg class="icon icon-x more-classes"><use xlink:href="#icon-x"></use></svg>``                                           |
+----------------------------+----------------------------------------------------------------------------------------------------------------------------+
| **SVG Symbols (external)** | ``<svg class="icon icon-x more-classes"><use xlink:href="/typo3temp/jst_assets/jst_assets_icons.svg#icon-x"></use></svg>`` |
+----------------------------+----------------------------------------------------------------------------------------------------------------------------+

There is an IconViewHelper that you can use which will always output the correct code depending on your configuration:

::

	{namespace assets=Josta\JstAssets\ViewHelpers}
	
	<assets:icon name="x" classes="more-classes" />
	

Styling Icons
-------------

``JST Assets`` will automatically output some CSS classes that you can use to style any icon:

+---------------------------+---------------------------------------------------------------+
| **different icon sizes**  | ``icons-xs, icons-s, icons-m, icons-l, icons-xl, icons-xxl``  |
+---------------------------+---------------------------------------------------------------+
| **icon colors**           | ``icons-white, icons-black``                                  |
+---------------------------+---------------------------------------------------------------+

If you're using the ``SASS`` compiler of ``JST Assets``, you also have access to a mixin which you can use to apply icon colors the correct way:

::

	.icon {
		@include icon-color(#ff0);
		&:hover {
			@include icon-color(rgba(200,100,50,0.5));
		}
	}

Note that for all modes that don't use fonts or masks, the icon files themselves can have colors (even different ones within one icon).
With inline SVG icons, you additionally have the possiblity to style any aspect of any icon with CSS (fill, stroke, different parts...).


Keeping your extension independent
----------------------------------

Maybe you like the ``Icon API``, but you don't want to add another dependency to your extension requirements? You can implement a graceful fallback by creating a small wrapper icon viewhelper:

::

	use TYPO3\CMS\Core\Utility\GeneralUtility;

	class IconViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper {
		
		protected $escapeOutput = false;
		
		/**
		 * @param string name
		 * @param string classes
		 * @return string
		 */
		public function render($name, $classes='') {
			if (class_exists($vh = 'Josta\\JstAssets\\ViewHelpers\\IconViewHelper', true))
				return $this->objectManager->get($vh)->forwardRender($name, $classes);
			$file = GeneralUtility::getFileAbsFileName('EXT:myext/Resources/Public/Icons/Frontend/'.$name.'.svg');
			return preg_replace('/^.*<svg/s', '<svg class="icon icon-'.$name.' '.$classes.'"', file_get_contents($file));
		}
	}
	
In your templates, you now can use the wrapper viewhelper instead of the one provided by this extension. If ``JST Assets`` is not installed, the wrapper will simply output the SVG file directly.

::

	{namespace myext=MyVendorName\MyExt\ViewHelpers}
	
	<myext:icon name="x" classes="more-classes" />

You will also have to wrap the icon registration in ``ext_localconf.php`` in a condition:

::

	if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('jst_assets')) {
		\Josta\JstAssets\Utility\IconUtility::addIconPath('EXT:myext/Resources/Public/Icons/Frontend');
	}
	
If you use SASS and want to use the ``icon-color`` mixin without depending on ``JST Assets``, you can include the following fallback at the beginning of your SCSS:

::

	@if not (mixin-exists('icon-color')) {
		@mixin icon-color($color) {&.icon,.icon{fill: $color;}}
	}

Library Inclusion
=================

``JST Assets`` allows you to include the common frontend libraries ``jQuery`` and ``Twitter Bootstrap``.
This may sound out of scope, but actually makes sense for several reasons:

- ``JST Assets`` requires ``jQuery`` for snippet encapsulation (see above)
- in TYPO3, including ``jQuery`` "the right way" is harder than it sounds
- ``Bootstrap`` is *the* paragon of a library that one would want to configure and compile
- ``Bootstrap`` mixins and variables (especially breakpoints) may be of interest as being universally available

jQuery is included with a special hook that allows it to always be the last library included in the asset
``TypoScript`` array. This in turn gives the ``forceOnTop`` setting the highest precedence, effectively making
jQuery the very first library to be included at the end of the ``HTML body`` (or in the ``head``, if so configured).

Additionally ``JST Assets`` offers to include a small hack ("``JQueryAnywhere``") that collects ``jQuery ready event``
registrations anywhere on the ``HTML`` page and forwards them to ``jQuery`` as soon as it has loaded.


TypoScript Configuration
========================

All configuration either happens in the ``Extension Manager`` or in the ``TypoScript`` setup key
**plugin.tx_jstassets.settings**.


``plugin.tx_jstassets.settings``
--------------------------------

+--------------+---------------------------------------------+
| **less**     | configuration for the ``LESS`` precompiler  |
+--------------+---------------------------------------------+
| **scss**     | configuration for the ``SCSS`` precompiler  |
+--------------+---------------------------------------------+
| **snippets** | configuration for the snippets feature      |
+--------------+---------------------------------------------+
| **icons**    | registration key for new icons              |
+--------------+---------------------------------------------+


``less.variables``
~~~~~~~~~~~~~~~~~~
    Array of **LESS variables** to be included before precompiling any ``LESS`` content

    ::

        plugin.tx_jstassets.settings.less.variables {
            some_color = rgba(0,50,0,0.5)
            other_color = lighten(@some_color, 20%)
        }


``less.includes``
~~~~~~~~~~~~~~~~~
    Array of **LESS files** to be included before precompiling any ``LESS`` content.

    You can define dependencies for any included file to enforce an order of inclusion.
    Included files are included as reference only, meaning they won't output any CSS,
    but any mixins or variables defined within will be available.

    ::

        plugin.tx_jstassets.settings.less.includes {
            some_mixin_file {
                path = EXT:myext/Resources/Public/mixins.less
            }
            other_file {
                path = fileadmin/more_definitions.less
                dependencies = some_mixin_file
            }
        }

``scss.variables``
~~~~~~~~~~~~~~~~~~
    Array of **SCSS variables** to be included before precompiling any ``SASS`` content.
    Works like ``less.variables``. Of course, references have to be to ``SASS`` functions and variables instead.


``scss.includes``
~~~~~~~~~~~~~~~~~
    Array of **SCSS files or partials** to be included before precompiling any ``SASS`` content.
    Works like ``less.includes``.


``snippets.cache_lifetime``
~~~~~~~~~~~~~~~~~~~~~~~~~~~
    **Validity period of snippet cache entries (in seconds).**
    The snippet cache will also be cleared if you save a pages/content record or if you use the "Clear Frontend Cache" button

``snippets.recursive``
~~~~~~~~~~~~~~~~~~~~~~
    Number of child page levels that will also have their page snippets included in the current page.
    Useful for onepage designs in which child pages are included as sections of the parent page. (see my other extension ``jst_onepage``)

``icons``
~~~~~~~~~
    Array of icon folders. Only SVG icons will be processed.

    ::

        plugin.tx_jstassets.settings.icons {
            some_icon_collection = fileadmin/icons/
        }

``lib.page.class``
------------------

The TS key ``lib.page.class`` is a ``COA`` that is rendered by ``JST Assets`` to include ``CSS`` classes in the ``HTML body`` tag.
Add your own classes if you want to.



Further Considerations
======================

A lot of the functionality provided by this extension depends on the included precompiler PHP libraries.
Those libraries may not be 100% compatible with the corresponding ``Node.js`` modules. I will try to always
include up-to-date versions. If I miss one, please give me a hint. The used CoffeScript library unfortunately appears to not be
maintained any longer, so new language features beyond CoffeScript 1.3.1 probably won't ever be supported by this extension.

Also, the important ``Node.js`` tools ``coffeescript-concat`` and ``autoprefixer`` have so far not
been ported to native PHP. For those, ``JST Assets`` offers rudimentary replacements of my own making that in no way
come close to the originals. If you know a better replacement, do tell me.

In the future, given some spare time and some feedback signalling interest, I may include an option
to use original ``Node.js`` precompilers and tools using PHP ``proc_open`` calls.
