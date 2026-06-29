<?php

declare(strict_types=1);

namespace Respinar\ContaoFooladgharbBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Input;
use Contao\Module;
use Contao\NewsModel;

#[AsHook('generateBreadcrumb')]
class BlogBreadcrumbListener
{
    public function __invoke(array $items, Module $module): array
    {
        // Check if there are any breadcrumb items
        if (empty($items)) {
            return $items;
        }

        // Check if the current page is a news reader page
        $newsAlias = Input::get('items') ?: Input::get('auto_item');
        if ($newsAlias) {
            $news = NewsModel::findByAlias($newsAlias);
            if ($news) {
                // Replace the last item's title with the news post title
                $lastItemKey = array_key_last($items);
                $items[$lastItemKey]['title'] = $news->headline;
                $items[$lastItemKey]['link'] = $news->headline;
            }
        }

        return $items;
    }
}
