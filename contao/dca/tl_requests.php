<?php

declare(strict_types=1);

/*
 * This file is part of Fooladgharb Bundle.
 *
 * (c) Hamid Peywasti
 *
 * @license MIT
 */

/*
 * Table tl_requests
 */

use Contao\DataContainer;
use Contao\DC_Table;

$GLOBALS['TL_DCA']['tl_requests'] = [
    // Config
    'config' => [
        'dataContainer' => DC_Table::class,
        'closed' => true,
        // 'notEditable' => true,
        'notCopyable' => true,
        'sql' => [
            'keys' => [
                'id' => 'primary',
            ],
        ],
    ],

    // List
    'list' => [
        'sorting' => [
            'mode' => DataContainer::MODE_SORTABLE,
            'fields' => ['tstamp'],
            'flag' => DataContainer::SORT_DESC,
            'panelLayout' => 'filter;sort,search,limit',
        ],
        'label' => [
            'fields' => ['tstamp', 'name', 'province', 'phone', 'date', 'product', 'area', 'lead_id'],
            'showColumns' => true,
            // 'label_callback' => ['tl_log', 'colorize')
        ],
        'global_operations' => [
            'all' => [
                'href' => 'act=select',
                'tl_class' => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset();" accesskey="e"',
            ],
            'export_csv' => [
                'href' => 'key=export_csv',
                'icon' => 'bundles/contaofooladgharb/icons/export-csv.svg',
                'primary' => true,
                'attributes' => 'title="'.($GLOBALS['TL_LANG']['tl_requests']['export_csv'][1] ?? 'Export all requests as a CSV file').'"',
            ],
            'export_excel' => [
                'href' => 'key=export_excel',
                'icon' => 'bundles/contaofooladgharb/icons/export-excel.svg',
                'primary' => true,
                'attributes' => 'title="'.($GLOBALS['TL_LANG']['tl_requests']['export_excel'][1] ?? 'Export all requests as an Excel file').'"',
            ],
        ],
        'operations' => [
            'edit',
            'delete',
            'show' => [
                'href' => 'act=show',
                'icon' => 'show.svg',
                'primary' => true,
            ],
        ],
    ],

    // Palettes
    'palettes' => [
        'default' => '{name_legend},name,phone,email;{product_legend},product,type,usage,insulation,area;{message_legend},message;{datetime_legend},date,time,url;',
    ],

    // Fields
    'fields' => [
        'id' => [
            'sql' => 'int(10) unsigned NOT NULL auto_increment',
        ],
        'tstamp' => [
            'filter' => true,
            'sorting' => true,
            'flag' => DataContainer::SORT_DAY_DESC,
            'sql' => 'int(10) unsigned NOT NULL default 0',
        ],
        'name' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => 'varchar(255) NOT NULL',
        ],
        'province' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => 'varchar(255) NOT NULL',
        ],
        'phone' => [
            'inputType' => 'text',
            'eval' => ['maxlength' => 255, 'tl_class' => 'w50'],
            'sql' => 'varchar(255) NOT NULL',
        ],
        'email' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'varchar(255) DEFAULT NULL',
        ],
        'message' => [
            'inputType' => 'textarea',
            'eval' => ['tl_class' => 'clr'],
            'sql' => 'text NOT NULL',
        ],
        'product' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'varchar(255) DEFAULT NULL',
        ],
        'type' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'varchar(255) DEFAULT NULL',
        ],
        'usage' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'varchar(255) DEFAULT NULL',
        ],
        'insulation' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'varchar(255) DEFAULT NULL',
        ],
        'area' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'varchar(255) DEFAULT NULL',
        ],
        'delivery' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'varchar(50) DEFAULT NULL',
        ],
        'date' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'varchar(50) NOT NULL',
        ],
        'time' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'varchar(5) DEFAULT NULL',
        ],
        'url' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50'],
            'sql' => 'varchar(255) NOT NULL',
        ],
        'send_status' => [
            'inputType' => 'checkbox',
            'eval' => ['tl_class' => 'w50', 'disabled' => true], // Disabled to prevent manual editing
            'sql' => 'tinyint(1) unsigned NOT NULL default 0',
        ],
        'lead_id' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50', 'disabled' => true],
            'sql' => 'varchar(10) DEFAULT NULL',
        ],
        'client_id' => [
            'inputType' => 'text',
            'eval' => ['tl_class' => 'w50', 'disabled' => true],
            'sql' => 'varchar(10) DEFAULT NULL',
        ],
    ],
];
