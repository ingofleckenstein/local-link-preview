<?php

namespace humhub\modules\localLinkPreview\controllers;

use humhub\components\Controller;
use humhub\modules\localLinkPreview\models\LinkPreview;
use humhub\modules\localLinkPreview\models\LinkPreviewSelection;
use humhub\modules\localLinkPreview\services\PreviewFetcher;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use Throwable;

class PreviewController extends Controller
{
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [['allow' => true, 'roles' => ['@']]],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['fetch' => ['POST'], 'image' => ['GET']],
            ],
        ]);
    }

    public function actionFetch(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $url = trim((string) Yii::$app->request->post('url', ''));
        $postId = (int) Yii::$app->request->post('postId', 0);
        if ($postId > 0) {
            $selection = LinkPreviewSelection::findOne(['post_id' => $postId]);
            if ($selection) {
                if ($selection->url === '') {
                    return ['disabled' => true];
                }
                $url = $selection->url;
            }
        }
        if ($url === '' || strlen($url) > 2048) {
            throw new BadRequestHttpException('Ungültige URL.');
        }

        try {
            $model = (new PreviewFetcher())->get($url);
        } catch (Throwable $e) {
            Yii::warning('Linkvorschau fehlgeschlagen: ' . $e->getMessage(), 'local-link-preview');
            throw new BadRequestHttpException('Für diesen Link konnte keine Vorschau erstellt werden.');
        }
        return [
            'url' => $model->url,
            'title' => $model->title,
            'description' => $model->description,
            'siteName' => $model->site_name,
            'imageUrl' => $model->image_file
                ? Yii::$app->urlManager->createUrl(['/local-link-preview/preview/image', 'id' => $model->id])
                : null,
            'hideImage' => isset($selection) && (bool) $selection->hide_image,
        ];
    }

    public function actionImage(int $id): Response
    {
        $model = LinkPreview::findOne($id);
        $path = $model ? PreviewFetcher::imageDirectory() . DIRECTORY_SEPARATOR . $model->image_file : null;
        if (!$model || !$model->image_file || !is_file($path)) {
            throw new NotFoundHttpException();
        }

        Yii::$app->response->headers->set('Cache-Control', 'private, max-age=86400');
        return Yii::$app->response->sendFile($path, null, ['mimeType' => $model->image_mime, 'inline' => true]);
    }
}
