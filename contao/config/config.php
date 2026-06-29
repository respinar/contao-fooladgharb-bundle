<?php

/*
 * This file is part of Fooladgharb Bundle.
 *
 * (c) Hamid Peywasti 2024 <hamid@respinar.com>
 *
 * @license MIT
 */

$GLOBALS['BE_MOD']['fooladgharb']['requests']= [
		'tables' => ['tl_requests'],
		'export_csv' => [
        Respinar\FooladgharbRequestBundle\Controller\Backend\RequestExportController::class,
        'exportCsv',
    ],
    'export_excel' => [
        Respinar\FooladgharbRequestBundle\Controller\Backend\RequestExportController::class,
        'exportExcel',
    ],
];

