<?php

namespace humhub\modules\localLinkPreview\assets;

use yii\web\AssetBundle;

class PreviewAsset extends AssetBundle
{
    public $css = ['css/link-preview.css'];
    public $js = ['js/link-preview.js'];
    public $jsOptions = ['position' => \yii\web\View::POS_END];
    public $depends = ['humhub\\assets\\AppAsset'];

    public function init(): void
    {
        // Use the physical module path. Event handlers can run before the module
        // instance has been created, so a module-defined Yii alias is unsafe here.
        $this->sourcePath = dirname(__DIR__) . '/resources';
        parent::init();
    }
}
