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


    public function actionIndex()
    {
        $model = new MoveUserContentModel();
        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->save()) {
            $this->view->success(Yii::t('MoveContentModule.base', 'The user content transfer has been added to the queue'));
            $model = new MoveUserContentModel(); // Reset field values
        }

        $title = Yii::t('MoveContentModule.base', 'Transfer the content from a user to another');
        $this->subLayout = '@admin/views/layouts/user';
        $this->appendPageTitle($title);
        return $this->render('index', [
            'title' => $title,
            'model' => $model,
        ]);
    }
}
