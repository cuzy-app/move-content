<?php
/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

use humhub\modules\admin\widgets\AdminMenu;
use humhub\modules\moveContent\models\MoveUsersModel;
use humhub\widgets\form\ActiveForm;
use humhub\components\View;
use humhub\modules\user\models\Group;
use humhub\widgets\bootstrap\Button;
use yii\helpers\ArrayHelper;

/**
 * @var $this View
 * @var $model MoveUsersModel
 * @var $title string
 */

AdminMenu::markAsActive(['/admin/user']);

$groupItems = ArrayHelper::map(Group::find()->where(['is_admin_group' => 0])->all(), 'id', 'name');
?>

<div class="panel-body">
    <h4><?= $title ?></h4>
    <br>

    <?php $form = ActiveForm::begin(); ?>
    <?= $form->field($model, 'sourceGroupId')->dropDownList($groupItems) ?>
    <?= $form->field($model, 'targetGroupId')->dropDownList($groupItems) ?>
    <?= Button::save()->submit() ?>
    <?php ActiveForm::end(); ?>
</div>
