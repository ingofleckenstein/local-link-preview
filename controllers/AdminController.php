<?php

namespace humhub\modules\localLinkPreview\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\localLinkPreview\models\LinkPreview;
use humhub\modules\localLinkPreview\models\SettingsForm;
use Yii;

class AdminController extends Controller
{
    public function actionIndex()
    {
        $settings = $this->module->settings;
        $model = new SettingsForm([
            'enabled' => (bool) $settings->get('enabled', true),
            'cacheDays' => (int) $settings->get('cacheDays', 7),
            'maxImageMb' => (int) $settings->get('maxImageMb', 5),
            'maxPreviews' => (int) $settings->get('maxPreviews', 1),
            'blockedDomains' => (string) $settings->get('blockedDomains', ''),
        ]);
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            foreach (['enabled', 'cacheDays', 'maxImageMb', 'maxPreviews', 'blockedDomains'] as $attribute) {
                $settings->set($attribute, $model->$attribute);
            }
            $this->view->saved();
        }
        return $this->render('index', ['model' => $model]);
    }

    public function actionClearCache()
    {
        LinkPreview::deleteAll();
        $directory = \humhub\modules\localLinkPreview\services\PreviewFetcher::imageDirectory();
        if (is_dir($directory)) {
            \yii\helpers\FileHelper::removeDirectory($directory);
        }
        $this->view->success('Der Linkvorschau-Cache wurde geleert.');
        return $this->redirect(['index']);
    }
}
