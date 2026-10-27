<?php

/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\tests\codeception\unit;

use humhub\modules\admin\permissions\ManageUsers;
use humhub\modules\content\models\Content;
use humhub\modules\moveContent\models\MoveSpaceContentModel;
use humhub\modules\moveContent\models\MoveUserContentModel;
use humhub\modules\moveContent\models\MoveUsersModel;
use humhub\modules\moveContent\tests\codeception\_support\MoveContentTestTrait;
use humhub\modules\user\models\GroupUser;
use tests\codeception\_support\HumHubDbTestCase;

class MoveModelsTest extends HumHubDbTestCase
{
    use MoveContentTestTrait;

    public function testSpaceModelHasNothingCheckedByDefault(): void
    {
        $model = new MoveSpaceContentModel();

        $this->assertFalse($model->moveContent);
        $this->assertFalse($model->moveMembers);
    }

    public function testSpaceModelRequiresSpaces(): void
    {
        $model = new MoveSpaceContentModel(['moveContent' => true]);

        $this->assertFalse($model->validate());
        $this->assertTrue($model->hasErrors('sourceSpaceGuid'));
        $this->assertTrue($model->hasErrors('targetSpaceGuid'));
    }

    /**
     * @dataProvider spaceModelOptionsProvider
     */
    public function testSpaceModelRequiresAtLeastOneOption(string $moveContent, string $moveMembers, bool $isValid): void
    {
        $model = new MoveSpaceContentModel();
        // Values as posted by the checkboxes
        $this->assertTrue($model->load(['MoveSpaceContentModel' => [
            'sourceSpaceGuid' => [static::reloadSpace(3)->guid],
            'targetSpaceGuid' => [static::reloadSpace(5)->guid],
            'moveContent' => $moveContent,
            'moveMembers' => $moveMembers,
        ]]));

        $this->assertSame($isValid, $model->validate());
        $this->assertSame(!$isValid, $model->hasErrors('moveContent'));
    }

    public static function spaceModelOptionsProvider(): array
    {
        return [
            'nothing' => ['0', '0', false],
            'content' => ['1', '0', true],
            'members' => ['0', '1', true],
            'both' => ['1', '1', true],
        ];
    }

    public function testSpaceModelSaveMovesContentAndMembers(): void
    {
        static::createPost('User1', static::reloadSpace(3), 'Post to move');
        $sourceContentIds = static::contentIds(static::reloadSpace(3));

        $this->becomeUser('Admin');
        $model = new MoveSpaceContentModel();
        $model->load(['MoveSpaceContentModel' => [
            'sourceSpaceGuid' => [static::reloadSpace(3)->guid],
            'targetSpaceGuid' => [static::reloadSpace(5)->guid],
            'moveContent' => '1',
            'moveMembers' => '1',
        ]]);
        $this->assertTrue($model->validate());
        $this->assertSame(static::reloadSpace(3)->guid, $model->sourceSpaceGuid, 'Space picker array is normalized');

        // The queue runs the job instantly in tests
        $this->assertTrue($model->save());
        static::flushCache();

        $this->assertSame([], static::contentIds(static::reloadSpace(3)));
        $this->assertEmpty(array_diff($sourceContentIds, static::contentIds(static::reloadSpace(5))));
        $this->assertSame([1], array_keys(static::memberRoles(static::reloadSpace(3))));
        $this->assertTrue(static::reloadSpace(5)->isMember(3));
    }

    public function testUserContentModelValidatesUsers(): void
    {
        $this->becomeUser('Admin');

        $model = new MoveUserContentModel();
        $this->assertFalse($model->validate());
        $this->assertTrue($model->hasErrors('sourceUserGuid'));
        $this->assertTrue($model->hasErrors('targetUserGuid'));

        $model = new MoveUserContentModel(['sourceUserGuid' => ['unknown-guid'], 'targetUserGuid' => [static::reloadUser('User3')->guid]]);
        $this->assertFalse($model->validate());
        $this->assertTrue($model->hasErrors('sourceUserGuid'));
        $this->assertFalse($model->hasErrors('targetUserGuid'));

        $model = new MoveUserContentModel(['sourceUserGuid' => [static::reloadUser('User1')->guid], 'targetUserGuid' => [static::reloadUser('Admin')->guid]]);
        $this->assertTrue($model->validate(), 'A system admin can move content to a system admin');
    }

