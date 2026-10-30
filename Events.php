<?php

namespace humhub\modules\localLinkPreview;

use humhub\modules\localLinkPreview\assets\PreviewAsset;
use humhub\modules\localLinkPreview\models\LinkPreviewSelection;
use humhub\modules\post\models\Post;
use humhub\modules\localLinkPreview\services\PreviewPolicy;
use Yii;
use yii\base\Event;
use yii\helpers\Url;
use yii\web\View;

class Events
{
    public static function onControllerBeforeAction(Event $event): void
    {
        $module = Yii::$app->getModule('local-link-preview');
        if (Yii::$app->user->isGuest || !Yii::$app->request->isGet || !$module->settings->get('enabled', true)) {
            return;
        }

        $view = Yii::$app->view;
        PreviewAsset::register($view);
        $config = [
            'endpoint' => Url::to(['/local-link-preview/preview/fetch']),
            'preferenceEndpoint' => Url::to(['/local-link-preview/preview/preference']),
            'selectionEndpoint' => Url::to(['/local-link-preview/preview/selection']),
            'showPreviews' => (bool) $module->settings->user()->get('showPreviews', true),
            'userId' => (int) Yii::$app->user->id,
            'enabled' => true,
            'maxPreviews' => (int) $module->settings->get('maxPreviews', 1),
            'csrfParam' => Yii::$app->request->csrfParam,
            'csrfToken' => Yii::$app->request->getCsrfToken(),
        ];
        $view->registerJs(
            'window.localLinkPreviewConfig = ' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';',
            View::POS_HEAD
        );
    }

    public static function onPostSave(Event $event): void
    {
        if (!($event->sender instanceof Post) || Yii::$app->request->isConsoleRequest) {
            return;
        }
        if (Yii::$app->request->post('localLinkPreviewUrl') === null) {
            $existing = LinkPreviewSelection::findOne(['post_id' => $event->sender->id]);
            if ($existing && $existing->url !== '' && !PreviewPolicy::appearsInMessage($existing->url, (string) $event->sender->message)) {
                $existing->delete();
            }
            return;
        }
        $url = trim((string) Yii::$app->request->post('localLinkPreviewUrl', ''));
        $disabled = $url === '__disabled__';
        if (!$disabled && ($url === '' || PreviewPolicy::isInternalProfile($url) || !PreviewPolicy::appearsInMessage($url, (string) $event->sender->message))) {
            LinkPreviewSelection::deleteAll(['post_id' => $event->sender->id]);
            return;
        }
        $selection = LinkPreviewSelection::findOne(['post_id' => $event->sender->id]) ?: new LinkPreviewSelection();
        $selection->post_id = $event->sender->id;
        $selection->url = $disabled ? '' : mb_substr($url, 0, 2048);
        $selection->hide_image = (bool) Yii::$app->request->post('localLinkPreviewHideImage', false);
        $selection->save(false);
    }
}
