<?php

    namespace Framework\Component\Console\SkeletCli;

    use phpseclib\Net\SSH2;
    use Illuminate\Support\Str;
    use Framework\Configurations;
    use Symfony\Component\Console\Style\SymfonyStyle;
    use Symfony\Component\Process\Process;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputOption;
    use Symfony\Component\Console\Input\StringInput;
    use Symfony\Component\Console\Question\Question;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Yaml\Exception\ParseException;
    use Symfony\Component\Yaml\Yaml;


    class InitCommand extends Command
    {
        /** @var InputInterface */
        public $input;
        /** @var OutputInterface */
        public $output;

        static public $tag = 'skelet-cli';

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
          'git','sfb','project'
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

        /**
         * Agregar opciones a los comandos que se ejecutaran.
         *
         * @var string
         */
        private $add_options;

        private $is_debug = false;

        protected function configure()
        {
            $this
                ->setName('init')
                ->addArgument('username', InputArgument::OPTIONAL, 'Nombre de usuario.')
                ->addOption('init','i',InputOption::VALUE_OPTIONAL,'Reconstruir la celula.')
                ->addOption('debug','d',InputOption::VALUE_OPTIONAL,'Ejecutar en modo debug.',false)
                ->addOption('simulate','s',InputOption::VALUE_OPTIONAL,'Ejecutar en modo simulacion.', false)
                ->setDescription('Autenticación de usuarios de espacios de trabajo.')
                ->setHelp(''."\n")
            ;

            $this->getConfigurationStation();
        }

        protected function execute(InputInterface $input, OutputInterface $output)
        {
            $this->input        = $input;
            $this->output       = $output;
            $simulate           = $this->input->getOption('simulate');
            $debug              = $this->input->getOption('debug');

            if ($debug) {
                $this->add_options .= ' --debug=true';
                $this->is_debug     = true;
            }

            if ($simulate) {
                $this->add_options .= ' --simulate=true';
            }

            if ($this->input->getArgument('username')) {
                $this->username = $this->input->getArgument('username');
            } else {
                $this->username = $this->config['server']['user'];
            }

            $this->welcome();
            $this->initializeFrameServer();
        }


        /**
         * Cargar la configuración de la skelet-cli
         *
         */
        function getConfigurationStation()
        {
            $configs = [];

            $file = base_path().'/config/_skelet-cli/workspace.yml';

            if (!file_exists($file)) {
                return;
            }

            try {

                $configs = Yaml::parse(file_get_contents($file));

            } catch (ParseException $e) {

                exit('No es posible leer la configuración del workspace.');
            }

            $this->config   = $configs;
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
            $this->output->writeln('<fg=black;bg=magenta> '.self::$tag.' </><fg=black;bg=cyan> v1.0 by Ing. Yadel Batista.</>');
            $this->output->writeln('<fg=black;bg=magenta> '.self::$tag.' </><fg=black;bg=cyan> '.$this->username.' ==> '.$this->config['server']['host'].' </>');

            $this->debug('enabled');

            $io = new SymfonyStyle($this->input, $this->output);
//            $io->newLine();
//            $io->progressStart(100);
//            sleep(5);
//            $io->progressAdvance(10);
//            sleep(5);
//            $io->progressAdvance(10);
//            $io->progressFinish();
            //$io->newLine();

            //$io->choice('Select the queue to analyze', array('queue1', 'queue2', 'queue3'));
        }

        static function tag()
        {
           return '<fg=black;bg=cyan> '.static::$tag.' </>';
        }


        public function debug($text)
        {
            if($this->is_debug) {
                $this->output->writeln('<fg=red> debug: </> '.$text);
            }
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
                $this->executeRemoteCommand($input);

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

        /**
         * Obtener el directorio donde se encuentra
         * skelet-cli-server para ejecutar comandos
         * remotos.
         *
         * @return mixed
         */
        private function getSkeletCliServerPath()
        {
            if (! isset($this->config['skelet-cli-server']['path'])) {
                return $this->config['server']['path'].'/skelet-cli-server';
            }
            return $this->config['skelet-cli-server']['path'];
        }

        /**
         * Ejecutar comandos locales.
         *
         * @param $command
         * @throws \Exception
         */
        public function executeLocalCommand($command)
        {
            $input      = new StringInput($command);
            $command_str= $input->getFirstArgument();

            $this
                ->getApplication()
                ->find($command_str)
                ->run($input, $this->output);
        }


        /**
         * Verificar si el comando se redireccionara
         * a skelet-cli-server
         *
         * @param $command
         * @return bool
         */
        function isRedirectToRemote($command)
        {
             if(in_array($command, $this->redirect_to_remote)) {

                 return true;

             } else {

                 return false;
             }
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


            if ($hidden) {

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
                $this->executeRemoteCommand($last_command);
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
            $this->executeRemoteCommand('sfb build --ask=Y', $this->e_patch);
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
        function executeRemoteCommand($command, $exe_in_path = null)
        {
            if (!$exe_in_path) {
                $exe_in_path = $this->getSkeletCliServerPath();
            }

            $this->last_remote_command  = $command;

            $command   = 'export CONSOLE_TYPE="remote"; php '.$exe_in_path.'/skelet-cli '.$command.' '.$this->add_options;

            $this->debug('remote command: '.$command);

            $result    = $this->server->exec($command);
            $result    = $this->prepareForExecutedLocal($result);

            $this->debug('remote command (response): '.$result);

            Utilities::remote()->executeRemote($result,$this);

            $this->go();
        }


        public function prepareForExecutedLocal($text)
        {
            $text    =  str_replace(['{auth.user}','{auth.password}'],[$this->username,$this->auth->getPassword()], $text);
            return $text;
        }


        /**
         * Ejecutar procesos en background.
         *
         * @deprecated
         * @return bool
         */
        function psExecuteProcessBackground()
        {

            if (! $this->psStatusProcess('sync')) {

                $this->process['sync'] = new Process(
                    'php '.base_path().'/skelet-cli sync '.$this->username.'.'.$this->auth->getPassword().' '.$this->add_options.' > /dev/null 2>&1 &'
                );
                $this->process['sync']->run();
                $this->info('sync process started...');
            }

            return true;
        }


        /**
         * Ejecutar procesos en el local.
         * Normalmente enviados desde el remoto
         * para ser ejecutados en el local.
         *
         * @param      $name
         * @param      $process
         * @param bool $in_background
         */
        public function executeProcessLocal($name,$process, $in_background = false)
        {

            if($in_background) {
                $process .= ' > /dev/null 2>&1 &';
            }

            $process = $this->prepareForExecutedLocal($process);

            $this->process[$name] = new Process(
                'php '.base_path().'/skelet-cli '.$process
            );
            $this->process[$name]->run();
            $this->debug('execProcessLocal('.$name.'): '.$process);
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