    public function testUserContentModelDoesNotAllowNonAdminsToUseSystemAdmins(): void
    {
        // User1 can manage users, but is not a system admin
        static::setGroupPermission(2, new ManageUsers());
        $this->becomeUser('User1');

        $model = new MoveUserContentModel(['sourceUserGuid' => [static::reloadUser('User2')->guid], 'targetUserGuid' => [static::reloadUser('Admin')->guid]]);
        $this->assertFalse($model->validate());
        $this->assertTrue($model->hasErrors('targetUserGuid'));

        $model = new MoveUserContentModel(['sourceUserGuid' => [static::reloadUser('Admin')->guid], 'targetUserGuid' => [static::reloadUser('User2')->guid]]);
        $this->assertFalse($model->validate());
        $this->assertTrue($model->hasErrors('sourceUserGuid'));
    }

    public function testUserContentModelSaveMovesContent(): void
    {
        $post = static::createPost('User1', static::reloadSpace(3), 'Post to move', Content::VISIBILITY_PUBLIC);

        $this->becomeUser('Admin');
        $model = new MoveUserContentModel(['sourceUserGuid' => [static::reloadUser('User1')->guid], 'targetUserGuid' => [static::reloadUser('User3')->guid]]);
        $this->assertTrue($model->validate());
        $this->assertTrue($model->save());

        $this->assertSame(4, Content::findOne($post->content->id)->created_by);
    }

    public function testUsersModelValidatesGroups(): void
    {
        $this->becomeUser('Admin');
        $model = new MoveUsersModel(['sourceGroupId' => 3, 'targetGroupId' => 4]);
        $this->assertTrue($model->validate());

        $model = new MoveUsersModel(['sourceGroupId' => 3, 'targetGroupId' => 999]);
        $this->assertFalse($model->validate());
        $this->assertTrue($model->hasErrors('targetGroupId'));
    }

    public function testUsersModelOnlyAllowsManagedGroups(): void
    {
        // User1 only manages the group "Users" (ID 2)
        $this->becomeUser('User1');
        $model = new MoveUsersModel(['sourceGroupId' => 2, 'targetGroupId' => 3]);
        $this->assertFalse($model->validate());
        $this->assertFalse($model->hasErrors('sourceGroupId'));
        $this->assertTrue($model->hasErrors('targetGroupId'));
    }

    public function testUsersModelValidatesSpaces(): void
    {
        $this->becomeUser('Admin');
        $model = new MoveUsersModel(['sourceSpaceGuid' => [static::reloadSpace(3)->guid], 'targetSpaceGuid' => [static::reloadSpace(5)->guid]]);
        $this->assertTrue($model->validate());
    }

    public function testUsersModelDoesNotAllowSpacesWithoutPermission(): void
    {
        // User1 cannot manage spaces
        $this->becomeUser('User1');
        $model = new MoveUsersModel(['sourceSpaceGuid' => [static::reloadSpace(3)->guid], 'targetSpaceGuid' => [static::reloadSpace(5)->guid]]);
        $this->assertFalse($model->validate());
        $this->assertTrue($model->hasErrors('sourceSpaceGuid'));
    }

    public function testUsersModelSaveMovesGroupUsers(): void
    {
        $this->becomeUser('Admin');
        $model = new MoveUsersModel(['sourceGroupId' => 3, 'targetGroupId' => 4]);
        $this->assertTrue($model->validate());
        $this->assertTrue($model->save());

        $this->assertFalse(GroupUser::find()->where(['group_id' => 3])->exists());
        $this->assertSame([3, 6], array_map('intval', GroupUser::find()->select('user_id')->where(['group_id' => 4])->orderBy('user_id')->column()));
    }
}
