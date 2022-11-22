<?php
/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent;

use Yii;

class Module extends \humhub\components\Module
{

    /**
     * @var string defines the icon
     */
    public $icon = 'arrows-h';

    /**
     * @var string defines path for resources, including the screenshots path for the marketplace
     */
    public $resourcesPath = 'resources';


    /**
     * @inerhitdoc
     */
    public function getName()
    {
        return Yii::t('MoveContentModule.base', 'Move content');
    }

    /**
     * @inerhitdoc
     */
    public function getDescription()
    {
        return Yii::t('MoveContentModule.base', 'Transfer the content from a user to another');
    }
}
