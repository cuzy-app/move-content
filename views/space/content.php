<?php
/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

use humhub\modules\moveContent\models\MoveUserContentModel;
use humhub\modules\space\widgets\SpacePickerField;
use humhub\widgets\form\ActiveForm;
use humhub\components\View;
use humhub\widgets\bootstrap\Button;

/**
 * @var $this View
 * @var $model MoveUserContentModel
 * @var $title string
 */
?>

<div class="panel-body">
    <h4><?= $title ?></h4>
    <br>

    <?php $form = ActiveForm::begin(); ?>
    <?= SpacePickerField::widget([
        'model' => $model,
        'form' => $form,
        'attribute' => 'sourceSpaceGuid',
        'maxSelection' => 1,
    ]) ?>
    <?= SpacePickerField::widget([
        'model' => $model,
        'form' => $form,
        'attribute' => 'targetSpaceGuid',
        'maxSelection' => 1,
    ]) ?>
    <?= $form->field($model, 'moveUsers')->checkbox() ?>

    <?= Button::save()->submit() ?>
    <?php ActiveForm::end(); ?>
</div>
