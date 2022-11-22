<?php
/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\models;

use humhub\modules\moveContent\jobs\MoveUserContentJob;
use Yii;
use yii\base\Model;

class MoveUserContentModel extends Model
{
    /**
     * @var string User guid
     */
    public $sourceUserGuid;

    /**
     * @var string User guid
     */
    public $targetUserGuid;

    /**
     * @var bool
     */
    public $moveProfileContent = false;

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'sourceUserGuid' => Yii::t('MoveContentModule.base', 'Source user'),
            'targetUserGuid' => Yii::t('MoveContentModule.base', 'Target user'),
            'moveProfileContent' => Yii::t('MoveContentModule.base', 'Move the content of the user profile'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['sourceUserGuid', 'targetUserGuid'], 'required'],
            [['sourceUserGuid', 'targetUserGuid'], 'safe'],
            [['moveProfileContent'], 'boolean'],
        ];
    }

    /**
     * @return bool
     */
    public function save()
    {
        Yii::$app->queue->push(new MoveUserContentJob([
            'sourceUserGuid' => $this->sourceUserGuid,
            'targetUserGuid' => $this->targetUserGuid,
            'moveProfileContent' => $this->moveProfileContent,
        ]));
        return true;
    }
}