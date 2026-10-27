<?php

/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\tests\codeception\_support;

use humhub\modules\comment\models\Comment;
use humhub\modules\content\components\ContentContainerActiveRecord;
use humhub\modules\content\models\Content;
use humhub\modules\like\services\LikeService;
use humhub\modules\notification\models\Notification;
use humhub\modules\post\models\Post;
use humhub\modules\space\models\Membership;
use humhub\modules\space\models\Space;
use humhub\modules\user\models\User;
use Yii;

trait MoveContentTestTrait
{
    protected static function createPost(string $userName, ContentContainerActiveRecord $container, string $message, int $visibility = Content::VISIBILITY_PRIVATE): Post
    {
        static::becomeUser($userName);
        $post = new Post($container, $visibility, ['message' => $message]);
        static::assertTrue($post->save(), 'Post saved: ' . implode(' ', $post->getErrorSummary(true)));

        return $post;
    }

    protected static function createComment(string $userName, Post $post, string $message): Comment
    {
        static::becomeUser($userName);
        $comment = new Comment(['message' => $message, 'content_id' => $post->content->id]);
        static::assertTrue($comment->save(), 'Comment saved: ' . implode(' ', $comment->getErrorSummary(true)));

        return $comment;
    }

    protected static function like(string $userName, Post $post): void
    {
        $user = static::becomeUser($userName);
        static::assertTrue((new LikeService(Post::findOne($post->id), $user))->like());
    }

    /**
     * @return int[] Content IDs of the container
     */
    protected static function contentIds(ContentContainerActiveRecord $container): array
    {
        return array_map('intval', Content::find()
            ->select('id')
            ->where(['contentcontainer_id' => $container->contentcontainer_id])
            ->orderBy('id')
            ->column());
    }

    /**
     * @return array<int, string> User ID => space role (group_id) of the space members
     */
    protected static function memberRoles(Space $space): array
    {
        return array_map('strval', Membership::find()
            ->select(['group_id', 'user_id'])
            ->where(['space_id' => $space->id, 'status' => Membership::STATUS_MEMBER])
            ->indexBy('user_id')
            ->orderBy('user_id')
            ->column());
    }

    /**
     * @return string[] Recipients and subjects of the emails sent since the beginning of the test
     */
    protected function sentEmails(): array
    {
        return array_map(
            static fn($message) => implode(', ', array_keys($message->getTo())) . ': ' . $message->getSubject(),
            $this->getYiiModule()->grabSentEmails(),
        );
    }

    protected static function notificationCount(): int
    {
        return (int)Notification::find()->count();
    }

    /**
     * Run the jobs as the queue worker does (no logged-in user), or as the user submitting the form
     * when the queue runs the jobs instantly, with fresh models and caches
     */
    protected static function asQueueWorker(?string $userName = null): void
    {
        if ($userName === null) {
            static::logout();
        } else {
            static::becomeUser($userName);
        }
        static::flushCache();
        Yii::$app->runtimeCache->flush();
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function runAsProvider(): array
    {
        return [
            'queue worker' => [null],
            'instant queue as admin' => ['Admin'],
        ];
    }

    protected static function reloadSpace(int $id): Space
    {
        return Space::findOne(['id' => $id]);
    }

    protected static function reloadUser(string $userName): User
    {
        return User::findOne(['username' => $userName]);
    }
}
