<?php
/**
 * Move Content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

use humhub\modules\moveContent\models\MoveUserContentModel;
use humhub\modules\ui\form\widgets\ActiveForm;
use humhub\modules\ui\view\components\View;
use humhub\modules\user\widgets\UserPickerField;
use humhub\widgets\Button;

/**
 * @var $this View
 * @var $model MoveUserContentModel
 * @var $title string
 */
?>

<div class="panel-body">
    <h4><?= $title ?></h4>

    <?php $form = ActiveForm::begin(); ?>
    <?= $form->field($model, 'sourceUserGuid')->widget(UserPickerField::class) ?>
    <?= $form->field($model, 'targetUserGuid')->widget(UserPickerField::class) ?>
    <?= $form->field($model, 'moveProfileContent')->checkbox() ?>
    <?= Button::save()->submit() ?>
    <?php ActiveForm::end(); ?>
</div>