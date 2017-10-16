<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'JST Assets',
    'description' => 'SCSS/LESS/CoffeeScript compilers for the TYPO3 and VHS asset pipelines. Add asset snippets to any page or content record.',
    'category' => 'misc',
    'author' => 'Josua Stabenow',
    'author_email' => 'josua.stabenow@gmx.de',
    'author_company' => 'private',
    'shy' => '',
    'dependencies' => '',
    'conflicts' => '',
    'priority' => '',
    'module' => '',
    'state' => 'stable',
    'internal' => '',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 1,
    'lockType' => '',
    'version' => '1.0',
    'constraints' => [
        'depends' => ['typo3' => '8.7.4-8.7.99'],
        'conflicts' => [],
        'suggests' => [
			'bootstrap_package' => '',
			'fluid_styled_content' => '',
			'flux' => '', # suggested so asset tab is also added to flux content
			'vhs' => '',
		],
    ],
];
