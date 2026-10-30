<?php
// SPDX-License-Identifier: MIT
$root = $argv[1] ?? getenv('HUMHUB_ROOT');
if (!$root || !is_file($root . '/protected/yii')) { throw new RuntimeException('Provide a test HumHub root.'); }
$protected = $root . '/protected';
$loader = require $protected . '/vendor/autoload.php';
Dotenv\Dotenv::createMutable($root, '.env')->safeLoad();
$loader->addClassMap(['humhub\\services\\BootstrapService' => $protected . '/humhub/services/BootstrapService.php']);
define('YII_DEBUG', false); define('YII_ENV', 'prod');
require $protected . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@humhub', $protected . '/humhub');
$_SERVER['REQUEST_URI'] = '/';
$config = (new humhub\services\BootstrapService())->getConfig('web');
$config['components']['request']['scriptFile'] = $root . '/index.php';
$config['components']['request']['scriptUrl'] = '/index.php';
$config['components']['request']['hostInfo'] = 'http://localhost';
new humhub\components\Application($config);

use humhub\modules\localLinkPreview\controllers\PreviewController;
use humhub\modules\localLinkPreview\models\LinkPreviewSelection;
use humhub\modules\localLinkPreview\services\PreviewFetcher;
use humhub\modules\post\models\Post;
function checkPreview(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } echo 'PASS ' . $label . PHP_EOL; }
$module = Yii::$app->getModule('local-link-preview');
$users = humhub\modules\user\models\User::find()->where(['status' => 1])->orderBy(['id' => SORT_ASC])->limit(3)->all();
$editable = null; $reader = null; $protected = null;
foreach ($users as $user) {
    Yii::$app->user->setIdentity($user);
    foreach (Post::find()->orderBy(['id' => SORT_ASC])->limit(100)->all() as $post) {
        if (!$editable && $post->content->canView($user) && $post->content->canEdit()) { $editable = [$user, $post]; }
        if (!$reader && $post->content->canView($user) && !$post->content->canEdit()) { $reader = [$user, $post]; }
        if (!$protected && !$post->content->canView($user)) { $protected = [$user, $post]; }
    }
}
checkPreview($editable !== null && $reader !== null && $protected !== null, 'existing accounts cover author, reader and forbidden post');
$controller = new PreviewController('preview', $module); Yii::$app->controller = $controller;
[$user, $post] = $reader; Yii::$app->user->setIdentity($user);
Yii::$app->request->setBodyParams(['postId' => (string) $post->id, 'disabled' => '1']);
$denied = false; try {$controller->actionSelection();} catch (yii\web\ForbiddenHttpException $e) {$denied = true;}
checkPreview($denied, 'reader cannot remove preview for everyone');
[$user, $post] = $protected; Yii::$app->user->setIdentity($user);
$originalPreference = $module->settings->user()->get('showPreviews');
try {
    $module->settings->user()->set('showPreviews', true);
    Yii::$app->request->setBodyParams(['postId' => (string) $post->id, 'url' => 'https://outside.example/']);
    $denied = false; try {$controller->actionFetch();} catch (yii\web\NotFoundHttpException $e) {$denied = true;}
    checkPreview($denied, 'private post selection cannot be read');
} finally {if ($originalPreference === null) {$module->settings->user()->delete('showPreviews');} else {$module->settings->user()->set('showPreviews', $originalPreference);}}
[$user, $post] = $editable; Yii::$app->user->setIdentity($user);
$originalPreference = $module->settings->user()->get('showPreviews');
$transaction = Yii::$app->db->beginTransaction();
try {
    Yii::$app->request->setBodyParams(['enabled' => '0']); checkPreview($controller->actionPreference()['enabled'] === false, 'personal off saved');
    Yii::$app->request->setBodyParams(['url' => 'https://outside.example/']); checkPreview($controller->actionFetch()['disabled'] === true, 'personal off prevents fetching');
    Yii::$app->request->setBodyParams(['enabled' => '1']); $controller->actionPreference();
    Yii::$app->request->setBodyParams(['url' => 'http://localhost/u/member/']); checkPreview($controller->actionFetch()['disabled'] === true, 'internal profile excluded server-side');
    Yii::$app->request->setBodyParams(['postId' => (string) $post->id, 'disabled' => '1']); $controller->actionSelection();
    checkPreview(LinkPreviewSelection::findOne(['post_id' => $post->id])->url === '', 'author can disable a published preview');
    Yii::$app->request->setBodyParams([]); humhub\modules\localLinkPreview\Events::onPostSave(new yii\base\Event(['sender' => $post]));
    checkPreview(LinkPreviewSelection::findOne(['post_id' => $post->id])->url === '', 'text editing preserves disabled preview');
    Yii::$app->request->setBodyParams(['postId' => (string) $post->id, 'disabled' => '0']); $controller->actionSelection();
    checkPreview(LinkPreviewSelection::findOne(['post_id' => $post->id]) === null, 'author can reenable preview');
} finally {
    $transaction->rollBack();
    if ($originalPreference === null) {$module->settings->user()->delete('showPreviews');} else {$module->settings->user()->set('showPreviews', $originalPreference);}
}
if ($url = getenv('PREVIEW_TEST_URL')) {
    $model = (new PreviewFetcher())->get($url);
    checkPreview((bool) $model->image_file && is_file(PreviewFetcher::imageDirectory() . '/' . $model->image_file), 'external site image fetched and cached locally');
}
