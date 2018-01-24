<?php


    return [

        /**
         * Arranque de componentes de Skelet.
         */
        'components'=>[
            Framework\Component\Cache\CacheComponent::class=>[
                'type'=>['web','console']
            ],
            Framework\Component\Log\LogComponent::class=>[
                'type'=>['web','console']
            ],
            Framework\Component\Security\Encrypt\EncryptComponent::class=>[
                'type'=>['web','console']
            ],
            Framework\Component\Security\Firewall\FirewallComponent::class=>[
                'type'=>['web','console']
            ],
            Framework\Component\Security\CsrfProtectionComponent::class =>[
                'type'=>'web'
            ],
            Framework\Component\Security\Authentication\AuthenticationComponent::class=>[
                'type'=>'web'
            ],
            Framework\Component\Translation\TranslatorComponent::class=>[
                'type'=>['web']
            ],
            Framework\Component\Validation\ValidationComponent::class=>[
                'type'=>['web']
            ],
            Framework\Component\Security\Cookie\EncryptCookieComponent::class =>[
                'type'=>'web'
            ],
            Framework\Component\Debug\DebugComponent::class=>[
                'type'=>'web'
            ],
            Framework\Component\UserInterface\DebugBarComponent::class =>[
                'type'=>'web'
            ],
            Framework\Component\Database\DatabaseBootComponent::class=>[
                'type'=>'web'
            ],
            Framework\Component\UserInterface\UIComponent::class=>[
                'type'=>['web']
            ],
        ],


        'events'=>[
            'log'=>[
                'listeners'=>[
                    Framework\Component\Events\Logs\FrameworkInternalLogListener::class =>[
                        'levels'=> ['critical:10','emergency'], 'env'=>'dev'
                    ]
                ]
            ]
        ]

    ];