<?php

/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\tests\codeception\unit;

use humhub\modules\comment\models\Comment;
use humhub\modules\content\models\Content;
use humhub\modules\like\models\Like;
use humhub\modules\moveContent\jobs\MoveSpaceContentJob;
use humhub\modules\moveContent\tests\codeception\_support\MoveContentTestTrait;
use humhub\modules\post\models\Post;
use humhub\modules\space\models\Space;
use humhub\modules\topic\models\Topic;
use tests\codeception\_support\HumHubDbTestCase;

/**
 * Fixtures used:
 * - Space 3 (source, owner Admin): Admin (admin), User1 (member), User2 (moderator)
 * - Space 5 (target, owner Admin): Admin (admin), User1 (member)
 */
class MoveSpaceContentJobTest extends HumHubDbTestCase
{
    use MoveContentTestTrait;

    private const SOURCE_SPACE_ID = 3;
    private const TARGET_SPACE_ID = 5;

    private function runJob(bool $moveContent, bool $moveMembers, ?string $sourceGuid = null, ?string $targetGuid = null, ?string $runAs = null): void
    {
        static::asQueueWorker($runAs);
        (new MoveSpaceContentJob([
            'sourceSpaceGuid' => $sourceGuid ?? static::reloadSpace(self::SOURCE_SPACE_ID)->guid,
            'targetSpaceGuid' => $targetGuid ?? static::reloadSpace(self::TARGET_SPACE_ID)->guid,
            'moveContent' => $moveContent,
            'moveMembers' => $moveMembers,
        ]))->run();
        static::flushCache();
    }

    /**
     * @return Post[]
     */
    private function createSourceContent(): array
    {
        $source = static::reloadSpace(self::SOURCE_SPACE_ID);

        $posts = [
            'user1Private' => static::createPost('User1', $source, 'User1 private post', Content::VISIBILITY_PRIVATE),
            'user2Public' => static::createPost('User2', $source, 'User2 public post', Content::VISIBILITY_PUBLIC),
            'adminPost' => static::createPost('Admin', $source, 'Admin post'),
        ];

        static::createComment('User2', $posts['user1Private'], 'User2 comment on User1 post');
        static::createComment('User1', $posts['user2Public'], 'User1 comment on User2 post');
        static::like('User2', $posts['user1Private']);
        static::like('User1', $posts['user2Public']);

        return $posts;
    }

    public function testMoveContentOnlyMovesAllContentAndKeepsOwners(): void
    {
        $posts = $this->createSourceContent();
        $source = static::reloadSpace(self::SOURCE_SPACE_ID);
        $target = static::reloadSpace(self::TARGET_SPACE_ID);

        $sourceContentIds = static::contentIds($source);
        $targetContentIds = static::contentIds($target);
        $this->assertNotEmpty($sourceContentIds);

        $contentBefore = Content::find()->where(['id' => $sourceContentIds])->indexBy('id')->asArray()->all();
        $commentsBefore = Comment::find()->where(['content_id' => $sourceContentIds])->asArray()->all();
        $likesBefore = Like::find()->where(['content_id' => $sourceContentIds])->asArray()->all();
        $sourceMembers = static::memberRoles($source);
        $targetMembers = static::memberRoles($target);

        $this->runJob(true, false);

        // No content lost: everything is now in the target space
        $this->assertSame([], static::contentIds(static::reloadSpace(self::SOURCE_SPACE_ID)));
        $this->assertEqualsCanonicalizing(
            array_merge($targetContentIds, $sourceContentIds),
            static::contentIds(static::reloadSpace(self::TARGET_SPACE_ID)),
        );

        // Owners, visibility, states and dates are unchanged
        foreach (Content::find()->where(['id' => $sourceContentIds])->asArray()->all() as $content) {
            $before = $contentBefore[$content['id']];
            foreach (['created_by', 'created_at', 'updated_by', 'updated_at', 'visibility', 'state', 'stream_sort_date', 'object_model', 'object_id'] as $attribute) {
                $this->assertEquals($before[$attribute], $content[$attribute], 'Content ' . $content['id'] . ': ' . $attribute);
            }
        }

        // Comments and likes still belong to the moved content with the same authors
        $this->assertEquals($commentsBefore, Comment::find()->where(['content_id' => $sourceContentIds])->asArray()->all());
        $this->assertEquals($likesBefore, Like::find()->where(['content_id' => $sourceContentIds])->asArray()->all());

        // The authors can still view and edit their content
        foreach (['User1' => 'user1Private', 'User2' => 'user2Public'] as $userName => $key) {
            $user = static::reloadUser($userName);
            $content = Post::findOne($posts[$key]->id)->content;
            $this->assertTrue($content->canView($user), $userName . ' can view their content');
            $this->assertTrue($content->canEdit($user), $userName . ' can edit their content');
        }

        // Members are untouched
        $this->assertSame($sourceMembers, static::memberRoles(static::reloadSpace(self::SOURCE_SPACE_ID)));
        $this->assertSame($targetMembers, static::memberRoles(static::reloadSpace(self::TARGET_SPACE_ID)));
    }

    public function testMoveContentAlsoMovesNotPublishedContent(): void
    {
        $source = static::reloadSpace(self::SOURCE_SPACE_ID);
        $archived = static::createPost('User1', $source, 'Archived post');
        $this->assertTrue($archived->content->archive());
        $draft = static::createPost('User1', $source, 'Draft post');
        $draft->content->getStateService()->draft();
        $this->assertTrue($draft->content->save());
        $deleted = static::createPost('User1', $source, 'Deleted post');
        $this->assertTrue($deleted->content->softDelete());

        $this->runJob(true, false);

        $target = static::reloadSpace(self::TARGET_SPACE_ID);
        foreach ([$archived, $draft, $deleted] as $post) {
            $content = Content::findOne($post->content->id);
            $this->assertSame($target->contentcontainer_id, $content->contentcontainer_id, $post->message);
            $this->assertEquals($post->content->state, $content->state, $post->message);
        }
        $this->assertTrue(Content::findOne($archived->content->id)->isArchived());
    }

