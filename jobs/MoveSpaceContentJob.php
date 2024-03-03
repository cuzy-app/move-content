<?php
/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\jobs;

use humhub\modules\cfiles\models\Folder;
use humhub\modules\content\models\Content;
use humhub\modules\content\models\ContentTagRelation;
use humhub\modules\queue\LongRunningActiveJob;
use humhub\modules\search\libs\SearchHelper;
use humhub\modules\space\models\Membership;
use humhub\modules\space\models\Space;
use humhub\modules\topic\models\Topic;
use humhub\modules\wiki\models\WikiPage;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\IntegrityException;
use yii\helpers\StringHelper;


class MoveSpaceContentJob extends LongRunningActiveJob
{
    protected const QUERY_IN_CLAUSE_LIMIT = 100;

    /**
     * @var string Space guid
     */
    public $sourceSpaceGuid;
    /**
     * @var string Space guid
     */
    public $targetSpaceGuid;
    public bool $moveUsers = false;
    protected int $_nbContentMoved = 0;
    protected array $_errors = [];

    /**
     * @inheritdoc
     * @throws IntegrityException
     */
    public function run()
    {
        $sourceSpace = Space::findOne(['guid' => $this->sourceSpaceGuid]);
        $targetSpace = Space::findOne(['guid' => $this->targetSpaceGuid]);

        if (!$sourceSpace || !$targetSpace) {
            return;
        }

        if ($this->moveUsers) {
            $this->addUsers($sourceSpace, $targetSpace);
        }
        $this->moveContent($sourceSpace, $targetSpace);
        if ($this->moveUsers) {
            $this->removeUsers($sourceSpace);
        }

        // Log result
        Yii::warning($this->_nbContentMoved . ' contents of space "' . $sourceSpace->displayName . '" have been transferred to space "' . $targetSpace->displayName . '"', 'move-content');
        if ($this->_errors) {
            Yii::error('Errors while transferring content from space "' . $sourceSpace->displayName . '" to space "' . $targetSpace->displayName . '": ' . PHP_EOL . implode(PHP_EOL, $this->_errors), 'move-content');
        }
    }

    protected function addUsers(Space $sourceSpace, Space $targetSpace)
    {
        /** @var Membership $membership */
        foreach ($sourceSpace->getMemberships()->each() as $membership) {
            $userId = $membership->user_id;
            if ($targetSpace->isMember($userId)) {
                continue;
            }
            try {
                $targetSpace->addMember($userId, 1, true, $membership->group_id);
            } catch (InvalidConfigException|\Throwable $e) {
                $this->_errors[] = 'User ID ' . $userId . ' not added to the target space "' . $sourceSpace->displayName . '":' . $e->getMessage();
            }
        }
    }

    /**
     * @param Space $sourceSpace
     * @param Space $targetSpace
     * @return void
     * @throws IntegrityException
     */
    protected function moveContent(Space $sourceSpace, Space $targetSpace)
    {
        $contentIdsToMove = [];

        // Get all movable content
        $moduleIds = [];
        $contentQuery = Content::find()->where(['contentcontainer_id' => $sourceSpace->contentcontainer_id]);
        /** @var Content $content */
        foreach ($contentQuery->each(500) as $content) {
            $model = null;
            try {
                $model = $content->getModel();
            } catch (IntegrityException $e) {
                continue;
            }
            if (!$model) {
                continue;
            }

            // Ignore special cfile folders such as "Root" or "Files from stream" folders
            if (
                $model instanceof Folder
                && $model->type
            ) {
                continue;
            }

            // Try enabling the module of the content on the target space
            $moduleId = $model->getModuleId();
            if ($moduleId && !in_array($moduleId, $moduleIds, true)) {
                $moduleIds[] = $moduleId;
                $errorMsg = 'Could not enable module ID ' . $moduleId . ' on the target space "' . $sourceSpace->displayName . '"';
                try {
                    if (
                        !$targetSpace->moduleManager->isEnabled($moduleId)
                        && !$targetSpace->moduleManager->enable($moduleId)
                    ) {
                        $this->_errors[] = $errorMsg;
                        continue;
                    }
                } catch (Exception $e) {
                    $this->_errors[] = $errorMsg . ': ' . $e->getMessage();
                }
            }

            // Rename Wiki pages having the same title as the one of the target space
            if ($model instanceof WikiPage) {
                $sameTitleExists = WikiPage::find()->contentContainer($targetSpace)->andWhere([WikiPage::tableName() . '.title' => $model->title])->exists();
                if ($sameTitleExists) {
                    // Don't replace updateAll with $model->save() because WikiPage::afterSave() crashes in command line because Yii::$app->user->getIdentity()
                    WikiPage::updateAll(
                        ['title' => StringHelper::truncate('Conflict with same page title: ' . $model->title, 250)],
                        ['id' => $model->id]
                    );
                }
            }

            $contentIdsToMove[] = $content->id;
        }

        // Move all content at once to avoid problems with related content such as cfile folders or wiki categories
        foreach (array_chunk($contentIdsToMove, self::QUERY_IN_CLAUSE_LIMIT) as $contentIdsToMoveChunk) {
            Content::updateAll(['contentcontainer_id' => $targetSpace->contentcontainer_id], ['in', 'id', $contentIdsToMoveChunk]);
        }

        // Move topics
        /** @var Topic $sourceTopic */
        $sourceTopics = Topic::findByContainer($sourceSpace)->all();
        /** @var Topic[] $targetTopics */
        $targetTopics = Topic::findByContainer($targetSpace)->all();
        // Search for duplicates
        foreach ($sourceTopics as $sourceTopic) {
            /** @var Topic[] $duplicatedTargetTopic */
            $duplicatedTargetTopics = array_filter($targetTopics, static function ($targetTopic) use ($sourceTopic) {
                return
                    $targetTopic->name === $sourceTopic->name
                    && $targetTopic->module_id === $sourceTopic->module_id
                    && $targetTopic->type === $sourceTopic->type ?
                        $targetTopic :
                        null;
            });
            if ($duplicatedTargetTopics) {
                $duplicatedTargetTopic = reset($duplicatedTargetTopics);
                // Attach the target space topic to the content
                ContentTagRelation::updateAll(['tag_id' => $duplicatedTargetTopic->id], ['tag_id' => $sourceTopic->id]);

                // Delete the duplicated source topic
                $sourceTopic->delete();
            }
        }
        Topic::updateAll(['contentcontainer_id' => $targetSpace->contentcontainer_id], ['contentcontainer_id' => $sourceSpace->contentcontainer_id]);

        // Actions after moving
        foreach (array_chunk($contentIdsToMove, self::QUERY_IN_CLAUSE_LIMIT) as $contentIdsToMoveChunk) {
            foreach (Content::findAll(['id' => $contentIdsToMoveChunk]) as $content) {
                $model = $content->getModel();

                // Update search database
                if ($content->getStateService()->isPublished()) {
                    SearchHelper::queueUpdate($model);
                }

                // Execute afterMove actions
                $model->afterMove($targetSpace);

                $this->_nbContentMoved++;
            }
        }
    }

    private function removeUsers(Space $sourceSpace)
    {
        /** @var Membership $membership */
        foreach ($sourceSpace->getMemberships()->each() as $membership) {
            try {
                $sourceSpace->removeMember($membership->user_id);
            } catch (InvalidConfigException|\Throwable $e) {
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function canRetry($attempt, $error)
    {
        $errorMessage = $error ? $error->getMessage() : '';
        Yii::error('Error with space content moving job: ' . $errorMessage, 'move-content');
        return false;
    }
}
