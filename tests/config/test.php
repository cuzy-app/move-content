<?php

/**
 * Shared config for all test suites.
 */
return [
    'modules' => ['move-content'],
    'fixtures' => [
        'default',
        'post' => 'humhub\modules\post\tests\codeception\fixtures\PostFixture',
    ],
];
