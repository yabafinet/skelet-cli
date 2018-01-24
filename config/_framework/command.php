<?php


    return [
        'commands'=> [
            Framework\Component\Console\SfBuild\StartCommand::class =>[
                'workspace'=>['dev','master']
            ],
            Framework\Component\Console\SfBuild\LocalCommands\CreateControllerCommand::class =>[
                'workspace'=>['dev']
            ],
        ],
        'remote_commands'=>[
            Framework\Component\Console\SfBuild\RemoteCommands\GitCommands::class =>[
                'workspace'=>['dev']
            ],
            Framework\Component\Console\SfBuild\RemoteCommands\SfbuildCommands::class =>[
                'workspace'=>['dev']
            ],
            Framework\Component\Console\SfBuild\LocalCommands\SyncLocalRemote::class =>[
                'workspace'=>['dev']
            ],
            Framework\Component\Console\SfBuild\RemoteCommands\WorkspaceCommand::class =>[
                'workspace'=>['dev']
            ]
        ],
    ];