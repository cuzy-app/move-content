<?php
/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\jobs;

use humhub\modules\activity\models\Activity;
use humhub\modules\cfiles\models\File;
use humhub\modules\cfiles\models\Folder;
use humhub\modules\comment\models\Comment;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\content\models\ContentTag;
use humhub\modules\eventsManager\models\EventSpeaker;
use humhub\modules\like\models\Like;
use humhub\modules\queue\LongRunningActiveJob;
use humhub\modules\reaction\models\Reaction;
use humhub\modules\survey\models\Answer;
use humhub\modules\user\models\User;
use Yii;
use yii\db\IntegrityException;


class MoveUserContentJob extends LongRunningActiveJob
{
    /**
     * @var string User guid
     */
    public $sourceUserGuid;
    /**
     * @var string User guid
     */
    public $targetUserGuid;
    public bool $moveProfileContent = false;
    /**
     * @var Content
     */
    protected $_content;
    /**
     * @var ContentActiveRecord
     */
    protected $_model;
    protected int $_nbContentMoved = 0;
    protected array $_errors = [];

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

        $this->moveContent($sourceUser, $targetUser);
        $this->moveContentAddons($sourceUser, $targetUser);
    }

    /**
     * @param User $sourceUser
     * @param User $targetUser
     * @return void
     */
    protected function moveContent(User $sourceUser, User $targetUser)
    {
        $contentQuery = Content::find()
            ->where(['created_by' => $sourceUser->id])
            ->orWhere(['contentcontainer_id' => $sourceUser->contentcontainer_id]);

        /** @var Content $content */
        foreach ($contentQuery->each(500) as $content) {

            // Get and check Content and Model
            $this->_content = $content;
            $this->_model = null;
            try {
                $this->_model = $this->_content->getModel();
            } catch (IntegrityException $e) {
                continue;
            }
            if (
                !$this->_model
                || $this->_model instanceof Activity
            ) {
                continue;
            }

            // The content is in the user profile
            if ($this->_content->container instanceof User) {
                if (
                    $this->moveProfileContent
                    && !$this->_model instanceof File
                    && !$this->_model instanceof Folder
                    && $this->_content->container->moduleManager->isEnabled($this->_model->getModuleId())
                ) {
                    $this->_content->contentcontainer_id = $targetUser->contentcontainer_id;
                    $this->changeContentOwnerAndSave($targetUser);
                    $this->_model->afterMove($targetUser);
                }
            } // The content is global or in a space
            else {
                $this->changeContentOwnerAndSave($targetUser);
            }
        }

        // Log result
        Yii::warning($this->_nbContentMoved . ' contents of user "' . $sourceUser->username . '" have been transferred to user "' . $targetUser->username . '"', 'move-content');
        if ($this->_errors) {
            Yii::error('Errors while transferring content from user "' . $sourceUser->username . '" to user "' . $targetUser->username . '": ' . implode(' | ', $this->_errors), 'move-content');
        }
    }

    /**
     * @param User $targetUser
     * @return void
     */
    protected function changeContentOwnerAndSave(User $targetUser)
    {
        $this->_content->created_by = $targetUser->id;

        if ($this->_content->save()) {
            ContentTag::deleteContentRelations($this->_content, false);
            $this->_model->populateRelation('content', $this->_content);
            if (isset($this->_model->created_by)) {
                $this->_model->created_by = $targetUser->id;
                $scenarios = $this->_model->scenarios();
                $scenario = $this->_model->getScenario();
                if (isset($scenarios[$scenario])) {
                    $this->_model->save();
                }
            }
            $this->_nbContentMoved++;
        } else {
            $this->_errors[] = 'Content ID ' . $this->_content->id . ': ' . implode(' ', $this->_content->getErrorSummary(true));
        }
    }

    /**
     * @param User $sourceUser
     * @param User $targetUser
     * @return void
     */
    protected function moveContentAddons(User $sourceUser, User $targetUser)
    {
        $nbContentAddonsMoved = 0;
        $errors = [];

        $condition = ['created_by' => $sourceUser->id];
        $contentAddonQueries = [
            Comment::find()->where($condition),
            Like::find()->where($condition),
        ];
        if (class_exists(Answer::class)) {
            $contentAddonQueries[] = Answer::find()->where($condition);
        }
        if (class_exists(Reaction::class)) {
            $contentAddonQueries[] = Reaction::find()->where($condition);
        }
        if (class_exists(EventSpeaker::class)) {
            $contentAddonQueries[] = EventSpeaker::find()->where($condition);
        }

        foreach ($contentAddonQueries as $contentAddonQuery) {
            foreach ($contentAddonQuery->each(1000) as $contentAddon) {
                $contentAddon->created_by = $targetUser->id;
                if ($contentAddon->save()) {
                    $nbContentAddonsMoved++;
                } else {
                    $this->_errors[] = 'Content addon ID ' . $contentAddon->id . ': ' . implode(' ', $contentAddon->getErrorSummary(true));
                }
            }
        }

        // Log result
        Yii::warning($nbContentAddonsMoved . ' content addons of user "' . $sourceUser->username . '" have been transferred to user "' . $targetUser->username . '"', 'move-content');
        if ($errors) {
            Yii::error('Errors while transferring content addons from user "' . $sourceUser->username . '" to user "' . $targetUser->username . '": ' . implode(' | ', $errors), 'move-content');
        }
    }

    /**
     * @inheritDoc
     */
    public function canRetry($attempt, $error)
    {
        $errorMessage = $error ? $error->getMessage() : '';
        Yii::error('Error with user content moving job: ' . $errorMessage, 'move-content');
        return false;
    }
}
