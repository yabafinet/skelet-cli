<?php


    return [
        'commands'=> [
            Framework\Component\Console\SfBuild\StartCommand::class =>[
                'workspace'=>['dev','master']
            ],
            Framework\Component\Console\SfBuild\LocalCommands\CreateControllerCommand::class =>[
                'workspace'=>['dev']
            ],
            Framework\Component\Console\SfBuild\LocalCommands\SyncLocalRemote::class =>[
                'workspace'=>['dev']
            ]
        ]
    ];