<?php


    namespace Framework\Component\Console\SkeletCli;


    use Framework\Component\Filesystem\Filesystem;
    use Illuminate\Support\Str;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Console\Style\SymfonyStyle;
    use Symfony\Component\Process\Exception\ProcessFailedException;
    use Symfony\Component\Process\Process;

    trait GitManager
    {

        /**
         * @var  Filesystem
         */
        public $fs;

        /**
         * @var SymfonyStyle
         */
        private $console_style;

        /**
         * @var InputInterface
         */
        public $input;

        /**
         * @var OutputInterface
         */
        public $output;

        private $enabled_output;

        /**
         * Git constructor.
         *
         * @param InputInterface  $input
         * @param OutputInterface $output
         * @param array|null      $config
         */
        public function __construct(InputInterface $input, OutputInterface $output, array $config = null)
        {

            $this->input        = $input;
            $this->output       = $output;

            $this->fs           = new Filesystem();

            $this->console_style= new SymfonyStyle($input, $output);
        }


        public function consoleStyle()
        {
            if (! $this->console_style ) {
                $this->console_style= new SymfonyStyle($this->input, $this->output);
            }

            return $this->console_style;
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

            $this->exec('clone '.$repository.' '.$temporally_path, $destination);

            $this->syncFilesStructure($structure, $temporally_path, $destination);

            // Eliminamos la copia temporal del repositorio.
            $this->fs->deleteDirectory($temporally_path);

        }

        /**
         * Sincronizar dos directorios tomando en cuenta
         * los archivos pasados en $files_structure
         *
         * @param array $files_structure
         * @param       $origin
         * @param       $destination_path
         * @return bool
         */
        public function syncFilesStructure(array $files_structure, $origin, $destination_path)
        {

            foreach ($files_structure as $file => $config) {

                $file_origin       = $origin.'/'.$file;
                $destination       = $destination_path.'/'.$file;
                $exclude_option    = '';
                $modifications     = null;

                // Config Exclude Files
                if(isset($config['exclude'])) {
                    foreach ($config['exclude'] as $exclude) {

                        $exclude_option .=' --exclude="'.$exclude.'" ';
                    }
                }
                // File Require Modifications
                if(isset($config['modification'])) {
                    foreach ($config['modification'] as $mod_type=>$new_value) {
                        $modifications[$file][$mod_type] = $new_value;
                    }
                }

                $this->consoleStyle()->text('sync origin      <== '.$file_origin);
                $this->consoleStyle()->text('     destination ==> '.$destination);

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

                $rsync_command = "rsync -uavP $exclude_option --size-only --delete --progress ".$file_origin.' '.$destination;
                $repo = new Process($rsync_command);

                if ($repo->run() ==1) {

                } else {

                }

                //Utilities::local($this->input, $this->output)->message("rsync: \n".$repo->getOutput());

                if($modifications) {
                    $this->fs->modifications($modifications, $destination_path);
                    $this->consoleStyle()->note('Modification:'.$file_origin);
                }
            }

            $this->consoleStyle()->comment("Synchronization Structure success!");
            return true;
        }



        /**
         * Ejecutando comandos git.
         *
         * @param      $git_command
         * @param null $in_path
         * @return int
         */
        public function exec($git_command, $in_path)
        {

            $cd     = "cd {$in_path};";
            $repo   = new Process($cd." git ".$git_command);
            $this->consoleStyle()->text('git command: '.$git_command);

            if ($this->ifEnabledOutput()) {

                return $repo->run(function ($type, $buffer) {

                    if (Process::ERR === $type) {
                        //$this->consoleStyle()->text(''.$buffer);
                    } else {
                        //Utilities::local($this->input, $this->output)->error($buffer);
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
    }