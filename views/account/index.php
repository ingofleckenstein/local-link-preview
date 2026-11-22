<?php

use humhub\widgets\form\ActiveForm;
use yii\helpers\Html;

/** @var \humhub\modules\localLinkPreview\models\PreferencesForm $model */
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Linkvorschauen</strong></div>
    <div class="panel-body">
        <p>Geteilte Links können mit Bild, Titel und Kurzbeschreibung angezeigt werden. Diese Einstellung gilt nur für dich.</p>
        <?php $form = ActiveForm::begin(['id' => 'link-preview-preferences', 'acknowledge' => true]); ?>
        <?= $form->field($model, 'showPreviews')->checkbox()->hint('Wenn du Linkvorschauen ausschaltest, bleiben die Links anklickbar.'); ?>
        <?= Html::submitButton('Speichern', ['class' => 'btn btn-primary', 'data-ui-loader' => '']); ?>
        <?php ActiveForm::end(); ?>
    </div>
</div>
