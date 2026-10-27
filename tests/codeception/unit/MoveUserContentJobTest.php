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
use humhub\modules\file\models\File;
use humhub\modules\like\models\Like;
use humhub\modules\moveContent\jobs\MoveUserContentJob;
use humhub\modules\moveContent\tests\codeception\_support\MoveContentTestTrait;
use humhub\modules\post\models\Post;
use tests\codeception\_support\HumHubDbTestCase;

/**
 * Moves the content of User1 (ID 2) to User3 (ID 4)
 */
class MoveUserContentJobTest extends HumHubDbTestCase
{
    use MoveContentTestTrait;

    private const SOURCE_USER_ID = 2;
    private const TARGET_USER_ID = 4;

    private function runJob(bool $moveProfileContent, ?string $runAs = null): void
    {
        static::asQueueWorker($runAs);
        (new MoveUserContentJob([
            'sourceUserGuid' => static::reloadUser('User1')->guid,
            'targetUserGuid' => static::reloadUser('User3')->guid,
            'moveProfileContent' => $moveProfileContent,
        ]))->run();
        static::flushCache();
    }

    /**
     * @return array{spacePost: Post, profilePost: Post, otherPost: Post, comment: Comment, file: File}
     */
    private function createSourceContent(): array
    {
        $spacePost = static::createPost('User1', static::reloadSpace(3), 'User1 space post');
        $profilePost = static::createPost('User1', static::reloadUser('User1'), 'User1 profile post');
        $otherPost = static::createPost('User2', static::reloadSpace(3), 'User2 space post');
        $comment = static::createComment('User1', $otherPost, 'User1 comment');
        static::like('User1', $otherPost);
        static::createComment('User2', $spacePost, 'User2 comment on User1 post');

        $file = new File([
            'object_model' => Post::class,
            'object_id' => $spacePost->id,
            'file_name' => 'test.txt',
            'title' => 'Test file',
            'mime_type' => 'text/plain',
            'size' => 10,
        ]);
        static::becomeUser('User1');
        $this->assertTrue($file->save());

        return compact('spacePost', 'profilePost', 'otherPost', 'comment', 'file');
    }

    public function testMoveContentChangesAuthorsWithoutLosingContent(): void
    {
        $items = $this->createSourceContent();
        $contentBefore = Content::find()->indexBy('id')->asArray()->all();
        $sourceContentIds = array_map('intval', Content::find()->select('id')->where(['created_by' => self::SOURCE_USER_ID])->column());
        $this->assertNotEmpty($sourceContentIds);

        $this->runJob(false);

        // No content lost, containers, states and dates unchanged
        $contentAfter = Content::find()->indexBy('id')->asArray()->all();
        $this->assertEqualsCanonicalizing(array_keys($contentBefore), array_keys($contentAfter));
        foreach ($contentAfter as $id => $content) {
            foreach (['contentcontainer_id', 'visibility', 'state', 'created_at', 'stream_sort_date', 'object_model', 'object_id'] as $attribute) {
                $this->assertEquals($contentBefore[$id][$attribute], $content[$attribute], 'Content ' . $id . ': ' . $attribute);
            }
        }

        // The source user has no content anymore, the target user is the author
        $this->assertSame(0, (int)Content::find()->where(['created_by' => self::SOURCE_USER_ID])->count());
        foreach ($sourceContentIds as $id) {
            $this->assertSame(self::TARGET_USER_ID, Content::findOne($id)->created_by, 'Content ' . $id);
        }
        $this->assertSame(self::TARGET_USER_ID, Post::findOne($items['spacePost']->id)->created_by);
        $this->assertSame(self::TARGET_USER_ID, Post::findOne($items['profilePost']->id)->created_by);

        // Other users' content is untouched
        $this->assertSame(3, Content::findOne($items['otherPost']->content->id)->created_by);
        $this->assertSame(3, (int)Comment::find()->where(['content_id' => $items['spacePost']->content->id])->select('created_by')->scalar());

        // Comments, likes and files
        $this->assertSame(self::TARGET_USER_ID, Comment::findOne($items['comment']->id)->created_by);
        $this->assertSame(0, (int)Comment::find()->where(['created_by' => self::SOURCE_USER_ID])->count());
        $this->assertSame(0, (int)Like::find()->where(['created_by' => self::SOURCE_USER_ID])->count());
        $this->assertTrue(Like::find()->where(['created_by' => self::TARGET_USER_ID, 'content_id' => $items['otherPost']->content->id])->exists());
        $this->assertSame(self::TARGET_USER_ID, File::findOne($items['file']->id)->created_by);

        // The target user can edit the content
        $target = static::reloadUser('User3');
        $this->assertTrue(Post::findOne($items['spacePost']->id)->content->canEdit($target));
        $this->assertTrue(Post::findOne($items['profilePost']->id)->content->canEdit($target));
    }

    public function testProfileContentStaysInSourceProfileByDefault(): void
    {
        $items = $this->createSourceContent();

        $this->runJob(false);

        $this->assertSame(
            static::reloadUser('User1')->contentcontainer_id,
            Content::findOne($items['profilePost']->content->id)->contentcontainer_id,
        );
        $this->assertSame(
            static::reloadSpace(3)->contentcontainer_id,
            Content::findOne($items['spacePost']->content->id)->contentcontainer_id,
        );
    }

    public function testProfileContentIsMovedToTargetProfile(): void
    {
        $items = $this->createSourceContent();
        $profileContentIds = static::contentIds(static::reloadUser('User1'));
        $this->assertContains($items['profilePost']->content->id, $profileContentIds);

        $this->runJob(true);

        $target = static::reloadUser('User3');
        $this->assertSame([], static::contentIds(static::reloadUser('User1')));
        $this->assertEmpty(array_diff($profileContentIds, static::contentIds($target)));
        $this->assertSame(
            static::reloadSpace(3)->contentcontainer_id,
            Content::findOne($items['spacePost']->content->id)->contentcontainer_id,
            'Space content stays in the space',
        );
        $this->assertTrue(Post::findOne($items['profilePost']->id)->content->canView($target));
    }

    public function testContentPostedByOthersOnSourceProfileKeepsItsAuthor(): void
    {
        $post = static::createPost('Admin', static::reloadUser('User1'), 'Admin post on User1 profile');

        $this->runJob(false);

        $this->assertSame(1, Content::findOne($post->content->id)->created_by);
    }

    /**
     * @dataProvider runAsProvider
     */
    public function testMoveContentDoesNotNotifyUsers(?string $runAs): void
    {
        $this->createSourceContent();
        $notificationCount = static::notificationCount();
        $sentEmails = $this->sentEmails();

        $this->runJob(true, $runAs);

        $this->assertSame($notificationCount, static::notificationCount(), 'No notification created');
        $this->assertSame($sentEmails, $this->sentEmails(), 'No email sent');
    }
}
