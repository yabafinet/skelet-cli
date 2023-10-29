<?php


    return [
        'commands'=> [
            Framework\Component\Console\SkeletCli\InitCommand::class =>[
                'workspace'=>['dev','master']
            ],
            Framework\Component\Console\SkeletCli\LocalCommands\CreateControllerCommand::class =>[
                'workspace'=>['dev']
            ],
            Framework\Component\Console\SkeletCli\LocalCommands\SyncLocalRemote::class =>[
                'workspace'=>['dev']
            ],
            \App\Console\Commands\LoadStructureFileAdessCommand::class =>[
                'workspace'=>['dev']
            ]
        ]
    ];