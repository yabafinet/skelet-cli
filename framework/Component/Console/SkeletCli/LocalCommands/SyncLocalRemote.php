<?php


    namespace Framework\Component\Console\SkeletCli\LocalCommands;



    use League\Flysystem\Filesystem;
    use Symfony\Component\Finder\Finder;
    use League\Flysystem\Sftp\SftpAdapter;
    use League\Flysystem\FileNotFoundException;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputOption;
    use Framework\Component\Console\SkeletCli\Utilities;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Command\LockableTrait;
    use Framework\Component\Console\SkeletCli\InitCommand;
    use Symfony\Component\Console\Output\OutputInterface;


    class SyncLocalRemote extends Command
    {
        use LockableTrait;

        /** @var InputInterface */
        public $input;

        /** @var OutputInterface */
        public $output;

        public $command;

        public $ssh;

        /**
         * @var Configuration
         */
        public $config;

        /**
         * Archivo donde almacenamos el ultimo index de sincronización.
         * A partir de aquí seguimos buscando cambios en los directorios
         * observados para ser sincronizados.
         *
         * @var string
         */
        private $file_index_sync = 'storage/cache/sync_local.index';

        /**
         * Archivo cache donde se almacenaran los archivos locales
         * ya indexados, para mantener la misma cantidad que remoto.
         *
         * @var string
         */
        private $file_for_index_files = 'storage/cache/sync_index_local_files.index';

        private $filesystem;

        /**
         * Directorios que se observaran para ser sincronizados.
         *
         * @var array
         */
        private $dirs_for_sync = [];

        /**
         * Archivos indexados como existentes.
         *
         * @var array
         */
        private $files_indexed_locally = null;


        /**
         * @var Utilities
         */
        public $utils;

        /**
         * Esta propiedad en true solo mostrara
         * las acciones pero no las ejecutara.
         *
         * @var bool
         */
        private $is_simulate = false;

        /**
         * SyncLocalRemote constructor.
         *
         * @param InitCommand|null $command_base
         */
        public function __construct(InitCommand $command_base = null)
        {
            parent::__construct();

            if ($command_base) {
                $this->input   = $command_base->input;
                $this->output  = $command_base->output;
                $this->command = $command_base;
            }

            $this->config   = new Configuration();

        }

        protected function configure()
        {
            $this
                ->setName('sync')
                ->addArgument('remote_user', InputArgument::REQUIRED, '')
                ->addOption('config','c',InputOption::VALUE_OPTIONAL,'Especifica si se quiere reconfigurar.')
                ->addOption('download','d',InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,'Especificar los archivos a descargar desde el remoto.')
                ->addOption('exclude','e',InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,'Archivos que se excluyen de la sincronización.')
                ->addOption('simulate','s',InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,'Simular la sincronización del proyecto.')
                ->setDescription('Sincronización de una celula con remoto.')
                ->setHelp(''."\n")
            ;

        }

        /**
         * @param InputInterface  $input
         * @param OutputInterface $output
         * @return int|null|void
         */
        protected function execute(InputInterface $input, OutputInterface $output)
        {
            $this->input   = $input;
            $this->output  = $output;
            $this->utils   = Utilities::local($this->input, $this->output);

            $user          = $input->getArgument('remote_user');

            $download      = $input->getOption('download');
            $exclude       = $input->getOption('exclude');

            $simulate      = $input->getOption('simulate');

            if($simulate) {
                $this->enabledSimulation();
            }

            if ($download) {

                $this->downloadFiles($user,$download, $exclude);

            } elseif ($this->startSyncProject($user)) {

                $this->utils->error('Autenticación fallida para '.$user);

            }else{
                //Utilities::local($input,$output)->info('Conectado! ');
            }
        }


        /**
         * Obtener directorios locales que seran sincronizados
         * con el servidor remoto.
         *
         * @return array
         */
        public function getDirectoriesForSync()
        {
            $this->dirs_for_sync = [
              base_path().'/app',
              //base_path().'/public/assets',
            ];

            return $this->dirs_for_sync;
        }


        /**
         * Iniciar el proceso de sincronización.
         *
         * @param $user
         * @return bool
         */
        function startSyncProject($user)
        {

            try {
                if ($this->syncSftp($user, $this->config->getUserRepositoryPath())) {
                    sleep(10);
                    $this->startSyncProject($user);
                }
            } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $e) {

            }

            return false;
        }

        /**
         *
         * @param $file
         * @return mixed
         */
        public function getRemotePatch($file)
        {
            return str_replace(dirname(base_path().'/skelet-cli').'/','', $file);
        }


        /**
         * Conectar al servidor SFTP.
         *
         * @param $user
         * @param $destination
         * @return Filesystem
         */
        public function connectSftp($user,$destination)
        {
            if (isset($this->filesystem)) {
                return $this->filesystem;
            }

            $host   = $this->config->getProjectServerHost();
            $pass   = '';

            // Dividir user/pass
            if (strstr($user,'.')) {
                $access = explode('.',$user);
                $user   =  $access[0];
                $pass   =  $access[1];
            }

            $sftp  =  new SftpAdapter([
                'host'      => $host,
                'port'      => 22,
                'username'  => $user,
                'password'  => $pass,
                'root'      => $destination,
                'timeout'   => 10,
            ]);

            $this->filesystem = new Filesystem($sftp);
            $contents   = $this->filesystem->listContents();

            return $this->filesystem;
        }


        /**
         * Sincronizar cambios de aplicación local con
         * aplicación remota.
         *
         * @param $user
         * @param $destination
         * @return bool
         * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
         */
        function syncSftp($user,$destination)
        {
            $index_modified     =  $this->getIndexSynchronization();

            $last_modified_dir  = $this->getTheLatestModifiedDirectories($index_modified);
            $modified_files     = $this->getTheLatestModifiedFiles($last_modified_dir);

            if($this->isSimulated()) {
                $this->output->writeln(" <info> - - - is simulation!</info>");
            }

            $this->output->writeln('- - - - - - - - - - - - - - - - - - - - - - ');
            $this->output->writeln($index_modified.' / last synchronisation. ');

            if (! $modified_files) {
                $this->output->writeln('<comment>There are no files to modify.</comment>');
                return true;
            }

            $filesystem = $this->connectSftp($user, $destination);

            $this->matchIndexedDirectoryStructureFromRemote($last_modified_dir, $filesystem);

            foreach ($modified_files as $file) {

                $status         = '<error>ERR</error>';
                $targetFile     = $file->getPathname();
                $remoteFile     = $this->getRemotePatch($targetFile);
                $date_modified  = $file->getMTime();

                if ($date_modified > $index_modified ) {

                    if ($filesystem->has($remoteFile)) {

                        if(! $this->isSimulated()) {
                            if (! $filesystem->put($remoteFile, fopen($targetFile, 'r+'))) {
                                return false;
                            }
                        }

                        $status = '<info>PUT</info>';

                    } else {

                        if(! $this->isSimulated()) {
                            if (! $filesystem->write($remoteFile, fopen($targetFile, 'r+'))) {
                                return false;
                            }
                        }


                        $status = '<info>WR </info>';

                        // Verificamos si existe en el indexado.
                        if (! $this->verifyExistenceIndexedLocally($targetFile)) {

                            // Si no existe en el indexado, el archivo fue eliminado
                            // en el local, entonces, eliminamos en el remoto.
                            //$filesystem->delete($remoteFile);

                            $this->indexedFile($targetFile);

                            $status .= '<info>INDEXED</info>, ';
                        }
                    }

                } else {
                    $status = "<comment>* *</comment>";
                }

                $this->output->writeln($date_modified.' [ '.$status.' ] '.$targetFile);

                //$this->updateIndexSynchronization($date_modified);
            }

            $this->output->writeln('- - - - - - - - - - - - - - - - - - - - - - ');

            return true;
        }


        /**
         * Obtener los ultimos archivos modificados.
         *
         * @param array $in_directories
         * @return bool|Finder
         */
        public function getTheLatestModifiedFiles(array $in_directories)
        {
            if (count($in_directories) <1) {
                return false;
            }

            $finder = new Finder();
            $finder
                ->files()
                ->in($in_directories)
                ->date('since 48 hour ago')
                ->sortByModifiedTime();

            return $finder;
        }

        /**
         * Obtener los ultimos directorios modificados. A partir de este resultados
         * haremos la sincronización de los cambios locales.
         *
         * @param $index_modified
         * @return array
         */
        public function getTheLatestModifiedDirectories($index_modified)
        {
            $finder = new Finder();
            $finder
                ->directories()
                ->exclude([base_path().'/vendor',base_path().'/store'])
                ->in(
                    $this->getDirectoriesForSync()
                )
                ->date('since 48 hour ago')
                ->sortByModifiedTime();

            $directories_for_sync   = array();
            $last_m_directory       = null;

            foreach ($finder as $file) {

                $date_modified = $file->getMTime();

                if ($date_modified > $index_modified ) {
                    $directories_for_sync[] = $file->getPathname();
                    $last_m_directory   = $date_modified;
                }
            }

            if(! is_null($last_m_directory) && $index_modified !== $last_m_directory) {
                $this->updateIndexSynchronization($last_m_directory);
            }

            return $directories_for_sync;
        }

        /**
         * Indexar los archivos locales que seran sincronizados
         * con el servidor remoto.
         *
         * @param array $directories
         */
        public function indexFiles($directories = null)
        {
            if (! $directories) {
                $directories = $this->getDirectoriesForSync();
            }
            $finder = new Finder();
            $finder
                ->files()
                ->exclude([base_path().'/vendor',base_path().'/store'])
                ->in($directories)
                ->sortByModifiedTime();

            $files_current_sync   = array();

            foreach ($finder as $file) {
                //d($file);
                $files_current_sync[] = $file->getPathname();
            }

            $fs =  new \Framework\Component\Filesystem\Filesystem();
            $fs->put($this->getFileForIndexLocalFiles(), implode("\n", $files_current_sync)."\n");

            $this->output->writeln('<info>'.count($files_current_sync).'</info> files indexed...');

            $this->reloadIndexedLocallyFiles();

        }

        /**
         * Indexar los archivos de los directorios observados
         * para sincronización con remoto.
         */
        public function reloadIndexedLocallyFiles()
        {
            $fs =  new \Framework\Component\Filesystem\Filesystem();

            try {

                $content = $fs->get($this->getFileForIndexLocalFiles());

                $this->files_indexed_locally = explode("\n", $content);

                $number_files_indexed = count($this->files_indexed_locally);

                $this->output->writeln('Reload <comment>'.$number_files_indexed.' </comment> files indexed Locally.');

            } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $e) {

                $this->output->writeln('<error>'.$e->getMessage().'</error>');
            }
        }

        /**
         * Verificar si un archivo existe en el indexado.
         *
         * @param $path_name
         * @return bool
         */
        public function verifyExistenceIndexedLocally($path_name)
        {
            if(! $this->files_indexed_locally) {
                $this->reloadIndexedLocallyFiles();
            }

            return in_array($path_name, $this->files_indexed_locally);
        }

        /**
         * Agregar al indexado un archivo nuevo.
         *
         * @param $path_name
         * @return int
         */
        public function indexedFile($path_name)
        {
            $fs =  new \Framework\Component\Filesystem\Filesystem();

            return $fs->append($this->getFileForIndexLocalFiles(), $path_name."\n");
        }


        /**
         *
         *
         * @param            $directories
         * @param Filesystem $sftp
         * @return void
         * @internal param $directory
         */
        public function matchIndexedDirectoryStructureFromRemote($directories, Filesystem $sftp)
        {
            //if(! $this->files_indexed_locally) {
                $this->reloadIndexedLocallyFiles();
            //}

            $finder = new Finder();
            $finder
                ->files()
                ->in( $this->getDirectoriesForSync() )
                ->sortByModifiedTime();

            $files_in_dirs   = array();

            foreach ($finder as $file) {
                $files_in_dirs[] = $file->getPathname();
            }
            $number_files_in_dirs           = count($files_in_dirs);
            $number_files_indexed_locally   = count($this->files_indexed_locally) -1;
            $is_deleted                     = false;


            foreach ($this->files_indexed_locally as $file_indexed) {

                if(! empty($file_indexed)) {

                    if (! in_array($file_indexed, $files_in_dirs)) {

                        try {

                            if(! $this->isSimulated()) {
                                $sftp->delete($this->getRemotePatch($file_indexed));
                            }

                            $this->output->writeln('<info>DEL REMOTE</info>: '. $file_indexed);

                        } catch (\Exception $exception) {

                            $this->output->writeln('<error>DEL REMOTE</error>: '. $exception->getMessage());
                        }

                        $is_deleted = true;
                    }
                }
            }

            if ($is_deleted) {
                $this->indexFiles();
            }

            $this->output->writeln(
                ' - Match structure ==> Indexed: <comment>'.$number_files_indexed_locally.'</comment> / Dirs: <comment>'.$number_files_in_dirs.'</comment> '
            );

        }


        /**
         * Obtener el index actual para sincronización.
         *
         * @return string
         */
        private function getFileForIndexSync()
        {
            return base_path().'/'.$this->file_index_sync;
        }

        /**
         * Obtener el index actual para sincronización.
         *
         * @return string
         */
        private function getFileForIndexLocalFiles()
        {
            return base_path().'/'.$this->file_for_index_files;
        }

        /**
         * Actualizar el index por la ultima sincronización.
         *
         * @param $index
         */
        public function updateIndexSynchronization($index)
        {
            $fs =  new \Framework\Component\Filesystem\Filesystem();

            $fs->put($this->getFileForIndexSync(),$index);
        }

        /**
         * Obtener/Crear el index de sincronización.
         *
         * @return string
         * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
         */
        public function getIndexSynchronization()
        {
            $file_index =  $this->getFileForIndexSync();

            $fs =  new \Framework\Component\Filesystem\Filesystem();

            if (! $fs->exists($file_index)) {
                $fs->put($this->getFileForIndexSync(),0);
            }

            if (! $fs->exists( $this->getFileForIndexLocalFiles() )) {
                $this->indexFiles();
            }

            if (! $fs->exists( base_path().'/storage/cache' )) {
                $fs->makeDirectory(base_path().'/storage/cache');
            }

            return $fs->get( $file_index );
        }

        /**
         * Descargar archivos desde servidor remoto.
         *
         * @param       $user
         * @param array $paths_for_download
         * @param array $exclude_files
         */
        public function downloadFiles($user, array $paths_for_download, array $exclude_files = [])
        {
            $sftp   = $this->connectSftp($user, $this->config->getUserRepositoryPath());


            foreach ($paths_for_download as $download) {

                $files      = $sftp->listContents($this->normalizeFile($download), true);
                $fs         =  new \Framework\Component\Filesystem\Filesystem();
                $base_path  = base_path();


                foreach ($files as $file) {

                    $file_local        = $base_path.'/'.$file['path'];
                    $file_local_path   = dirname($file_local);

                    // Verificar si el archivo no esta en la lista
                    // de archivos excluidos.
                    if (in_array($file['path'], $exclude_files)) {
                        continue;
                    }

                    if (! $fs->exists($file_local)) {

                        $remote_file = $sftp->get($file['path']);

                        if ($remote_file->isDir()) {

                            if(! $this->isSimulated()) {
                                $fs->makeDirectory($file_local,0755, true);
                            }

                        }

                        if ($remote_file->isFile()) {

                            if (! $fs->exists($file_local_path)) {

                                if(! $this->isSimulated()) {
                                    $fs->makeDirectory($file_local_path,0755, true);
                                }

                                $this->output->writeln('<info> NEW DIR </info>  ==> '. $file_local_path);
                            }

                            if(! $this->isSimulated()) {
                                $fs->put($file_local, $remote_file->read());
                            }

                        }

                        $this->output->writeln('<info> GET </info>  ==> '. $file['path']);


                    } else {
                        $this->output->writeln('<error> EXIST </error>  ==> '. $file['path']);
                    }
                }
            }
        }

        /**
         * Habilitar la simulación de sincronización.
         *
         */
        public function enabledSimulation()
        {
            $this->is_simulate = true;
        }

        public function isSimulated()
        {
            return $this->is_simulate;
        }


        /**
         * Regularizar el nombre del archivo.
         *
         * @param $file
         * @return string
         */
        private function normalizeFile($file)
        {
            if(substr($file, -1) !=='/') {
                $file = $file.'/';
            }

            return $file;
        }
    }