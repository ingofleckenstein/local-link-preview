<?php

namespace humhub\modules\localLinkPreview\models;

use yii\db\ActiveRecord;

class LinkPreviewSelection extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%local_link_preview_selection}}';
    }
}
