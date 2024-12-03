<?php

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['migrate_imageToTextMedia']
    = \Taketool\Migrate\Updates\V76\ImageToTextMediaUpdate::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['migrate_mediaToAssetsForTextMediaCe']
    = \Taketool\Migrate\Updates\V76\MigrateMediaToAssetsForTextMediaCe::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['migrate_textpicToTextMedia']
    = \Taketool\Migrate\Updates\V76\TextpicToTextMediaUpdate::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['migrate_textToTextMedia']
    = \Taketool\Migrate\Updates\V76\TextToTextMediaUpdate::class;
