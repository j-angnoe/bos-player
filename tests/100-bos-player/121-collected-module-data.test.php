<?php

/**
 * Demonstrate how module dependencies can be handled.
 * 
 */
require_once __DIR__ . '/../includes.php';

autoloadBosPlayer();
autoloadBosExec();

try {
    createFiles([
        'env.json' => json_encode([
            'modules' => [
                ['id' => 'mod1' ],
                ['id' => 'mod2' ]
            ],
        ]),
        'mod1/bosModule.json' => json_encode([
            // Provides a string value
            'provides' => 'mod1-stuff',

            // a single menu item
            'menu' => [
                'title' => 'Mod1 menu'
            ],

            // Anything in data will automatically be 
            // collected with the same heuristics as provides and menu.
            'data' => [
                'some_string' => 'string value from mod1',
                'some_object' => [
                    'title' => 'Object 1 from mod1'
                ]
            ]
        ]),
        'mod2/bosModule.json' => json_encode([
            // Provides an array value
            'provides' => ['mod2-stuff','more-mod2-stuff'],

            // Multiple menu items
            'menu' => [
                [ 'title' => 'Mod2 menu 1'],
                ['title' => 'Mod2 menu 2']
            ],

            'data' => [
                'some_string' => ['string value 1 from mod2',  'string value 2 from mod2'],
                'some_object' => [
                    ['title' => 'Object 1 from mod2'],
                    ['title' => 'Object 2 from mod2'],
                ]
            ]
        ])
    ]);
    
    $environment = getTestEnvironment('env.json');

    // collectModuleData can be run multiple times 
    // safely, without causing clutter.
    $environment->collectModuleData();
    $environment->collectModuleData();
    $environment->collectModuleData();
    $environment->collectModuleData();

    // Provides
    assertTrue(in_array('mod1-stuff', $environment->collectedModuleData['provides']));
    assertTrue(in_array('mod2-stuff', $environment->collectedModuleData['provides']));
    assertTrue(in_array('more-mod2-stuff', $environment->collectedModuleData['provides']));

    // And tracking of who provides certain stuff.
    // This also checks for clutter. 
    assertEquals($environment->whoProvides('mod1-stuff'), ['mod1']);

    // Collection of arbitrary strings from data
    assertTrue(in_array('string value from mod1', $environment->collectedModuleData['some_string']));
    assertTrue(in_array('string value 1 from mod2', $environment->collectedModuleData['some_string']));
    assertTrue(in_array('string value 2 from mod2', $environment->collectedModuleData['some_string']));

    // Collection of objects 
    $someObjectTitles = array_map(function($i) { return $i->title; }, $environment->collectedModuleData['some_object']);

    assertTrue(in_array('Object 1 from mod1', $someObjectTitles));
    assertTrue(in_array('Object 1 from mod2', $someObjectTitles));
    assertTrue(in_array('Object 2 from mod2', $someObjectTitles));    
} catch (Exception $e) {
    fatalException($e);
}