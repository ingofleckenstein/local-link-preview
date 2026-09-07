<?php

namespace humhub\modules\localLinkPreview;

use humhub\components\Module as BaseModule;
use yii\helpers\Url;

class Module extends BaseModule
{
    public $controllerNamespace = 'humhub\\modules\\localLinkPreview\\controllers';

    public function getName(): string
    {
        return 'Local Link Preview';
    }

    public function getDescription(): string
    {
        return 'Erzeugt Linkvorschauen und liefert Vorschaubilder ausschließlich lokal aus.';
    }

    public function getConfigUrl(): string
    {
        return Url::to(['/local-link-preview/admin']);
    }
}
