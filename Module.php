<?php

/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent;

use Yii;
use yii\helpers\Url;

class Module extends \humhub\components\Module
{
    /**
     * @var string defines the icon
     */
    public $icon = 'arrows-h';


    /**
     * @inerhitdoc
     */
    public function getName()
    {
        return Yii::t('MoveContentModule.base', 'Move content and users');
    }

    /**
     * @inerhitdoc
     */
    public function getDescription()
    {
        return Yii::t('MoveContentModule.base', 'Transfer Content, Comments and Likes from one User or Space to another, and Users from one Group or Space to another.');
    }

    /**
     * @inheritdoc
     */
    public function getConfigUrl()
    {
        return Url::to(['/move-content/config']);
    }
}
