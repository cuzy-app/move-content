<?php
/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\moveContent\models\MoveUserContentModel;
use humhub\widgets\form\ActiveForm;
use humhub\components\View;
use humhub\modules\user\widgets\UserPickerField;
use humhub\widgets\bootstrap\Button;

/**
 * @var $this View
 * @var $model MoveUserContentModel
 * @var $title string
 */

AdminMenu::markAsActive(['/admin/user']);
?>

<div class="panel-body">
    <h4><?= $title ?></h4>
    <br>

    <?php $form = ActiveForm::begin(); ?>
    <?= UserPickerField::widget([
        'model' => $model,
        'form' => $form,
        'attribute' => 'sourceUserGuid',
        'maxSelection' => 1,
    ]) ?>
    <?= UserPickerField::widget([
        'model' => $model,
        'form' => $form,
        'attribute' => 'targetUserGuid',
        'maxSelection' => 1,
    ]) ?>
    <?= $form->field($model, 'moveProfileContent')->checkbox() ?>
    <?= Button::save()->submit() ?>
    <?php ActiveForm::end(); ?>
</div>