    public function testMoveContentMergesTopics(): void
    {
        $source = static::reloadSpace(self::SOURCE_SPACE_ID);
        $target = static::reloadSpace(self::TARGET_SPACE_ID);

        $post = static::createPost('Admin', $source, 'Post with topics');
        Topic::attach($post->content, ['_add:Shared', '_add:SourceOnly']);
        $targetPost = static::createPost('Admin', $target, 'Target post with topic');
        Topic::attach($targetPost->content, ['_add:Shared']);

        $sharedTargetTopic = Topic::findOne(['name' => 'Shared', 'contentcontainer_id' => $target->contentcontainer_id]);
        $sourceOnlyTopic = Topic::findOne(['name' => 'SourceOnly', 'contentcontainer_id' => $source->contentcontainer_id]);
        $this->assertNotNull($sharedTargetTopic);
        $this->assertNotNull($sourceOnlyTopic);

        $this->runJob(true, false);

        // The duplicated source topic is replaced by the target one, the other one is moved
        $this->assertSame([], Topic::findByContainer($source)->column());
        $this->assertSame(1, (int)Topic::find()->where(['name' => 'Shared'])->count());
        $this->assertSame($target->contentcontainer_id, Topic::findOne($sourceOnlyTopic->id)->contentcontainer_id);

        $postTopicIds = array_map('intval', Topic::findByContent(Post::findOne($post->id)->content)->select('content_tag.id')->column());
        $this->assertEqualsCanonicalizing([$sharedTargetTopic->id, $sourceOnlyTopic->id], $postTopicIds);
    }

    public function testMoveMembersOnlyKeepsContentInSourceSpace(): void
    {
        $this->createSourceContent();
        $source = static::reloadSpace(self::SOURCE_SPACE_ID);
        $target = static::reloadSpace(self::TARGET_SPACE_ID);
        $sourceContentIds = static::contentIds($source);
        $targetContentIds = static::contentIds($target);

        $this->runJob(false, true);

        $this->assertSame($sourceContentIds, static::contentIds(static::reloadSpace(self::SOURCE_SPACE_ID)));
        $this->assertSame($targetContentIds, static::contentIds(static::reloadSpace(self::TARGET_SPACE_ID)));

        // Only the owner remains in the source space
        $this->assertSame([1 => Space::USERGROUP_ADMIN], static::memberRoles(static::reloadSpace(self::SOURCE_SPACE_ID)));

        // Missing users are added with the same role, existing members keep their role
        $this->assertSame([
            1 => Space::USERGROUP_ADMIN,
            2 => Space::USERGROUP_MEMBER,
            3 => Space::USERGROUP_MODERATOR,
        ], static::memberRoles(static::reloadSpace(self::TARGET_SPACE_ID)));
    }

    /**
     * @dataProvider runAsProvider
     */
    public function testMoveMembersDoesNotNotifyUsers(?string $runAs): void
    {
        $this->createSourceContent();
        $notificationCount = static::notificationCount();
        $sentEmails = $this->sentEmails();

        $this->runJob(false, true, null, null, $runAs);

        $this->assertSame($notificationCount, static::notificationCount(), 'No notification created');
        $this->assertSame($sentEmails, $this->sentEmails(), 'No email sent');
    }

    /**
     * @dataProvider runAsProvider
     */
    public function testMoveContentAndMembers(?string $runAs): void
    {
        $posts = $this->createSourceContent();
        $sourceContentIds = static::contentIds(static::reloadSpace(self::SOURCE_SPACE_ID));
        $notificationCount = static::notificationCount();
        $sentEmails = $this->sentEmails();

        $this->runJob(true, true, null, null, $runAs);

        $source = static::reloadSpace(self::SOURCE_SPACE_ID);
        $target = static::reloadSpace(self::TARGET_SPACE_ID);
        $this->assertSame([], static::contentIds($source));
        $this->assertEmpty(array_diff($sourceContentIds, static::contentIds($target)));
        $this->assertSame([1 => Space::USERGROUP_ADMIN], static::memberRoles($source));
        $this->assertTrue($target->isMember(3));

        // The moved members can view and edit their content in the target space
        foreach (['User1' => 'user1Private', 'User2' => 'user2Public'] as $userName => $key) {
            $user = static::reloadUser($userName);
            $content = Post::findOne($posts[$key]->id)->content;
            $this->assertTrue($content->canView($user), $userName . ' can view their content');
            $this->assertTrue($content->canEdit($user), $userName . ' can edit their content');
        }
        // User2 can view User1's private post, as a member of the target space
        $this->assertTrue(Post::findOne($posts['user1Private']->id)->content->canView(static::reloadUser('User2')));

        $this->assertSame($notificationCount, static::notificationCount(), 'No notification created');
        $this->assertSame($sentEmails, $this->sentEmails(), 'No email sent');
    }

    public function testNothingHappensWithUnknownSpace(): void
    {
        $this->createSourceContent();
        $source = static::reloadSpace(self::SOURCE_SPACE_ID);
        $sourceContentIds = static::contentIds($source);
        $sourceMembers = static::memberRoles($source);

        $this->runJob(true, true, null, 'unknown-guid');

        $source = static::reloadSpace(self::SOURCE_SPACE_ID);
        $this->assertSame($sourceContentIds, static::contentIds($source));
        $this->assertSame($sourceMembers, static::memberRoles($source));
    }
}
