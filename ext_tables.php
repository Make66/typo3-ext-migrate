<?php
defined('TYPO3') || die();

(static function() {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
        'Migrate',
        'tools',
        'mod1',
        '',
        [
            \Taketool\Migrate\Controller\Mod1Controller::class => 'index',

        ],
        [
            'access' => 'user,group',
            'icon'   => 'EXT:migrate/Resources/Public/Icons/user_mod_mod1.svg',
            'labels' => 'LLL:EXT:migrate/Resources/Private/Language/locallang_mod1.xlf',
        ]
    );

})();
