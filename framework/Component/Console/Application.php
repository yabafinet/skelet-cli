<?php

    namespace Framework\Component\Console;

    use Symfony\Component\Console\Command\Command;
    use Framework\Component\Console\SkeletCli\InitCommand;
    use Symfony\Component\Console\Application as ApplicationBase;
    use Framework\Contracts\Console\Application as ApplicationContractsBase;


    class Application implements ApplicationContractsBase
    {
        public  $application;
        public  $framework;
        public  $bootComponents;
        private $registered_commands = array();
        /**
         * @var InitCommand;
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

            if(! isset($_SERVER['CONSOLE_TYPE'])) {
                $_SERVER['CONSOLE_TYPE'] = 'local';
            }
            $this->loadRegisteredCommands();
        }

        function loadRegisteredCommands()
        {

            $this->registered_commands = require base_path()."/config/commands/commands.local.php";

            if($_SERVER['CONSOLE_TYPE'] =='remote') {

                $remote_commands       = require base_path()."/config/commands/commands.remote.php";
                $this->registered_commands['remote_commands'] = $remote_commands['commands'];
            }

        }


        function getConfig()
        {
            //return $this
        }

        /** */
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

        /** */
        function registerConfiguredCommands()
        {
            foreach ($this->registered_commands['commands'] as $command=>$config)
            {
                $instanceCommand = new $command();

                if ($instanceCommand instanceof InitCommand) {
                    $this->setFrameServerStartCommand($instanceCommand);
                }

                $this->add($instanceCommand);
            }

            if($_SERVER['CONSOLE_TYPE'] =='remote') {
                $this->registerConfiguredFrameServerCommands();
            }

            $this->registerInternalsSkeletCommands();
        }

        /**  */
        function registerConfiguredFrameServerCommands()
        {
            foreach ($this->registered_commands['remote_commands'] as $command=>$config) {
                $this->add(new $command($this->start_command));
            }
        }

        /**
         * Registrando comandos internos de Skelet Framework.
         * estos comandos solo serán utilizados para procesos
         * de división de códigos para su distribución en los
         * diferentes repositorios de skelet.
         *
         * @return bool
         */
        function registerInternalsSkeletCommands()
        {
            $file_commands = base_path()."/config/commands/commands.skelet.php";

            if (! file_exists($file_commands)) {
                return false;
            }

            $commands = require $file_commands;

            foreach ($commands['commands'] as $command=>$config) {
                $this->add(new $command($this->start_command));
            }

            return true;
        }

        function setFrameServerStartCommand($start_command)
        {
            $this->start_command = $start_command;
        }
    }