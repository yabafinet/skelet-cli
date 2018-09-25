<?php

    namespace Framework\Component\Console\SkeletCli;


    use phpseclib\Net\SSH2;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    class Authentication implements FrameServerCommandInterface
    {

        /** @var InputInterface */
        public $input;
        /** @var OutputInterface */
        public $output;

        public $command;

        public $ssh;

        public $is_login_ok = false;

        public $repo_manager;

        private $password;

        public function __construct(InitCommand $command_base)
        {
            $this->input        = $command_base->input;
            $this->output       = $command_base->output;
            $this->command      = $command_base;
            //$this->repo_manager = Git::singleton($this->input,$this->output);

        }


        public function login()
        {
            $password = $this->command->question('Password:',true);

            if ($password) {
                $this->command->repo_name = $this->command->username;
                $this->connectRemoteServer($this->command->repo_name, $password);
            }
        }


        /**
         * Conectar via ssh.
         *
         * @param $user
         * @param $password
         */
        function connectRemoteServer($user, $password)
        {
            $this->ssh = new SSH2($this->command->config['server']['host']);

            if(! $this->ssh->login($user, $password)) {

                $this->command->error('Autenticación fallida para '.$user);
                $this->login();

            } else {

                $this->command->server = $this->ssh;
                $this->setPassword($password);

                // Reportando al manejador de proyecto
                // la coneccion desde skelet-cli local.
                $this->command->executeRemoteCommand('project init-connection');
                $this->command->go();
            }
        }

        /**
         * @return mixed
         */
        public function getPassword()
        {
            return $this->password;
        }

        /**
         * @param mixed $password
         */
        public function setPassword($password)
        {
            $this->password = $password;
        }
    }