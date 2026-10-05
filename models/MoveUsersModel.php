<?php

/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\models;

use humhub\modules\admin\permissions\ManageSpaces;
use humhub\modules\moveContent\jobs\MoveUsersJob;
use humhub\modules\user\models\Group;
use Yii;
use yii\base\Model;

class MoveUsersModel extends Model
{
    /**
     * @var int|null Group ID
     */
    public $sourceGroupId;

    /**
     * @var int|null Group ID
     */
    public $targetGroupId;

    /**
     * @var string|array|null Space guid
     */
    public $sourceSpaceGuid;

    /**
     * @var string|array|null Space guid
     */
    public $targetSpaceGuid;

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'sourceGroupId' => Yii::t('MoveContentModule.base', 'Source group'),
            'targetGroupId' => Yii::t('MoveContentModule.base', 'Target group'),
            'sourceSpaceGuid' => Yii::t('MoveContentModule.base', 'Source space'),
            'targetSpaceGuid' => Yii::t('MoveContentModule.base', 'Target space'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['sourceGroupId', 'targetGroupId'], 'integer'],
            [['sourceGroupId', 'targetGroupId'], 'validateGroup'],
            [['sourceSpaceGuid', 'targetSpaceGuid'], 'safe'],
            [['sourceSpaceGuid', 'targetSpaceGuid'], 'validateSpace'],
        ];
    }

    public function validateGroup($attribute): void
    {
        $group = Group::findOne($this->$attribute);
        if ($group === null || !$group->canManage()) {
            $this->addError($attribute, Yii::t('MoveContentModule.base', 'Invalid group'));
        }
    }

    public function validateSpace($attribute): void
    {
        if (!Yii::$app->user->can(ManageSpaces::class)) {
            $this->addError($attribute, Yii::t('MoveContentModule.base', 'Invalid space'));
        }
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
        Yii::$app->queue->push(new MoveUsersJob([
            'sourceGroupId' => $this->sourceGroupId,
            'targetGroupId' => $this->targetGroupId,
            'sourceSpaceGuid' => $this->sourceSpaceGuid,
            'targetSpaceGuid' => $this->targetSpaceGuid,
        ]));
        return true;
    }
}
