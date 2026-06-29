<?php
return [
    'controllers' => [
        'value' => [
            'namespaces' => [
                '\\Awz\\Lockfieldsup1c\\Api' => 'api'
            ]
        ],
        'readonly' => true
    ],
    'ui.entity-selector' => [
        'value' => [
            'entities' => [
                [
                    'entityId' => 'awzlockfieldsup1c-user',
                    'provider' => [
                        'moduleId' => 'awz.lockfieldsup1c',
                        'className' => '\\Awz\\Lockfieldsup1c\\Access\\EntitySelectors\\User'
                    ],
                ],
                [
                    'entityId' => 'awzlockfieldsup1c-group',
                    'provider' => [
                        'moduleId' => 'awz.lockfieldsup1c',
                        'className' => '\\Awz\\Lockfieldsup1c\\Access\\EntitySelectors\\Group'
                    ],
                ],
            ]
        ],
        'readonly' => true,
    ]
];