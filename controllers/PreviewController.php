<?php

namespace humhub\modules\localLinkPreview\controllers;

use humhub\components\Controller;
use humhub\modules\localLinkPreview\models\LinkPreview;
use humhub\modules\localLinkPreview\models\LinkPreviewSelection;
use humhub\modules\localLinkPreview\services\PreviewFetcher;
use humhub\modules\localLinkPreview\services\PreviewPolicy;
use humhub\modules\post\models\Post;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
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
                'actions' => ['fetch' => ['POST'], 'image' => ['GET'], 'preference' => ['POST'], 'selection' => ['POST']],
            ],
        ]);
    }

    public function actionFetch(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        if (!$this->module->settings->get('enabled', true) || !$this->module->settings->user()->get('showPreviews', true)) {
            return ['disabled' => true];
        }
        $url = trim((string) Yii::$app->request->post('url', ''));
        $postId = (int) Yii::$app->request->post('postId', 0);
        if ($postId > 0) {
            $post = Post::findOne($postId);
            if (!$post || !$post->content->canView()) { throw new NotFoundHttpException(); }
            $selection = LinkPreviewSelection::findOne(['post_id' => $postId]);
            if ($selection && $selection->url !== '' && PreviewPolicy::isInternalProfile($selection->url)) { $selection = null; }
            if ($selection) {
                if ($selection->url === '') {
                    return ['disabled' => true, 'canEdit' => $post->content->canEdit()];
                }
                $url = $selection->url;
            }
        }
        if (isset($post) && !PreviewPolicy::appearsInMessage($url, (string) $post->message)) {
            throw new BadRequestHttpException('Der Link gehört nicht zu diesem Beitrag.');
        }
        if (PreviewPolicy::isInternalProfile($url)) { return ['disabled' => true]; }
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
            'canEdit' => isset($post) && $post->content->canEdit(),
        ];
    }

    public function actionPreference(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $enabled = Yii::$app->request->post('enabled');
        if (!in_array($enabled, ['0', '1'], true)) { throw new BadRequestHttpException(); }
        $this->module->settings->user()->set('showPreviews', $enabled === '1');
        return ['enabled' => $enabled === '1'];
    }

    public function actionSelection(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $post = Post::findOne((int) Yii::$app->request->post('postId'));
        if (!$post || !$post->content->canView()) { throw new NotFoundHttpException(); }
        if (!$post->content->canEdit()) { throw new ForbiddenHttpException(); }
        $disabled = Yii::$app->request->post('disabled');
        if (!in_array($disabled, ['0', '1'], true)) { throw new BadRequestHttpException(); }
        if ($disabled === '1') {
            $selection = LinkPreviewSelection::findOne(['post_id' => $post->id]) ?: new LinkPreviewSelection();
            $selection->post_id = $post->id;
            $selection->url = '';
            $selection->hide_image = false;
            $selection->save(false);
        } else {
            LinkPreviewSelection::deleteAll(['post_id' => $post->id]);
        }
        return ['disabled' => $disabled === '1'];
    }

    public function actionImage(int $id): Response
    {
        if (!$this->module->settings->get('enabled', true) || !$this->module->settings->user()->get('showPreviews', true)) {
            throw new NotFoundHttpException();
        }
        $model = LinkPreview::findOne($id);
        $path = $model ? PreviewFetcher::imageDirectory() . DIRECTORY_SEPARATOR . $model->image_file : null;
        if (!$model || PreviewPolicy::isInternalProfile($model->url) || !$model->image_file || !is_file($path)) {
            throw new NotFoundHttpException();
        }

        Yii::$app->response->headers->set('Cache-Control', 'private, max-age=86400');
        return Yii::$app->response->sendFile($path, null, ['mimeType' => $model->image_mime, 'inline' => true]);
    }
}
