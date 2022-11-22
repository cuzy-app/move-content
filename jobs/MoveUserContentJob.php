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
use humhub\modules\content\components\ContentActiveRecord;
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
     * @var Content
     */
    protected $_content;

    /**
     * @var Content
     */
    protected $_model;

    /**
     * @var ContentActiveRecord
     */
    protected $_nbContentMoved = 0;

    /**
     * @var array
     */
    protected $_errors = [];

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

        /** @var Content $content */
        foreach ($contentQuery->each() as $content) {

            // Get and check Content and Model
            $this->_content = $content;
            try {
                $this->_model = $this->_content->getModel();
            } catch (IntegrityException $e) {
                echo '111';
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
                    $this->changeContentOwner($targetUser);
                }
            } // The content is global or in a space
            else {
                $this->changeContentOwner($targetUser);
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
    protected function changeContentOwner(User $targetUser)
    {
        $this->_content->created_by = $targetUser->id;

        if ($this->_content->save()) {
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
