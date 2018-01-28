<?php


    namespace Framework\Component\Console\SkeletCli;

    use Illuminate\Support\Str;
    use Framework\Configurations;
    use Framework\Component\Console\Process;
    use Framework\Component\Filesystem\Filesystem;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Process\Exception\ProcessFailedException;


    class Git
    {
        public static $instance;

        public $config =[];
        /** @var InputInterface */
        public $input;
        /** @var OutputInterface */
        public $output;

        public $utils;

        /** @var  Filesystem */
        public $fs;

        public $username;

        private $enabled_output;

        /**
         * Git constructor.
         *
         * @param InputInterface  $input
         * @param OutputInterface $output
         * @param array|null      $config
         */
        function __construct(InputInterface $input, OutputInterface $output, array $config = null)
        {
            if (isset($config)) {
                $this->config   = $config;
            } else
            {
                $this->config   = Configurations::yml('_skelet-cli/sfbuild.');
            }

            $this->input        = $input;
            $this->output       = $output;

            $this->utils        = new Utilities();
            $this->fs           = new Filesystem();
        }

        /**
         * Asignar el nombre de usuario que se esta ejecutando.
         *
         * @param $username
         */
        function setUsername($username)
        {
            $this->username = $username;
        }


        function addProjectRepoRemote()
        {
            $this->exec('remote add '.$this->utils->project_repo);
        }

        /**
         * Crear o Actualizar el template que se toma como referencia para configurar un nuevo
         * espacio de trabajo local. Estos archivos preparados, deberían descargarse en la
         * computadora del desarrollador.
         *
         */
        function createOrUpdateCellTemplate()
        {
            $template_path          = $this->utils->project_cell_template;
            $app_files_structure    = $this->utils->getCellAppStructure();  // <-- Application Structure
            $fw_files_structure     = $this->utils->getCellFrameworkStructure();   // <-- Framework Structure
            $files_structure        = array_merge($app_files_structure, $fw_files_structure);

            $this->cloneProjectRepoSpecificFiles($files_structure, $template_path);

            return true;
        }

        /**
         * Clonar archivos en especifico desde el repositorio del proyecto.
         *
         * @param array $files
         * @param       $destination
         * @internal param bool $delete_temp
         */
        public function cloneProjectRepoSpecificFiles(array $files, $destination)
        {
            $name_rand = Str::random(9);
            $path_temp = $this->utils->project_temp_path.'/'.$name_rand;
            $this->cloneProjectRepo($path_temp);

            if ($this->fs->copyTheseFiles($files, $path_temp, $destination)) {
                Utilities::local($this->input, $this->output)->info('Archivos copiados desde repo git.');
            } else {
                Utilities::local($this->input, $this->output)->error('No se pudo copiar los archivos.');
            }

            $this->fs->deleteDirectory($path_temp);
        }


        /**
         * Actualizar el núcleo del framework, desde el repositorio
         * oficial de skelet-framework.
         *
         * @internal param array $files
         * @internal param $destination
         * @internal param bool $delete_temp
         * @param $username
         */
        public function updateFrameworkStructure($username)
        {

            if (!$username) {
                $this->utils->local($this->input, $this->output)->error(
                    'Se requiere el nombre de usuario para actualizar.'
                );
            }

            $name_rand       = Str::random(9);
            $temporally_path = $this->utils->project_temp_path.'/'.$name_rand;

            $this->exec('clone '.$this->utils->framework_repo.' '.$temporally_path);

            // Después de limpiar y dejar la estructura del framework limpia
            // actualizamos el repositorio master del proyecto.
            $destination_path = $this->utils->getUserPath($username);

            // Realizamos un rsync de las estructura del repositorio
            // del framework, con la estructura del repositorio del
            // proyecto del usuario.
            $this->syncFilesStructure(
                $this->utils->getFrameworkCoreStructure(),
                $temporally_path,
                $destination_path
            );

            // Eliminamos la copia temporal del repositorio.
            $this->fs->deleteDirectory($temporally_path);

            // Asignando permisos necesarios para el desarrollador
            $this->assignPermissionsToDevelop($username);

            // Subiendo los cambios a la rama de framework-update
            //$this->makePullRequestFrameworkUpdate($username);

            // configuración
            $this->reconfigureEnvironmentAppVares($username);
        }

        /**
         * Clonar un repositorio con cierta estructura de archivos.
         *
         * @param       $repository
         * @param array $structure
         * @param       $destination
         */
        public function cloneGitRepositoryWithStructure($repository, array $structure, $destination)
        {
            $name_rand       = Str::random(9);
            $temporally_path = base_path().'/'.$name_rand;

            $this->exec('clone '.$repository.' '.$temporally_path);

            $this->syncFilesStructure($structure, $temporally_path, $destination);

            // Eliminamos la copia temporal del repositorio.
            $this->fs->deleteDirectory($temporally_path);

        }

        /**
         * Hacer el pull request al repositorio del proyecto
         * con los cambios hechos en la actualización del framework.
         *
         * @param $username
         */
        public function makePullRequestFrameworkUpdate($username)
        {
            Utilities::local($this->input, $this->output)->message('Make Pull Request for Project Repository...');

            // Creando rama de actualización del framework.
            $this->exec('checkout -b framework-core-update-'.$username);
            $this->exec("add .");
            $this->exec('status');
            $this->exec('commit -m "Actualizando core del framework / " '.date("YmdHis"));

            if ($this->exec("push") ==0) {
                Utilities::local($this->input, $this->output)->info('Pull Request for Project Repository: OK! ');
            }

        }

        /**
         * Verificar
         *
         * @param string $options
         * @return int
         */
        public function status($options ='')
        {
           return $this->exec('status '.$options);
        }

        /**
         * Crear y/o actualizar un repositorio de un usuario en el
         * workspace del proyecto.
         *
         * @param $username
         * @return int
         */
        public function createOrUpdateUserRepo($username)
        {
            if (! $this->utils->getProjectUser($username)) {
                Utilities::local($this->input, $this->output)->error('Usuario '.$username.' no existe.');
            }

            $user_repo_path = $this->utils->project_path.'/repos/'.$username;

            if (!$this->fs->exists($user_repo_path)) {
                $this->fs->makeDirectory($user_repo_path);
            }

            // Clonando el repositorio git del proyecto
            // normalmente se clonara la rama principal.
            $this->cloneProjectRepo($user_repo_path);

            // configuración
            $this->reconfigureEnvironmentAppVares($username);

            // .gitignore
            $this->createIfNotExistGitIgnore();

            $this->composer('update');

            // Asignando permisos necesarios para el desarrollador
            $this->assignPermissionsToDevelop($username);

            // Creando rama principal en la que trabajara el repositorio.
            $this->createAndCheckOutBranch($username,'master');

            return 1;
        }


        /**
         * Crear y/o actualizar el repositorio de skelet-cli desde
         * el repositorio principal de skelet-framework
         *
         * @param $comment
         * @return int
         */
        public function createOrUpdateSkeletCliRepository($comment)
        {
            if(! $comment) {
                Utilities::local($this->input, $this->output)->error(
                    'Se necesita un comentario para hacer un commit en skelet-cli.'
                );
                return;
            }

            // Configurando email para push.
            $this->exec('config user.email yabafinet@gmail.com', $destination);

            $destination = $this->utils->getSkeletCLIRepositoryPath();

            if (! $destination) {
                Utilities::local($this->input, $this->output)->error(
                    'No se encuentra la configuración de skelet-cli'
                );
            }

            if (!$this->fs->exists($destination)) {
                $this->fs->makeDirectory($destination);
            }

            // Crear y entrar a una rama nueva para la
            // actualización desde skelet-framework.
            $branch_name = 'update-skelet-framework-'.date("Ymd");
            $this->exec('checkout -b '.$branch_name, $destination);

            $this->cloneGitRepositoryWithStructure(
                $this->utils->framework_repo,
                $this->utils->getSkeletCLIStructure(),
                $destination
            );

            // Realizar cambios en la estructura de archivos
            // en los que se destaca renombrar workspace-sample.yml
            // por workspace.yml
            $this->fs->modifications(
                $this->utils->getSkeletCLIFilesStructureChange(),
                $destination,
                $destination
            );


            $this->exec('add .', $destination);
            $this->exec('commit -m "'.$comment.'" ', $destination);
            $this->exec('push origin '.$branch_name, $destination);

            return 1;
        }


        /**
         * Crear rama con prefijo del repositorio del usuario.
         *
         * @param      $username
         * @param      $branch_name
         * @param null $in_path
         */
        public function createAndCheckOutBranch($username,$branch_name, $in_path = null)
        {
            $this->exec('checkout -b '.$username.'-'.$branch_name, $in_path);
        }

        /**
         * Crear si no existe el repositorio del usuario.
         *
         * @param $username
         */
        public function createIfNotExistUserRepo($username)
        {
            if (! $this->verifyExistRepoUser($username)) {
                $this->createOrUpdateUserRepo($username);
            }
        }

        /**
         * Crear si no existe el archivo .gitignore
         */
        public function createIfNotExistGitIgnore()
        {
            $user_repo_path = $this->utils->getUserPath($this->username);

            // .gitignore
            $gitignore = $user_repo_path.'/.gitignore';

            if (! $this->fs->exists($gitignore)) {

                Utilities::local($this->input, $this->output)->message('Not Exist .gitignore');

                if ($this->fs->put($user_repo_path.'/.gitignore',
                    "/vendor\n".
                    "framework/storage/* \n".
                    "/.ssh \n".
                    "/.cloud-locale-test.skip \n".
                    "/.cache \n".
                    "/.bash_history \n".
                    ""
                )) {

                    Utilities::local($this->input, $this->output)->info('Created .gitignore');
                }
            }
        }

        /**
         * Verificar si existe repositorio de un
         * usuario en especifico.
         *
         * @param $username
         * @return bool
         */
        public function verifyExistRepoUser($username)
        {
            $user_repo_path = $this->utils->getUserPath($username);

            return $this->fs->exists($user_repo_path);
        }

        /**
         * Configurando la aplicación para cargar desde el directorio
         * del repositorio del usuario.
         * Ejemplo: permisos a --> directory of storage, etc...
         *
         * @param $username
         */
        public function reconfigureEnvironmentAppVares($username)
        {
            $user_repo_path = $this->utils->getUserPath($username);
            $env_file       = $user_repo_path.'/config/.env.yml';

            // Permisos a directorios y archivos importantes
            $this->fs->chmod($env_file,0777);
            $this->fs->chmod_r($user_repo_path.'/framework/storage',0777);

            $new_base_path  = $this->utils->getUserPublicBasePath($username);

            // Configurando el base_path de la app para
            // poder visualizar  la app en el navegador.
            $new_value  = [
                'app'=>[
                    'base_path'=> $new_base_path
                ]
            ];

            if (Configurations::modifyConfigFile($env_file,$new_value)) {

                Utilities::local($this->input, $this->output)->info(
                    'Las configuraciones fueron cargadas.'
                );

            } else {

                Utilities::local($this->input, $this->output)->error(
                    'Las configuraciones no fueron posibles cargarse en este repositorio.'
                );
            }
        }


        /**
         * Sincronizar dos directorios tomando en cuenta
         * los archivos pasados en $files_structure
         *
         * @param array $files_structure
         * @param       $origin
         * @param       $destination_path
         * @return bool
         * @internal param $destination
         * @internal param array $files
         */
        public function syncFilesStructure(array $files_structure, $origin, $destination_path)
        {

            foreach ($files_structure as $file) {

                $file_origin       = $origin.'/'.$file;
                $destination       = $destination_path.'/'.$file;

                Utilities::local($this->input, $this->output)->message('syncFile (origin)     : '.$file_origin);
                Utilities::local($this->input, $this->output)->message('syncFile (destination): '.$destination);

                if (is_dir($file_origin)) {
                    $file_origin   .='/*';

                    if (! Str::endsWith('/', $destination)) {
                        $destination .='/';
                    }
                }

                if ($this->fs->isFile($file_origin)) {

                    $base_path = $this->fs->dirname($destination);

                    if(! $this->fs->isDirectory($base_path)) {
                        $this->fs->makeDirectory($base_path,0755,true);
                    }
                }

                $repo = new Process("rsync -uavP --size-only --delete --progress ".$file_origin.' '.$destination);
                $repo->run();
            }

            return true;
        }



        /**
         * Clonar el repositorio del proyecto en un directorio en especifico.
         *
         * @param $directory
         * @return int
         */
        public function cloneProjectRepo($directory)
        {
            return $this->exec('clone '.$this->utils->project_repo.' '.$directory);
        }


        /**
         * Ejecutando comandos git.
         *
         * @param      $git_command
         * @param null $in_path
         * @return int
         */
        public function exec($git_command, $in_path = null)
        {
            if (! $in_path) {
                $in_path = $this->utils->getUserPath($this->username);
            }

            $cd     = "cd {$in_path};";
            $repo   = new Process($cd." git ".$git_command);

            if ($this->ifEnabledOutput()) {

                return $repo->run(function ($type, $buffer) {

                    if (Process::ERR === $type) {
                        Utilities::local($this->input, $this->output)->info($buffer);
                    } else {
                        Utilities::local($this->input, $this->output)->error($buffer);
                    }

                });

            } else {

                try {

                    $repo->mustRun();
                    return $repo->getOutput();

                } catch (ProcessFailedException $e) {
                    return $e->getMessage();
                }

            }

        }

        /**
         * Ejecutar comandos de composer.
         *
         * @param $command
         * @return int
         */
        public function composer($command)
        {
            $git_dir= $this->username ? '-d='.$this->utils->getUserPath($this->username).'' : '';

            Utilities::local($this->input, $this->output)->message("Executing / composer ".$command." ...");

            $repo   = new Process("composer ".$command." {$git_dir}" );
            $repo->setTimeout(120);

            $result = $repo->run(function ($type, $buffer) {
                echo " COMPOSER  : ".$buffer."\n";
            });

            return $result;
        }


        /**
         * Ejecutar comandos de composer.
         *
         * @param null   $username
         * @param string $group
         * @return int
         * @internal param $command
         */
        public function assignPermissionsToDevelop($username = null, $group ='dev01')
        {
            $username = $username ? $username : $this->username;

            Utilities::local($this->input, $this->output)->message(
                "Asignando permisos para desarrollador ..."
            );

            $repo   = new Process(
                "chown -R {$username}:{$group} ".$this->utils->getUserPath($username)."/ "
            );

            $result = $repo->run(function ($type, $buffer) {
                echo " PERM.    : ".$buffer."\n";
            });

            // Repository Git
            $repo   = new Process(
                "chown -R {$username}:{$group} ".$this->utils->getUserPath($username)."/.git/* "
            );

            $result .= $repo->run(function ($type, $buffer) {
                echo " PERM. GIT: ".$buffer."\n";
            });

            // Storage:
            $repo   = new Process(
                "chmod -R 0777 ".$this->utils->getUserPath($username)."/storage/* "
            );

            $result .= $repo->run(function ($type, $buffer) {
                echo " STORAGE  : ".$buffer."\n";
            });


            return $result;
        }

        /**
         * Habilitar la salido de resultados de la consola.
         *
         * @param bool $enabled
         */
        public function setEnabledOutput($enabled = true)
        {
            $this->enabled_output = $enabled;
        }

        /**
         * Verificar si la salida de la consola esta habilitado.
         *
         * @return mixed
         */
        public function ifEnabledOutput()
        {
            return $this->enabled_output;
        }

        /**
         * Singleton.
         *
         * @param InputInterface|null  $input
         * @param OutputInterface|null $output
         * @return Git
         */
        public static function singleton(InputInterface $input = null, OutputInterface $output = null)
        {
            if (self::$instance == null)
                self::$instance = new Git($input, $output);

            return self::$instance;
        }
    }