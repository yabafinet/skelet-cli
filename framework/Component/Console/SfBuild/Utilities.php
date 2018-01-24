<?php


    namespace Framework\Component\Console\SfBuild;


    use Framework\Configurations;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    class Utilities
    {

        /** @var InputInterface */
        public  $input;
        /** @var OutputInterface */
        public  $output;

        public  $tag = 'sfbuild';

        public  $isRemote = false;

        public  $project_repo;
        public  $project_path;
        public  $project_temp_path;
        public  $project_cell_template;
        public  $project_user_master;
        public  $project_users;

        public  $framework_repo;

        /**
         * Configuración de estructura de archivos
         * para configurar el framework.
         *
         * @var array|mixed
         */
        private $framework_general_config = [];

        public  $cell_general_config = [];


        function __construct(InputInterface $input = null, OutputInterface $output = null, array $config = null)
        {
            $this->input = $input;
            $this->output = $output;

            if ($this->isConsoleRemoteType()) {

                // Estas configuraciones son cargadas al ejecutar
                // comandos en el servidor.

                if (isset($config)){

                    $this->config = $config;
                } else {
                    $this->config = Configurations::yml('_sfbuild/sfbuild.');
                }

                // Project Config
                $this->project_repo         = $this->config['project']['git_repo'];
                $this->project_path         = $this->config['project']['path'];
                $this->project_temp_path    = $this->project_path.'/_temp';
                $this->project_users        = $this->config['project']['users'];
                $this->project_user_master  = $this->config['project']['user_master'];
                $this->project_cell_template= $this->project_path.'/master/cell_template';
                $this->cell_general_config  = require base_path().'/config/_sfbuild/cell.php';

                // Framework Config
                $this->framework_general_config  = require base_path().'/config/_sfbuild/framework.php';

                $this->framework_repo = $this->config['skelet_framework']['git_repo'];

            } else {

                $this->config       = $this->getWorkspaceConfig();
                $this->project_path = $this->config['server']['path'];
                $this->project_users= [ ];
            }

        }

        /**
         * Obtener información de un usuario registrado en sfbuild.yml
         *
         * @param $username
         * @return bool
         */
        public function getProjectUser($username)
        {
            if(isset($this->project_users[$username]))
                return $this->project_users[$username];
            else
                return false;
        }

        public function getWorkspaceConfig()
        {
            return Configurations::yml('_sfbuild/workspace.');
        }


        public function getUsername()
        {

        }

        /**
         * Verificar si la consola se esta ejecutando
         * en un entorno remoto.
         *
         * @return bool
         */
        public function isConsoleRemoteType()
        {
            $console_type = isset($_SERVER['CONSOLE_TYPE']) ? $_SERVER['CONSOLE_TYPE'] : 'local';

            return $console_type == 'remote'? true : false;
        }

        function getUserPath($username)
        {
            return $this->project_path.'/repos/'.$username;
        }

        function getUserPublicBasePath($username)
        {
            return '/'.end(explode("/",$this->project_path)).'/repos/'.$username.'/public';
        }

        function getFrameworkCoreStructure()
        {
            return $this->framework_general_config['files_structure']['core'];
        }

        function getSkeletCLIStructure()
        {
            return $this->framework_general_config['files_structure']['cli']['files'];
        }

        function getSkeletCLIFilesStructureChange()
        {
            return $this->framework_general_config['files_structure']['cli']['files_structure_change'];
        }

        function getSkeletCLIRepositoryPath()
        {
            return $this->framework_general_config['files_structure']['cli']['repo_path'];
        }

        function getCellAppStructure()
        {
            return $this->cell_general_config['files_structure']['app'];
        }

        function getCellFrameworkStructure()
        {
            return $this->cell_general_config['files_structure']['framework'];
        }

        /**
         *
         * @param      $text
         * @param int  $code
         * @return mixed
         * @internal param bool $isRemote
         */
        public function error($text,$code = 10)
        {
            if ($this->isRemote) {
                return $this->remoteMessage([
                    'cod'=>$code,
                    'type'=>'error',
                    'msg'=>$text,
                ]);

            } else {
                return $this->output->writeln(StartCommand::tag().'<fg=red;options=bold> '.$text.' </>');
            }

        }

        /**
         *
         * @param      $text
         * @return mixed
         * @internal param bool $isRemote
         * @internal param int $code
         */
        public function info($text)
        {
            if ($this->isRemote) {

                return $this->remoteMessage([
                    'cod'=>'00',
                    'type'=>'info',
                    'msg'=>$text,
                ]);

            }else {

                return $this->output->writeln(StartCommand::tag().'<fg=blue;options=bold> '.$text.' </>');
            }

        }

        /**
         * Imprimir desde remoto una salida con formato console.
         *
         * @param $text
         * @return string
         */
        public function console($text)
        {
            if ($this->isRemote) {
                return $this->remoteMessage([
                    'cod'=>'00',
                    'type'=>'console',
                    'msg'=>"$text",
                ]);
            }
        }

        /**
         *
         * @param $question
         * @return mixed
         * @internal param $text
         * @internal param int $code
         * @internal param bool $isRemote
         */
        public function question($question)
        {
            return $this->remoteMessage([
                'cod'=>'00',
                'type'=>'question',
                'question'=>$question,
            ]);

        }

        /**
         * @param array $data
         * @return string
         */
        public function remoteMessage(array $data)
        {
            $send_message = json_encode($data);

            if (isset($this->output)) {
                $this->output->writeln($send_message);
            } else {
                return $send_message;
            }
        }

        /**
         *
         * @param $text
         * @return mixed
         */
        public function message($text)
        {
            return $this->output->writeln(StartCommand::tag().' '.$text.' ');
        }

        /**
         *
         * @param              $response
         * @param StartCommand $startCommand
         */
        public function executeRemote($response, StartCommand $startCommand)
        {

            $response = json_decode($response);

            if(!isset($response->type))
                return;

            if($response->type =='error')
                $startCommand->error($response->msg);

            elseif ($response->type =='info')
                $startCommand->info($response->msg);

            elseif ($response->type =='question')
            {
                $startCommand->question($response->question, false,true);
            }
            elseif ($response->type =='console')
                $startCommand->output->writeln(str_replace('<\\','<',$response->msg));
        }

        static function remote(InputInterface $input = null, OutputInterface $output = null)
        {
            $utilities           = new Utilities($input, $output);
            $utilities->isRemote = true;
            return $utilities;
        }

        static function local(InputInterface $input = null, OutputInterface $output = null)
        {
            $utilities           = new Utilities($input, $output);
            $utilities->isRemote = false;
            return $utilities;
        }

        static function stationConfig(array $config =[])
        {
            $station            = new \stdClass();

            if(!$config)
                $config         = self::getWorkspaceConfig();

            if(!isset($config))
                return $config;

            $current_user               = shell_exec('echo $USER');

            $station->username          = $current_user;
            $station->server_host       = $config['server']['host'];

            $station->evolution_version = $config['sfbuild']['evolution_version'];
            $station->evolution_patch   = $config['sfbuild']['evolution_path'].'/master/'.$station->evolution_version;

            $station->buildings_patch   = $config['sfbuild']['cell_path'].'/buildings';

            $station->cells_path        = $config['sfbuild']['cell_path'];
            $station->cells_repos       = $config['sfbuild']['cell_path'].'/repos';
            $station->cell_buildings    = $station->buildings_patch.'/'.trim($current_user);
            $station->cell_path         = $station->cells_repos.'/'.trim($current_user);


            return $station;
        }

        /**
         * Obtener el nombre del usuario conectado al
         * sistema operativo. Normalmente se obtiene
         * cuando estamos ejecutando comandos remotos.
         *
         * @return string
         */
        static function currentOsUsername()
        {
            return trim(shell_exec('echo $USER'));
        }


    }