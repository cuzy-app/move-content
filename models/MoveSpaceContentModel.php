<?php
/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\models;

use humhub\modules\moveContent\jobs\MoveSpaceContentJob;
use Yii;
use yii\base\Model;

class MoveSpaceContentModel extends Model
{
    /**
     * @var string|array|null Space guid
     */
    public $sourceSpaceGuid;

    /**
     * @var string|array|null Space guid
     */
    public $targetSpaceGuid;

    public bool $moveUsers = false;

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'sourceSpaceGuid' => Yii::t('MoveContentModule.base', 'Source space'),
            'targetSpaceGuid' => Yii::t('MoveContentModule.base', 'Target space'),
            'moveUsers' => Yii::t('MoveContentModule.base', 'Move users also'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeHints()
    {
        return [
            'moveUsers' =>
                Yii::t('MoveContentModule.base', 'All users except the owner will be removed from the source space.') . '<br>' .
                Yii::t('MoveContentModule.base', 'Users missing from the target space will be added with the same role.') . '<br>' .
                Yii::t('MoveContentModule.base', 'If unchecked, data such as calendar event participations or task assignments may be deleted.'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['sourceSpaceGuid', 'targetSpaceGuid'], 'required'],
            [['sourceSpaceGuid', 'targetSpaceGuid'], 'safe'],
            [['moveUsers'], 'boolean'],
        ];
    }

    public function beforeValidate()
    {
        if (is_array($this->sourceSpaceGuid)) {
            $this->sourceSpaceGuid = reset($this->sourceSpaceGuid);
        }
        if (is_array($this->targetSpaceGuid)) {
            $this->targetSpaceGuid = reset($this->targetSpaceGuid);
        }
        return parent::beforeValidate();
    }

    /**
     * @return bool
     */
    public function save()
    {
        Yii::$app->queue->push(new MoveSpaceContentJob([
            'sourceSpaceGuid' => $this->sourceSpaceGuid,
            'targetSpaceGuid' => $this->targetSpaceGuid,
            'moveUsers' => $this->moveUsers,
        ]));
        return true;
    }
}
