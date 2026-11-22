<?php

namespace humhub\modules\localLinkPreview\controllers;

use humhub\modules\localLinkPreview\models\PreferencesForm;
use humhub\modules\user\components\BaseAccountController;
use Yii;

class AccountController extends BaseAccountController
{
    public function actionIndex()
    {
        $this->appendPageTitle('Linkvorschauen');
        $model = new PreferencesForm();
        $model->showPreviews = (bool) $this->module->settings->user()->get('showPreviews', true);
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $this->module->settings->user()->set('showPreviews', (bool) $model->showPreviews);
            $this->view->saved();
            return $this->redirect(['index']);
        }
        return $this->render('index', ['model' => $model]);
    }
}
