<?php

use humhub\modules\localLinkPreview\Events;
use humhub\components\Controller;
use humhub\modules\post\models\Post;

return [
    'id' => 'local-link-preview',
    'class' => 'humhub\\modules\\localLinkPreview\\Module',
    'namespace' => 'humhub\\modules\\localLinkPreview',
    'events' => [
        [
            'class' => Controller::class,
            'event' => Controller::EVENT_BEFORE_ACTION,
            'callback' => [Events::class, 'onControllerBeforeAction'],
        ],
        ['class' => Post::class, 'event' => Post::EVENT_AFTER_INSERT, 'callback' => [Events::class, 'onPostSave']],
        ['class' => Post::class, 'event' => Post::EVENT_AFTER_UPDATE, 'callback' => [Events::class, 'onPostSave']],
    ],
];
