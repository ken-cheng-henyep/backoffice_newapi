<?php
$config = [
    'SimpleRbac' => [
        'roles' => [
            'operator', // Normal admin user
            'manager',// Normal admin user plus access to approval functions
            'admin', // Super admin user
        ],
        'actionMap' => [
            //Controller => action
            'RemittanceBatch' => [
                'edit' => ['admin'],
                'delete' => ['admin'],
                'index' => ['*'],
                'search' => ['operator', 'manager', 'admin'],
                'jsonList' => ['*'],
                'view' => ['operator', 'manager', 'admin'],
                'viewJSON' => ['operator', 'manager', 'admin'],
                'targetJSON' => ['operator', 'manager', 'admin'],
                'apiLogJson' => ['operator', 'manager', 'admin'],
                'upload' => ['*'],
                'handle_form' => ['*'],
                'serveStaticFile' => ['*'],
                'downloadReport' => ['*'],
                'downloadExcel' => ['*'],
                'updateStatus' => ['operator', 'manager', 'admin'],
                'updateLogStatus' => ['operator', 'manager', 'admin'],
                '*' => ['*'],
            ],
            'RemittanceFilter'=>[
                '*' => ['*'],
            ],
            'TransactionLog' => [
                '*' => ['*'],
            ],
            'CivsReport' => [
                '*' => ['*'],
            ],
            'ChinaGPay' => [
                '*' => ['*'],
            ],
            'Users' => [
                'update' => ['*'],
                '*' => ['admin'],
            ],
            'Pages' => [
                '*' => ['operator', 'manager', 'admin'],
            ],
            'MerchantArticle' => [
                '*' => ['*'],
            ],
            'Merchants' => [
                '*' => ['*'],
            ],
            'MerchantTransaction' => [
                '*' => ['*'],
            ],
            'Reconciliation' => [
                '*' => ['*'],
            ],
            'QueueJob' => [
                '*' => ['*'],
            ],
            'SettlmentTransaction'=> [
                '*' => ['*'],
            ],
            'Holidays' => [
                '*' => ['*'],
            ],
            'SettlementRate'=> [
                'add' => ['admin'],
                'delete' => ['admin'],
                '*' => ['*'],
            ],
            'SettlmentProcess' => [
                '*' => ['*'],
            ],
        ]
    ]
];
