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

    public bool $moveContent = false;

    public bool $moveMembers = false;

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'sourceSpaceGuid' => Yii::t('MoveContentModule.base', 'Source space'),
            'targetSpaceGuid' => Yii::t('MoveContentModule.base', 'Target space'),
            'moveContent' => Yii::t('MoveContentModule.base', 'Move the Space content'),
            'moveMembers' => Yii::t('MoveContentModule.base', 'Move the Space members'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeHints()
    {
        return [
            'moveContent'
                => Yii::t('MoveContentModule.base', 'All content (with comments, likes and files) and topics will be moved to the target space. Modules needed by this content will be enabled on the target space.') . '<br>'
                . Yii::t('MoveContentModule.base', 'Topics with the same name will be merged, and wiki pages having the same title as an existing one will be renamed.') . '<br>'
                . Yii::t('MoveContentModule.base', 'Authors keep their content even if they are not members of the target space.'),
            'moveMembers'
                => Yii::t('MoveContentModule.base', 'All users except the owner will be removed from the source space.') . '<br>'
                . Yii::t('MoveContentModule.base', 'Users missing from the target space will be added with the same role.') . '<br>'
                . Yii::t('MoveContentModule.base', 'If the content is moved without the members, data such as calendar event participations or task assignments may be deleted.') . '<br>'
                . Yii::t('MoveContentModule.base', 'If the members are moved without the content, the content stays in the source space: the removed users may no longer access it if the space is private, and data such as their task assignments in the source space will be deleted.'),
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
            [['moveContent', 'moveMembers'], 'boolean'],
            [['moveContent'], 'validateSomethingToMove'],
        ];
    }

    public function validateSomethingToMove($attribute): void
    {
        if (!$this->moveContent && !$this->moveMembers) {
            $this->addError($attribute, Yii::t('MoveContentModule.base', 'Select at least the content or the members to move.'));
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
        Yii::$app->queue->push(new MoveSpaceContentJob([
            'sourceSpaceGuid' => $this->sourceSpaceGuid,
            'targetSpaceGuid' => $this->targetSpaceGuid,
            'moveContent' => $this->moveContent,
            'moveMembers' => $this->moveMembers,
        ]));
        return true;
    }
}
