<?php
return [
    'migrate:cal' => [
        'class' => \Taketool\Migrate\Command\Cal2DatetimeCommand::class,
    ],
    'migrate:flexforms' => [
        'class' => \Taketool\Migrate\Command\FlexFormsCommand::class,
    ],
    'migrate:tca' => [
        'class' => \Taketool\Migrate\Command\TcaCommand::class,
    ],
    'migrate:templates' => [
        'class' => \Taketool\Migrate\Command\TemplatesCommand::class,
    ],
];
