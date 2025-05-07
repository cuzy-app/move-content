<?php

/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\jobs;

use humhub\modules\queue\LongRunningActiveJob;
use humhub\modules\space\models\Membership;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\User;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\IntegrityException;
use yii\db\StaleObjectException;

class MoveUsersJob extends LongRunningActiveJob
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
     * @var string|null Space guid
     */
    public $sourceSpaceGuid;
    /**
     * @var string|null Space guid
     */
    public $targetSpaceGuid;

    protected int $_nbUsersMoved = 0;
    protected array $_errors = [];

    /**
     * @inheritdoc
     * @throws IntegrityException
     */
    public function run()
    {
        if ($this->sourceGroupId && $this->targetGroupId) {
            $this->moveGroupUsers();
        }

        if ($this->sourceSpaceGuid && $this->targetSpaceGuid) {
            $this->moveSpaceUsers();
        }
    }

    private function moveGroupUsers()
    {
        $sourceGroup = Group::findOne($this->sourceGroupId);
        $targetGroup = Group::findOne($this->targetGroupId);

        if (!$sourceGroup || !$targetGroup) {
            return;
        }

        /** @var User $user */
        foreach ($sourceGroup->getUsers()->each() as $user) {
            if (!$targetGroup->isMember($user)) {
                try {
                    $targetGroup->addUser($user);
                } catch (InvalidConfigException $e) {
                    $this->_errors[] = 'User ID ' . $user->id . ' not added to target group  "' . $targetGroup->name . '":' . $e->getMessage();
                }
            }
            try {
                $sourceGroup->removeUser($user);
            } catch (StaleObjectException|\Throwable $e) {
                $this->_errors[] = 'User ID ' . $user->id . ' not removed from source group "' . $sourceGroup->name . '":' . $e->getMessage();
            }
            $this->_nbUsersMoved++;
        }

        // Log result
        Yii::warning($this->_nbUsersMoved . ' users have been transferred from group "' . $sourceGroup->name . '" to group "' . $targetGroup->name . '"', 'move-content');
        if ($this->_errors) {
            Yii::error('Errors while transferring users from group "' . $sourceGroup->name . '" to group "' . $targetGroup->name . '": ' . PHP_EOL . implode(PHP_EOL, $this->_errors), 'move-content');
        }
    }

    private function moveSpaceUsers()
    {
        $sourceSpace = Space::findOne(['guid' => $this->sourceSpaceGuid]);
        $targetSpace = Space::findOne(['guid' => $this->targetSpaceGuid]);

        if (!$sourceSpace || !$targetSpace) {
            return;
        }

        /** @var Membership $membership */
        foreach ($sourceSpace->getMemberships()->each() as $membership) {
            $userId = $membership->user_id;
            if (!$targetSpace->isMember($userId)) {
                try {
                    $targetSpace->addMember($userId, 1, true, $membership->group_id);
                } catch (InvalidConfigException|\Throwable $e) {
                    $this->_errors[] = 'User ID ' . $userId . ' not added to target space  "' . $targetSpace->displayName . '":' . $e->getMessage();
                }
            }
            try {
                $sourceSpace->removeMember($membership->user_id);
            } catch (InvalidConfigException|\Throwable $e) {
                $this->_errors[] = 'User ID ' . $userId . ' not removed from source space "' . $sourceSpace->displayName . '":' . $e->getMessage();
            }
            $this->_nbUsersMoved++;
        }

        // Log result
        Yii::warning($this->_nbUsersMoved . ' users have been transferred from space "' . $sourceSpace->displayName . '" to space "' . $targetSpace->displayName . '"', 'move-content');
        if ($this->_errors) {
            Yii::error('Errors while transferring users from space "' . $sourceSpace->displayName . '" to space "' . $targetSpace->displayName . '": ' . PHP_EOL . implode(PHP_EOL, $this->_errors), 'move-content');
        }
    }

    /**
     * @inheritDoc
     */
    public function canRetry($attempt, $error)
    {
        $errorMessage = $error ? $error->getMessage() : '';
        Yii::error('Error with users moving job: ' . $errorMessage, 'move-content');
        return false;
    }
}
