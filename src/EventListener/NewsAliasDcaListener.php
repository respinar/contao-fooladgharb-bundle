<?php

declare(strict_types=1);

/*
 * This file is part of Contao Fooladgharb Bundle.
 *
 * (c) Hamid Peywasti
 *
 * @license MIT
 */

namespace Respinar\ContaoFooladgharbBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;

/**
 * Replaces the news bundle's global alias validation with the reader-page
 * scoped validation from NewsAliasListener.
 *
 * Runs at priority 0, i.e. before the core tagged-callback listener
 * (priority -16) appends the NewsAliasListener callback to the DCA.
 */
#[AsHook('loadDataContainer')]
class NewsAliasDcaListener
{
    public function __invoke(string $table): void
    {
        if ('tl_news' !== $table || !isset($GLOBALS['TL_DCA']['tl_news']['fields']['alias'])) {
            return;
        }

        // tl_news::generateAlias rejects duplicate aliases across ALL news
        // items and generates globally unique slugs, while eval.unique is
        // additionally checked against the whole table in DC_Table::save().
        // Both would prevent the same alias in archives that use different
        // reader pages.
        $GLOBALS['TL_DCA']['tl_news']['fields']['alias']['eval']['unique'] = false;
        $GLOBALS['TL_DCA']['tl_news']['fields']['alias']['save_callback'] = [];
    }
}
