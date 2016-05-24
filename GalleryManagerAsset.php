<?php

namespace aquy\gallery;

use Yii;
use yii\web\AssetBundle;

class GalleryManagerAsset extends AssetBundle
{
    public $sourcePath = '@aquy/gallery/assets';
    public $js = [
        'jquery.iframe-transport.js',
        'jquery.galleryManager.js',
        // 'jquery.iframe-transport.min.js',
        // 'jquery.galleryManager.min.js',
    ];
    public $css = [
        'galleryManager.css'
    ];
    public $depends = [
        'yii\web\JqueryAsset',
        'yii\jui\JuiAsset'
    ];

}
