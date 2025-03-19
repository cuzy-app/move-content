<?php
/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

/* @var $this View */

use humhub\modules\moveContent\Module;
use humhub\modules\ui\view\components\View;
use humhub\widgets\Button;

/** @var Module $module */
$module = Yii::$app->getModule('move-content');
?>
<div class="container-fluid">
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong><?= $module->getName() ?></strong>
            <div class="help-block"><?= $module->getDescription() ?></div>
        </div>

        <div class="panel-body">

            <div class="alert alert-info cuzy-free-module-info">
                This module was created and is maintained by
                <a href="https://www.cuzy.app/"
                   target="_blank">CUZY.APP (view other modules)</a>.
                <br>
                It's free, but it's the result of a lot of design and maintenance work over time.
                <br>
                If it's useful to you, please consider
                <a href="https://www.cuzy.app/checkout/donate/"
                   target="_blank">making a donation</a>
                or
                <a href="https://github.com/cuzy-app/move-content"
                   target="_blank">participating in the code</a>.
                Thanks!
            </div>

            <div>
                <?= Button::primary('Move content from one user to another')->link(['/move-content/user/content']) ?>
                <br><br>
                <?= Button::primary('Move content and users from one space to another')->link(['/move-content/space/content']) ?>
                <br><br>
                <?= Button::primary('Move users from one group to another')->link(['/move-content/user/group']) ?>
            </div>
        </div>
    </div>
</div>
