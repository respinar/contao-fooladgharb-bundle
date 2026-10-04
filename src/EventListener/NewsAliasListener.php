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

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Slug\Slug;
use Contao\Database;
use Contao\DataContainer;
use Contao\NewsArchiveModel;

#[AsCallback(table: 'tl_news', target: 'fields.alias.save')]
class NewsAliasListener
{
    public function __construct(
        private readonly Slug $slug,
    ) {
    }

    public function __invoke(?string $value, DataContainer $dc): string
    {
        if (!$dc->activeRecord) {
            return (string) $value;
        }

        $currentId = (int) $dc->id;
        $archiveId = (int) $dc->activeRecord->pid;

        $db = Database::getInstance();
        $archive = NewsArchiveModel::findById($archiveId);
        $jumpTo = $archive ? (int) $archive->jumpTo : 0;

        // Archives that are displayed by the same reader page.
        if ($jumpTo > 0) {
            $archiveIds = $db
                ->prepare('SELECT id FROM tl_news_archive WHERE jumpTo=?')
                ->execute($jumpTo)
                ->fetchEach('id');
        } else {
            $archiveIds = [$archiveId];
        }

        $archiveIds = array_map('intval', $archiveIds);
        $placeholders = implode(',', array_fill(0, count($archiveIds), '?'));

        $aliasExists = static function (string $alias) use (
            $db,
            $currentId,
            $archiveIds,
            $placeholders,
        ): bool {
            return $db
                ->prepare(
                    "SELECT id
                     FROM tl_news
                     WHERE alias=?
                       AND id!=?
                       AND pid IN ($placeholders)"
                )
                ->execute($alias, $currentId, ...$archiveIds)
                ->numRows > 0;
        };

        // Generate an alias automatically when the field is empty.
        if ($value === null || $value === '') {
            return $this->slug->generate(
                (string) $dc->activeRecord->headline,
                $jumpTo ?: [],
                $aliasExists,
            );
        }

        // Prevent duplicate aliases within the same reader-page scope.
        if ($aliasExists($value)) {
            throw new \Exception(
                sprintf(
                    $GLOBALS['TL_LANG']['ERR']['aliasExists'],
                    $value,
                )
            );
        }

        return $value;
    }
}
