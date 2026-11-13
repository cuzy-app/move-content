<?php

/**
 * Move content
 * @link [REDACTED:CUZY_SEO_SITE]
 * @license [REDACTED:CUZY_SEO_SITE]/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\tests\codeception\unit;

use humhub\modules\comment\models\Comment;
use humhub\modules\moveContent\jobs\MoveUserContentJob;
use humhub\modules\moveContent\tests\codeception\_support\MoveContentTestTrait;
use humhub\modules\user\models\Password;
use humhub\modules\user\models\User;
use tests\codeception\_support\HumHubDbTestCase;

/**
 * The non-content records are moved with one UPDATE per class. Classes whose table has no
 * `created_by` column (e.g. `sms_verify`) or that do not exist must be skipped, not crash the job.
 */
class MoveNonContentRecordsTest extends HumHubDbTestCase
{
    use MoveContentTestTrait;

    public function testTablesWithoutCreatedByAreSkippedAndOthersMoved(): void
    {
        $post = static::createPost('User2', static::reloadSpace(3), 'User2 space post');
        $comment = static::createComment('User1', $post, 'User1 comment');
        $source = static::reloadUser('User1');
        $target = static::reloadUser('User3');
        $passwordsBefore = Password::find()->orderBy('id')->asArray()->all();

        $job = new class (['sourceUserGuid' => $source->guid, 'targetUserGuid' => $target->guid]) extends MoveUserContentJob {
            // user_password has no created_by column; the last entry is not a class
            protected const NON_CONTENT_CLASSES = [
                Password::class,
                Comment::class,
                'humhub\\modules\\notInstalled\\models\\Missing',
            ];

            public function moveRecords(User $source, User $target): void
            {
                $this->moveNonContentActiveRecords($source, $target);
            }
        };

        $job->moveRecords($source, $target);

        $this->assertSame($target->id, (int)Comment::find()->select('created_by')->where(['id' => $comment->id])->scalar());
        $this->assertSame($passwordsBefore, Password::find()->orderBy('id')->asArray()->all());
    }

    public function testOnlyTheSourceUserRecordsAreMoved(): void
    {
        $post = static::createPost('User2', static::reloadSpace(3), 'User2 space post');
        $mine = static::createComment('User1', $post, 'User1 comment');
        $other = static::createComment('User2', $post, 'User2 comment');
        $source = static::reloadUser('User1');
        $target = static::reloadUser('User3');

        $job = new class (['sourceUserGuid' => $source->guid, 'targetUserGuid' => $target->guid]) extends MoveUserContentJob {
            protected const NON_CONTENT_CLASSES = [Comment::class];

            public function moveRecords(User $source, User $target): void
            {
                $this->moveNonContentActiveRecords($source, $target);
            }
        };
        $job->moveRecords($source, $target);

        $this->assertSame($target->id, (int)Comment::find()->select('created_by')->where(['id' => $mine->id])->scalar());
        $this->assertSame((int)$other->created_by, (int)Comment::find()->select('created_by')->where(['id' => $other->id])->scalar());
    }
}
