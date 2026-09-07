<?php

use humhub\libs\Html;
use humhub\widgets\Button;
use humhub\widgets\form\ActiveForm;

/** @var \humhub\modules\localLinkPreview\models\SettingsForm $model */
?>
<div class="panel panel-default">
    <div class="panel-heading"><strong>Local Link Preview</strong></div>
    <div class="panel-body">
        <?php $form = ActiveForm::begin(); ?>
        <?= $form->field($model, 'enabled')->checkbox()->label('Linkvorschauen aktivieren') ?>
        <?= $form->field($model, 'cacheDays')->input('number')->label('Cache-Dauer in Tagen') ?>
        <?= $form->field($model, 'maxImageMb')->input('number')->label('Maximale Bildgröße in MB') ?>
        <?= $form->field($model, 'maxPreviews')->dropDownList([1 => '1', 2 => '2', 3 => '3'])->label('Maximale Vorschauen pro Beitrag') ?>
        <?= $form->field($model, 'blockedDomains')->textarea(['rows' => 6, 'placeholder' => "example.org\ntracking.example"])->label('Gesperrte Domains – eine pro Zeile') ?>
        <?= Button::save()->submit() ?>
        <?= Html::a('Cache leeren', ['clear-cache'], ['class' => 'btn btn-danger', 'data-method' => 'post', 'data-confirm' => 'Linkvorschau-Cache wirklich leeren?']) ?>
        <?php ActiveForm::end(); ?>
    </div>
</div>
