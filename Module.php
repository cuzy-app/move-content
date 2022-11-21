<?php
/**
 * Move Content
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
        return Yii::t('MoveContentModule.config', 'Move Content');
    }

    /**
     * @inerhitdoc
     */
    public function getDescription()
    {
        return Yii::t('MoveContentModule.config', 'Transfer all content from one user to another.');
    }
}
