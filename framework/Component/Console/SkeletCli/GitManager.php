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