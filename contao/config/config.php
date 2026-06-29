<?php

/*
 * This file is part of Fooladgharb Bundle.
 *
 * (c) Hamid Peywasti 2024 <hamid@respinar.com>
 *
 * @license MIT
 */

use Respinar\ContaoFooladgharbBundle\Controller\Backend\RequestExportController;

$GLOBALS['BE_MOD']['fooladgharb']['requests']= [
		'tables' => ['tl_requests'],
		'export_csv' => [
        RequestExportController::class,
        'exportCsv',
    ],
    'export_excel' => [
        RequestExportController::class,
        'exportExcel',
    ],
];

