<?php

namespace humhub\modules\localLinkPreview\models;

class PreferencesForm extends \yii\base\Model
{
    public $showPreviews = true;

    public function rules(): array
    {
        return [['showPreviews', 'boolean']];
    }

    public function attributeLabels(): array
    {
        return ['showPreviews' => 'Linkvorschauen anzeigen'];
    }
}
