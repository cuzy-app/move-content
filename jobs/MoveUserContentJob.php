<?php
/**
 * Move Content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\jobs;

use humhub\modules\content\models\Content;
use humhub\modules\queue\ActiveJob;
use humhub\modules\user\models\User;
use Yii;
use yii\db\IntegrityException;
use yii\queue\RetryableJobInterface;


class MoveUserContentJob extends ActiveJob implements RetryableJobInterface
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
     * @inhertidoc
     * @var int maximum 1 hour
     */
    private $maxExecutionTime = 60 * 60;

    /**
     * @inheritdoc
     */
    public function run()
    {
        $sourceUser = User::findOne(['guid' => $this->sourceUserGuid]);
        $targetUser = User::findOne(['guid' => $this->targetUserGuid]);

        if (!$sourceUser || !$targetUser) {
            return;
        }

        $contentQuery = Content::find()
            ->where(['created_by' => $sourceUser->id])
            ->orWhere(['contentcontainer_id' => $sourceUser->contentcontainer_id]);

        $nbContentMoved = 0;
        $errors = [];
        /** @var Content $content */
        foreach ($contentQuery->each() as $content) {
            $content->created_by = $targetUser->id;
            if ($content->container instanceof User) {
                if (!$this->moveProfileContent) {
                    continue;
                }
                $content->contentcontainer_id = $targetUser->contentcontainer_id;
            }
            if ($content->save()) {
                $nbContentMoved++;
                try {
                    $model = $content->getPolymorphicRelation();
                    if (!empty($model->created_by)) {
                        $model->created_by = $targetUser->id;
                        $model->save();
                    }
                } catch (IntegrityException $e) {
                }
            } else {
                $errors[] = implode(' ', $model->getErrorSummary(true));
            }
        }

        Yii::warning($nbContentMoved . ' contents of user "' . $sourceUser->username . '" have been transferred to user "' . $targetUser->username . '"', 'move-content');
        if ($errors) {
            Yii::error('Errors while transferring content from user "' . $sourceUser->username . '" to user "' . $targetUser->username . '": ' . implode(' | ', $errors), 'move-content');
        }
    }

    /**
     * @inheritDoc
     */
    public function getTtr()
    {
        return $this->maxExecutionTime;
    }

    /**
     * @inheritDoc for RetryableJobInterface
     */
    public function canRetry($attempt, $error)
    {
        return true;
    }
}
