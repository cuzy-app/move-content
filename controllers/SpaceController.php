<?php
/**
 * Move content
 * @link https://www.cuzy.app
 * @license https://www.cuzy.app/cuzy-license
 * @author [Marc FARRE](https://marc.fun)
 */

namespace humhub\modules\moveContent\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\admin\permissions\ManageSpaces;
use humhub\modules\moveContent\models\MoveSpaceContentModel;
use Yii;


class SpaceController extends Controller
{
    /**
     * @inheritdoc
     */
    public function getAccessRules()
    {
        return [
            ['permission' => ManageSpaces::class]
        ];
    }


    public function actionContent()
    {
        $model = new MoveSpaceContentModel();
        if ($model->load(Yii::$app->request->post()) && $model->validate() && $model->save()) {
            $this->view->success(Yii::t('MoveContentModule.base', 'The space content transfer has been added to the queue'));
            $model = new MoveSpaceContentModel(); // Reset field values
        }

        $title = Yii::t('MoveContentModule.base', 'Move content and users from one space to another');
        $this->subLayout = '@admin/views/layouts/space';
        $this->appendPageTitle($title);

        return $this->render('content', [
            'title' => $title,
            'model' => $model,
        ]);
    }
}
