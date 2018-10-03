<?php

namespace Framework\Component\Console\SkeletCli\LocalCommands;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use JasonLewis\ResourceWatcher\Tracker;
use JasonLewis\ResourceWatcher\Watcher;
use League\Flysystem\Filesystem;
use League\Flysystem\Sftp\SftpAdapter;
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

    /**
     * @var InputInterface
     */
    public $input;

    /**
     * @var OutputInterface
     */
    public $output;

    public $command;

    public $ssh;

    /**
     * @var Configuration
     */
    public $config;


    private $filesystem;

    /**
     * Directorios que se observaran para ser sincronizados.
     *
     * @var array
     */
    private $dirForSync = [];

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
     * Códigos de eventos que se ejecutan en el
     *  file watcher.
     *
     * @var array
     */
    private $file_watcher_event = [
          0=>'deleted',
          1=>'created',
          2=>'modified'
    ];

    /**
     * @var
     */
    private $sftp_user;

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

    /**
     * @return mixed
     */
    public function getSftpUser()
    {
        return $this->sftp_user;
    }

    /**
     * @param mixed $sftp_user
     */
    public function setSftpUser($sftp_user)
    {
        $this->sftp_user = $sftp_user;
    }

    protected function configure()
        {
            $this
                ->setName('sync')
                ->addArgument('remote_user', InputArgument::REQUIRED, '')
                ->addOption('config','c',InputOption::VALUE_OPTIONAL,'Especifica si se quiere reconfigurar.')
                ->addOption('download','d',InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,'Especificar los archivos a descargar desde el remoto.')
                ->addOption('exclude','e',InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,'Archivos que se excluyen de la sincronización.')
                ->addOption('upload','u',InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,'Directorios que se observaran para sincronizar.')
                ->addOption('simulate','s',InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,'Simular la sincronización del proyecto.')
                ->setDescription('Sincronización de una estación de trabajo local, con skelet-cli-server.')
                ->setHelp(''."\n")
            ;

        }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input   = $input;
        $this->output  = $output;
        $this->utils   = Utilities::local($this->input, $this->output);

        $this->setSftpUser($input->getArgument('remote_user'));

        $download      = $input->getOption('download');
        $exclude       = $input->getOption('exclude');

        $this->dirForSync = $input->getOption('upload');

        $simulate      = $input->getOption('simulate');


        if ($simulate) {
            $this->enabledSimulation();
        }

        if( $this->dirForSync){
            return $this->startFilesWatcher();
        }

        if ($download) {
            return $this->downloadFiles($download, $exclude);
        }

        $this->utils->error('Action not found ...');

    }


    /**
     * Iniciar la monitorización de archivos para sincronizar.
     *
     * @return int
     */
    public function startFilesWatcher()
    {
        $this->output->writeln('- - - - - - - - - - - - - - - - - - - - - - ');
        $files      = new \Illuminate\Filesystem\Filesystem;
        $tracker    = new Tracker;

        $watcher    = new Watcher($tracker, $files);


        $dirForSync =  $this->getDirectoriesForSync();
        foreach ($dirForSync as $dir) {

            $this->utils->info('watch: '.$dir);

            $listener   = $watcher->watch($dir);
            $listener->anything(function(\JasonLewis\ResourceWatcher\Event $event, $resource, $path) {
                $this->fireOnEventFile($event->getCode(), $path);
            });
        }

        $watcher->start();

        return 0;
    }

    /**
     * Lanzar un evento cuando se detecte cambios en los directorios vigilados.
     *
     * @param $eventCode
     * @param $path
     */
    private function fireOnEventFile($eventCode, $path)
    {
        $event = $this->getFileWatcherEvent($eventCode);

        if($event =='deleted') {
        } elseif ($event =='created') {
        } elseif ($event =='modified') {}

        $this->modifyFileInRemote($path, $event);
        $this->utils->info("{$path} fire --> ".$event);
    }

    /**
     * Enviar modificación de archivo al servidor remoto.
     *
     * @param $file_path
     * @param $event
     * @return bool
     */
    public function modifyFileInRemote($file_path, $event)
    {
        $filesystem = $this->connectSftp();

        $status         = '<error>[E]</error>';
        $targetFile     = $file_path;
        $remoteFile     = $this->getRemotePatch($targetFile);

        if(! $this->isSimulated()) {
            if($event =='modified') {
                if (! $filesystem->put($remoteFile, fopen($targetFile, 'r+'))) {
                    $status = '<error>PUT(E)</error>';
                } else {
                    $status = '<info>PUT</info>';
                }
            } else if($event =='deleted') {
                if (! $filesystem->delete($remoteFile)) {
                    $status = '<error>DELETE(E)</error>';
                } else {
                    $status = '<info>DELETE</info>';
                }
            } else if($event =='created') {
                if (! $filesystem->write($remoteFile, fopen($targetFile, 'r+'))) {
                    $status = '<error>CREATE(E)</error>';
                } else {
                    $status = '<info>CREATE</info>';
                }
            }
        }

        $this->output->writeln(' [ '.$status.' ] '.$targetFile.' --> '.$remoteFile);

    }


    /**
     * Obtener directorios locales que seran sincronizados
     * con el servidor remoto.
     *
     * @return array
     */
    public function getDirectoriesForSync()
    {
        $dirs = [];

        foreach ($this->dirForSync as $file) {

            $file_path = base_path().'/' . $file;

            if(file_exists($file_path)) {
                $dirs[] = $file_path;
            }
        }

        return $dirs;
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
    public function connectSftp($user = null, $destination = null)
    {
        $user = $user ? $user : $this->getSftpUser();
        $destination = $destination ? $destination : $this->config->getUserRepositoryPath();

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

        $this->utils->info(' Document Root (Remote) : '.$sftp->getRoot());

        return $this->filesystem;
    }


    /**
     * Sincronizar cambios de aplicación local con
     * aplicación remota.
     *
     * @deprecated
     *
     * @param $user
     * @param $destination
     * @return bool
     * @throws FileNotFoundException
     */
    public function syncSftp($user,$destination)
    {
        $index_modified     =  $this->getIndexSynchronization();

        $last_modified_dir  = $this->getTheLatestModifiedDirectories($index_modified);
        $modified_files     = $this->getTheLatestModifiedFiles($last_modified_dir);

        if($this->isSimulated()) {
            $this->output->writeln(" <info> - - - is simulation!</info>");
        }

        $this->output->writeln('- - - - - - - - - - - - - - - - - - - - - - ');
        $this->output->writeln($index_modified.' / last synchronisation [<fg=red>'.date("H:i:s").'</>]. ');

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

    private function prepareIncludeFiles()
    {
        $dirs_for_sync =  $this->getDirectoriesForSync();
        $options = '';
        foreach ($dirs_for_sync as $dir) {
            $options .='--include="'.$dir.'" ';
        }

        return $options;
    }

    /**
     * Descargar archivos desde servidor remoto.
     *
     * @param array $paths_for_download
     * @param array $exclude_files
     */
    public function downloadFiles(array $paths_for_download, array $exclude_files = [])
    {
        $sftp   = $this->connectSftp();

        foreach ($paths_for_download as $download) {

            $files      = $sftp->listContents($this->normalizeFile($download), true);
            $fs         = new \Framework\Component\Filesystem\Filesystem();
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

    /**
     * @param $event_code
     * @return array
     */
    public function getFileWatcherEvent($event_code)
    {
        return $this->file_watcher_event[$event_code];
    }
}