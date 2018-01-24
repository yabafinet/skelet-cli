<?php

    namespace Framework\Component\Console\SfBuild;

    use phpseclib\Net\SSH2;
    use Illuminate\Support\Str;
    use Framework\Configurations;
    use Symfony\Component\Process\Process;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Input\StringInput;
    use Symfony\Component\Console\Question\Question;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;


    class StartCommand extends Command
    {
        /** @var InputInterface */
        public $input;
        /** @var OutputInterface */
        public $output;

        static public $tag = 'sfbuild';

        public $config = array();

        /** @var  Authentication */
        public $auth;

        public $last_response;

        /**
         * Redirección comandos al entorno remoto.
         *
         * @var array
         */
        public $redirect_to_remote = [
          'git','sfb'
        ];
        /**
         * @var SSH2;
         */
        public $server;

        public $repo_name;

        public $e_patch;

        public $e_version;
        /** @var  Command */
        public $last_remote_command;

        public $process = [];

        public $execute_in_remote_patch;

        public $username;

        private $password;

        protected function configure()
        {
            $this
                ->setName('init')
                ->addArgument('username', InputArgument::OPTIONAL, 'Nombre de usuario.')
                ->addOption('--init','-i',InputOption::VALUE_OPTIONAL,'Reconstruir la celula.')
                ->setDescription('Autenticación de usuarios de espacios de trabajo.')
                ->setHelp(''."\n")
            ;

            $this->getConfigurationStation();

        }

        protected function execute(InputInterface $input, OutputInterface $output)
        {
            $this->input        = $input;
            $this->output       = $output;

            if ($this->input->getArgument('username')) {
                $this->username = $this->input->getArgument('username');
            } else {
                $this->username = $this->config['server']['user'];
            }

            $this->welcome();
            $this->initializeFrameServer();
        }


        function getConfigurationStation()
        {
            $this->config   = Configurations::yml('_sfbuild/workspace.');
            $this->e_version= $this->config['sfbuild']['evolution_version'];
            $this->e_patch  = $this->config['sfbuild']['evolution_path'].'/master/'.$this->e_version;
        }

        function getWorkspaceType()
        {
            return $this->config['type'];
        }

        function initializeFrameServer()
        {
            $this->auth =  new Authentication($this);
            $this->auth->login();
        }


        function welcome()
        {
            $this->output->writeln('<fg=black;bg=magenta> sfbuild </><fg=black;bg=cyan> v1.0 by Ing. Yadel Batista.</>');
            $this->output->writeln('<fg=black;bg=magenta> sfbuild </><fg=black;bg=cyan> '.$this->username.' ==>> '.$this->config['server']['host'].' </>');
        }

        static function tag()
        {
           return '<fg=black;bg=cyan> '.static::$tag.' </>';
        }

        /**
         *
         */
        function go()
        {
            $response = $this->question(":");
            $this->ifCommand($response);
        }

        /**
         *
         * @param $command
         */
        function ifCommand($command)
        {
            $input      = new StringInput($command);
            $command_str= $input->getFirstArgument();

            if($command_str =='sfb-init')
            {
                // Configuración al iniciar un workspace.
                $this->sfbInit();

            }elseif($command_str =='ps')
            {
                $this->psProcessCommand($input);

            }elseif($this->isRedirectToRemote($command_str))
            {
                $this->sfbCommand($input);

            }else{

                try {
                    $this
                        ->getApplication()
                        ->find($command_str)
                        ->run($input, $this->output);

                    $this->go();

                } catch (\Exception $e) {

                    $this->error( $e->getMessage() );
                    $this->go();
                }
            }

        }



        function isRedirectToRemote($command)
        {
             if(in_array($command, $this->redirect_to_remote))
                 return true;
             else
                 return false;
        }


        /**
         * @param             $question
         * @param bool        $hidden
         * @param bool|string $report_to_last_command Reportar la respuesta al ultimo comando.
         * @return string
         * @internal param \Closure|null $closure
         */
        function question($question, $hidden = false, $report_to_last_command = false)
        {
            $helper   = $this->getHelper('question');
            $question = new Question($this->tag()." ". $question. " ", false);


            if($hidden) {
                $question->setHidden($hidden);
                $question->setHiddenFallback(false);
            }else{
                $bundles = array('AcmeDemoBundle', 'AcmeBlogBundle', 'AcmeStoreBundle');
                $question->setAutocompleterValues($bundles);
            }


            $this->questionResponse($question);
            $response = $helper->ask($this->input, $this->output, $question);

            if($report_to_last_command)
            {
                $last_command = $this->getLastRemoteCommand().' --ask="'.$response.'" ';
                $this->sfbCommand($last_command);
            }


            return $response;
        }

        /**
         *
         * @param $question
         * @return mixed
         */
        function questionResponse($question){ }


        function error($text)
        {
            return $this->output->writeln($this->tag().'<fg=red;options=bold> '.$text.' </>');
        }

        function info($text)
        {
            return $this->output->writeln($this->tag().'<fg=blue;options=bold> '.$text.' </>');
        }

        function message($text)
        {
            return $this->output->writeln($this->tag().'<fg=blue> '.$text.' </>');
        }

        function sfbInit()
        {
            $this->sfbCommand('sfb build --ask=Y', $this->e_patch);
        }

        function getLastRemoteCommand()
        {
            return $this->last_remote_command;
        }


        /**
         * Ejecutando comandos remotos.
         *
         * @param      $command
         * @param null $exe_in_path
         */
        function sfbCommand($command, $exe_in_path = null)
        {

            $this->last_remote_command  = $command;

            $cells_path   = Utilities::local()->getUserPath( $this->username );

            if ($exe_in_path) {
                $execute_in_path = $exe_in_path;

            } else {
                $execute_in_path = $cells_path;
            }

            $command   = 'export CONSOLE_TYPE="remote"; php '.$execute_in_path.'/sfbuild.php '.$command;
            $result    = $this->server->exec($command);

            //d($result);

            Utilities::remote()->executeRemote($result,$this);

            $this->go();
        }


        /**
         * Ejecutar procesos en background.
         *
         * @return bool
         */
        function psExecuteProcessBackground()
        {

            if (! $this->psStatusProcess('sync')) {
                $this->process['sync'] = new Process(
                    'php '.base_path().'/sfbuild.php sync '.$this->username.'.'.$this->auth->getPassword().' > /dev/null 2>&1 &'
                );
                $this->process['sync']->run();
                $this->info('sync process started...');
            }

            return true;
        }


        function createIfNotExistRepoUser(){ }

        /**
         * Verificar el estado de proceso ejecutado en
         * 2do plano.
         *
         * @param $process_name
         * @return bool
         */
        function psStatusProcess($process_name)
        {
            $ps = new Process('ps aux | grep php');
            $ps->run();
            $ps_result =  $ps->getOutput();


            if (Str::contains($ps_result,'sync '.$this->username)) {
                $this->info($process_name.' process is running...');
                return true;
            }else
                $this->error($process_name.' process not running...');
        }

        /**
         * Obtener el flujo de resultados de un comando
         * ejecutándose en segundo plano.
         *
         * @param $process_name
         */
        function psGetOutputProcess($process_name)
        {
            if($this->psStatusProcess($process_name)){
                $this->message($process_name.": \n". $this->process[$process_name]->getOutput());

            } else {
                $this->error($process_name.' not running...');
            }
        }

        /**
         * Verificar estado de procesos en segundo plano.
         *
         * @param StringInput $input
         */
        function psProcessCommand(StringInput $input)
        {
            $arguments = explode(' ',$input);
            $ps_name   = $arguments[1];
            $ps_action = $arguments[2];

            if( !isset($this->process[$ps_name])){
                $this->error($ps_name.' process not sunning.');
                $this->go();
                return;
            }

            if($ps_action == 'status')
                $this->psStatusProcess($ps_name);

            elseif($ps_action == 'show')
                $this->psGetOutputProcess($ps_name);

            elseif($ps_name =='sync' && $ps_action == 'start')
                $this->psExecuteProcessBackground();

            $this->go();
        }

    }