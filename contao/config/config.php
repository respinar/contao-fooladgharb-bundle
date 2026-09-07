<?php

declare(strict_types=1);

/*
 * This file is part of Fooladgharb Bundle.
 *
 * (c) Hamid Peywasti
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
