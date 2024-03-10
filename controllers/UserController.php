<?php
/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\admin\permissions\ManageUsers;
use humhub\modules\moveContent\models\MoveUserContentModel;
use humhub\modules\moveContent\models\MoveUsersModel;
use Yii;


class UserController extends Controller
{
    /**
     * @inheritdoc
     */
    public function getAccessRules()
    {
        return [
            ['permission' => ManageUsers::class]
        ];
    }

    public function actionContent()
    {
        $model = new MoveUserContentModel();

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->save()) {
            $this->view->success(Yii::t('MoveContentModule.base', 'The user content transfer has been added to the queue'));
            $model = new MoveUserContentModel(); // Reset field values
        }

        $title = Yii::t('MoveContentModule.base', 'Move content from one user to another');
        $this->subLayout = '@admin/views/layouts/user';
        $this->appendPageTitle($title);

        return $this->render('content', [
            'title' => $title,
            'model' => $model,
        ]);
    }

    public function actionGroup()
    {
        $model = new MoveUsersModel();

        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->save()) {
            $this->view->success(Yii::t('MoveContentModule.base', 'The group users transfer has been added to the queue'));
            $model = new MoveUsersModel(); // Reset field values
        }

        $title = Yii::t('MoveContentModule.base', 'Move users from one group to another');
        $this->subLayout = '@admin/views/layouts/user';
        $this->appendPageTitle($title);

        return $this->render('group', [
            'title' => $title,
            'model' => $model,
        ]);
    }
}
