<?php

    namespace Framework\Component\Console;

    use Symfony\Component\Console\Command\Command;
    use Framework\Component\Console\SfBuild\StartCommand;
    use Symfony\Component\Console\Application as ApplicationBase;
    use Framework\Contracts\Console\Application as ApplicationContractsBase;


    class Application implements ApplicationContractsBase
    {
        public  $application;
        public  $framework;
        public  $bootComponents;
        private $registered_commands = array();
        /**
         * @var StartCommand;
         */
        public  $start_command;

        /**
         * Application constructor.
         *
         * @param ApplicationBase $application
         * @internal param App $app
         */
        function __construct(ApplicationBase $application)
        {
            $this->application  = $application;

            //$this->framework    = new App();
            //$this->framework->setTypeApplication('console');
            //$this->framework->setSingleton();
            //$this->framework->buildFromConsoleApplication();

            if(! isset($_SERVER['CONSOLE_TYPE'])) {
                $_SERVER['CONSOLE_TYPE'] = 'local';
            }
            $this->loadRegisteredCommands();
        }

        function loadRegisteredCommands()
        {

            $this->registered_commands = require_once base_path()."/config/commands/commands.local.php";

            if($_SERVER['CONSOLE_TYPE'] =='remote') {

                $remote_commands           = require_once base_path()."/config/commands/commands.remote.php";
                $this->registered_commands['remote_commands'] = $remote_commands['commands'];
            }

        }


        function getConfig()
        {
            //return $this
        }

        /**
         *
         */
        function run()
        {

            try {
                $this->application->run();

            } catch (\Exception $e) {
            }
        }

        /**
         *
         * @param Command $command
         */
        function add(Command $command)
        {
            $this->application->add($command);
        }

        /**
         *
         */
        function registerConfiguredCommands()
        {
            foreach ($this->registered_commands['commands'] as $command=>$config)
            {
                $instanceCommand = new $command();

                if ($instanceCommand instanceof StartCommand) {
                    $this->setFrameServerStartCommand($instanceCommand);
                }

                $this->add($instanceCommand);
            }

            if($_SERVER['CONSOLE_TYPE'] =='remote') {
                $this->registerConfiguredFrameServerCommands();
            }

        }

        /**
         *
         */
        function registerConfiguredFrameServerCommands()
        {
            foreach ($this->registered_commands['remote_commands'] as $command=>$config) {
                $this->add(new $command($this->start_command));
            }
        }

        function setFrameServerStartCommand($start_command)
        {
            $this->start_command = $start_command;
        }
    }