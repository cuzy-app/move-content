<?php

/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent;

use humhub\helpers\ControllerHelper;
use humhub\modules\admin\permissions\ManageUsers;
use humhub\modules\admin\widgets\UserMenu;
use humhub\modules\ui\menu\MenuLink;
use Throwable;
use Yii;
use yii\base\Event;
use yii\base\InvalidConfigException;

class Events
{
    /**
     * @param Event $event
     * @throws InvalidConfigException|Throwable
     */
    public static function onAdminUserMenuInit($event)
    {
        /** @var UserMenu $menu */
        $menu = $event->sender;

        if (Yii::$app->user->can(ManageUsers::class)) {
            $menu->addEntry(new MenuLink([
                'label' => Yii::t('MoveContentModule.base', 'Move content'),
                'url' => ['/move-content/user/content'],
                'sortOrder' => 2000,
                'isActive' => ControllerHelper::isActivePath('move-content', 'user', 'content'),
                'isVisible' => true,
            ]));
        }

        if (Yii::$app->user->can(ManageUsers::class)) {
            $menu->addEntry(new MenuLink([
                'label' => Yii::t('MoveContentModule.base', 'Move group users'),
                'url' => ['/move-content/user/group'],
                'sortOrder' => 2000,
                'isActive' => ControllerHelper::isActivePath('move-content', 'user', 'group'),
                'isVisible' => true,
            ]));
        }
    }

    /**
     * @param Event $event
     * @throws InvalidConfigException|Throwable
     */
    public static function onAdminSpaceMenuInit($event)
    {
        /** @var UserMenu $menu */
        $menu = $event->sender;

        if (Yii::$app->user->can(ManageUsers::class)) {
            $menu->addEntry(new MenuLink([
                'label' => Yii::t('MoveContentModule.base', 'Move content'),
                'url' => ['/move-content/space/content'],
                'sortOrder' => 2000,
                'isActive' => ControllerHelper::isActivePath('move-content', 'space', 'content'),
                'isVisible' => true,
            ]));
        }
    }
}
