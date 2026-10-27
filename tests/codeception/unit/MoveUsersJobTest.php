<?php

/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\tests\codeception\unit;

use humhub\modules\moveContent\jobs\MoveUsersJob;
use humhub\modules\moveContent\tests\codeception\_support\MoveContentTestTrait;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\Group;
use humhub\modules\user\models\GroupUser;
use tests\codeception\_support\HumHubDbTestCase;

/**
 * Fixtures used:
 * - Group 3 "Moderators": User2 (manager), UnapprovedUser
 * - Group 4 "Editors": empty
 * - Space 3 (owner Admin): Admin (admin), User1 (member), User2 (moderator)
 * - Space 5 (owner Admin): Admin (admin), User1 (member)
 */
class MoveUsersJobTest extends HumHubDbTestCase
{
    use MoveContentTestTrait;

    private const SOURCE_GROUP_ID = 3;
    private const TARGET_GROUP_ID = 4;

    private function runJob(array $config, ?string $runAs = null): void
    {
        static::asQueueWorker($runAs);
        (new MoveUsersJob($config))->run();
        static::flushCache();
    }

    /**
     * @return int[]
     */
    private static function groupUserIds(int $groupId): array
    {
        return array_map('intval', GroupUser::find()->select('user_id')->where(['group_id' => $groupId])->orderBy('user_id')->column());
    }

    public function testMoveGroupUsers(): void
    {
        $sourceUserIds = static::groupUserIds(self::SOURCE_GROUP_ID);
        $this->assertNotEmpty($sourceUserIds);

        $this->runJob(['sourceGroupId' => self::SOURCE_GROUP_ID, 'targetGroupId' => self::TARGET_GROUP_ID]);

        $this->assertSame([], static::groupUserIds(self::SOURCE_GROUP_ID));
        $this->assertSame($sourceUserIds, static::groupUserIds(self::TARGET_GROUP_ID));
    }

    public function testMoveGroupUsersKeepsExistingTargetMembership(): void
    {
        // User2 is already in the target group
        $this->assertTrue(Group::findOne(self::TARGET_GROUP_ID)->addUser(3));

        $this->runJob(['sourceGroupId' => self::SOURCE_GROUP_ID, 'targetGroupId' => self::TARGET_GROUP_ID]);

        $this->assertSame([], static::groupUserIds(self::SOURCE_GROUP_ID));
        $this->assertSame([3, 6], static::groupUserIds(self::TARGET_GROUP_ID));
    }

    /**
     * @dataProvider runAsProvider
     */
    public function testMoveGroupUsersDoesNotNotifyUsers(?string $runAs): void
    {
        // Groups notifying their users when they are added or removed
        Group::updateAll(['notify_users' => 1], ['id' => [self::SOURCE_GROUP_ID, self::TARGET_GROUP_ID]]);
        $notificationCount = static::notificationCount();
        $sentEmails = $this->sentEmails();

        $this->runJob(['sourceGroupId' => self::SOURCE_GROUP_ID, 'targetGroupId' => self::TARGET_GROUP_ID], $runAs);

        $this->assertSame([3, 6], static::groupUserIds(self::TARGET_GROUP_ID));
        $this->assertSame($notificationCount, static::notificationCount(), 'No notification created');
        $this->assertSame($sentEmails, $this->sentEmails(), 'No email sent');
    }

    /**
     * @dataProvider runAsProvider
     */
    public function testMoveSpaceUsers(?string $runAs): void
    {
        $notificationCount = static::notificationCount();
        $sentEmails = $this->sentEmails();

        $this->runJob([
            'sourceSpaceGuid' => static::reloadSpace(3)->guid,
            'targetSpaceGuid' => static::reloadSpace(5)->guid,
        ], $runAs);

        // Only the owner remains in the source space
        $this->assertSame([1 => Space::USERGROUP_ADMIN], static::memberRoles(static::reloadSpace(3)));
        // Missing users are added with the same role, existing members keep their role
        $this->assertSame([
            1 => Space::USERGROUP_ADMIN,
            2 => Space::USERGROUP_MEMBER,
            3 => Space::USERGROUP_MODERATOR,
        ], static::memberRoles(static::reloadSpace(5)));

        $this->assertSame($notificationCount, static::notificationCount(), 'No notification created');
        $this->assertSame($sentEmails, $this->sentEmails(), 'No email sent');
    }

    public function testNothingHappensWithUnknownGroupOrSpace(): void
    {
        $sourceUserIds = static::groupUserIds(self::SOURCE_GROUP_ID);
        $spaceMembers = static::memberRoles(static::reloadSpace(3));

        $this->runJob([
            'sourceGroupId' => self::SOURCE_GROUP_ID,
            'targetGroupId' => 999,
            'sourceSpaceGuid' => static::reloadSpace(3)->guid,
            'targetSpaceGuid' => 'unknown-guid',
        ]);

        $this->assertSame($sourceUserIds, static::groupUserIds(self::SOURCE_GROUP_ID));
        $this->assertSame($spaceMembers, static::memberRoles(static::reloadSpace(3)));
    }
}
