<?php

/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

use humhub\modules\admin\widgets\SpaceMenu;
use humhub\modules\admin\widgets\UserMenu;
use humhub\modules\moveContent\Events;

return [
    'id' => 'move-content',
    'class' => humhub\modules\moveContent\Module::class,
    'namespace' => 'humhub\modules\moveContent',
    'events' => [
        [
            'class' => UserMenu::class,
            'event' => UserMenu::EVENT_INIT,
            'callback' => Events::onAdminUserMenuInit(...),
        ],
        [
            'class' => SpaceMenu::class,
            'event' => SpaceMenu::EVENT_INIT,
            'callback' => Events::onAdminSpaceMenuInit(...),
        ],
    ],
];
