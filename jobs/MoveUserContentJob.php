<?php

/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\jobs;

use humhub\modules\activity\models\Activity;
use humhub\modules\analytics\models\AnalyticsReportedContent;
use humhub\modules\categoryGroup\models\CategoryGroup;
use humhub\modules\cfiles\models\File;
use humhub\modules\cfiles\models\Folder;
use humhub\modules\classifiedSpace\models\ClassifiedSpaceCategory;
use humhub\modules\comment\models\Comment;
use humhub\modules\content\components\ContentActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\ecommerce\models\Discount;
use humhub\modules\ecommerce\models\Guest;
use humhub\modules\ecommerce\models\Item;
use humhub\modules\ecommerce\models\PrivateContent;
use humhub\modules\ecommerce\models\Stripe;
use humhub\modules\ecommerce\models\Subscription;
use humhub\modules\ecommerce\models\Transaction;
use humhub\modules\ecommerce\models\Vendor;
use humhub\modules\eventsManager\models\EventSpeaker;
use humhub\modules\helloasso\models\HelloassoForm;
use humhub\modules\helloasso\models\HelloassoItem;
use humhub\modules\helloasso\models\HelloassoPayer;
use humhub\modules\like\models\Like;
use humhub\modules\mass_notification\models\MassNotification;
use humhub\modules\polls\models\PollAnswer;
use humhub\modules\polls\models\PollAnswerUser;
use humhub\modules\questions\models\QuestionAnswer;
use humhub\modules\queue\LongRunningActiveJob;
use humhub\modules\reaction\models\Reaction;
use humhub\modules\reportcontent\models\ReportContent;
use humhub\modules\show_content\models\ShowContent;
use humhub\modules\spacesMap\models\SpacesMap;
use humhub\modules\survey\models\Field;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\GroupUser;
use humhub\modules\user\models\Invite;
use humhub\modules\user\models\ProfileField;
use humhub\modules\user\models\ProfileFieldCategory;
use humhub\modules\user\models\User;
use humhub\modules\userCleanup\models\UserCleanup;
use humhub\modules\virusScanner\models\VirusFile;
use Yii;

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
     * Tables where created_by must be updated
     */
    protected const NON_CONTENT_CLASSES = [
        Group::class,
        GroupUser::class,
        Invite::class,
        ProfileField::class,
        ProfileFieldCategory::class,
        PollAnswer::class,
        PollAnswerUser::class,
        QuestionAnswer::class,
        ReportContent::class,
        VirusFile::class,
        AnalyticsReportedContent::class,
        CategoryGroup::class,
        ClassifiedSpaceCategory::class,
        Discount::class,
        Guest::class,
        Item::class,
        PrivateContent::class,
        Stripe::class,
        Subscription::class,
        Transaction::class,
        Vendor::class,
        HelloassoForm::class,
        HelloassoItem::class,
        HelloassoPayer::class,
        MassNotification::class,
        ShowContent::class,
        Field::class,
        UserCleanup::class,
        SpacesMap::class,

        // Content Active Records
        Like::class,
        Comment::class,
        Answer::class,
        Reaction::class,
        EventSpeaker::class,
    ];

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
        $this->moveNonContentActiveRecords($sourceUser, $targetUser);
        $this->moveFiles($sourceUser, $targetUser);
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
            $this->_model = $this->_content->getModel();
            if (
                !$this->_model
                || $this->_model instanceof Activity
            ) {
                continue;
            }

            // Change creator
            $this->_content->created_by = $targetUser->id;
            if ($this->_content->updateAttributes(['created_by'])) { // Don't replace with ->save() to avoid updated_at and stream_sort_date to be updated
                $this->_nbContentMoved++;

                if (property_exists($this->_model, 'created_by')) {
                    $this->_model->created_by = $targetUser->id;
                    $scenarios = $this->_model->scenarios();
                    $scenario = $this->_model->getScenario();
                    if (isset($scenarios[$scenario])) {
                        $this->_model->save();
                    }
                }
            }

            // The content is in the user profile
            if (
                $this->_content->container instanceof User
                && $this->moveProfileContent
                && !$this->_model instanceof File
                && !$this->_model instanceof Folder
                && $this->_content->container->moduleManager->isEnabled($this->_model->getModuleId())
            ) {
                // Move to new user container
                try {
                    $this->_content->move($targetUser, true);
                } catch (\Throwable $e) {
                    $this->_errors[] = 'Error while moving content ID ' . $this->_content->id . ': ' . $e->getMessage();
                }
            }
        }

        // Log result
        Yii::warning($this->_nbContentMoved . ' contents of user "' . $sourceUser->username . '" have been transferred to user "' . $targetUser->username . '"', 'move-content');
        if ($this->_errors) {
            Yii::error('Errors while transferring content from user "' . $sourceUser->username . '" to user "' . $targetUser->username . '": ' . implode(' | ', $this->_errors), 'move-content');
        }
    }

    /**
     * @param User $sourceUser
     * @param User $targetUser
     * @return void
     */
    protected function moveNonContentActiveRecords(User $sourceUser, User $targetUser)
    {
        $nbRecordsMoved = 0;
        $errors = [];

        foreach (self::NON_CONTENT_CLASSES as $class) {
            if (
                !class_exists($class)
                || !method_exists($class, 'tableName')
                || !Yii::$app->db->getTableSchema($class::tableName()) // In case the module is installed, but disabled
            ) {
                continue;
            }
            $query = $class::find()->where(['created_by' => $sourceUser->id]);
            foreach ($query->each(1000) as $record) {
                $record->created_by = $targetUser->id;
                if ($record->save()) {
                    $nbRecordsMoved++;
                } else {
                    $this->_errors[] = $class . ' record ID ' . $record->id . ': ' . implode(' ', $record->getErrorSummary(true));
                }
            }
        }

        // Log result
        Yii::warning($nbRecordsMoved . ' records of user "' . $sourceUser->username . '" have been transferred to user "' . $targetUser->username . '"', 'move-content');
        if ($errors) {
            Yii::error('Errors while transferring records from user "' . $sourceUser->username . '" to user "' . $targetUser->username . '": ' . implode(' | ', $errors), 'move-content');
        }
    }

    private function moveFiles(User $sourceUser, User $targetUser)
    {
        $nbFilesMoved = 0;
        $errors = [];

        foreach (\humhub\modules\file\models\File::find()->each(1000) as $file) {
            if ($file->created_by === $sourceUser->id) {
                $file->created_by = $targetUser->id;
                if ($file->save()) {
                    $nbFilesMoved++;
                } else {
                    $this->_errors[] = 'File ID ' . $file->id . ': ' . implode(' ', $file->getErrorSummary(true));
                }
            }
        }

        // Log result
        Yii::warning($nbFilesMoved . ' files have been transferred to user "' . $targetUser->username . '"', 'move-content');
        if ($errors) {
            Yii::error('Errors while transferring files to user "' . $targetUser->username . '": ' . implode(' | ', $errors), 'move-content');
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
