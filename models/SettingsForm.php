<?php

namespace humhub\modules\localLinkPreview\models;

use yii\base\Model;

class SettingsForm extends Model
{
    public bool $enabled = true;
    public int $cacheDays = 7;
    public int $maxImageMb = 5;
    public int $maxPreviews = 1;
    public string $blockedDomains = '';

    public function rules(): array
    {
        return [
            ['enabled', 'boolean'],
            [['cacheDays'], 'integer', 'min' => 1, 'max' => 90],
            [['maxImageMb'], 'integer', 'min' => 1, 'max' => 20],
            [['maxPreviews'], 'integer', 'min' => 1, 'max' => 3],
            ['blockedDomains', 'string', 'max' => 10000],
        ];
    }
}
