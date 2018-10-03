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
    public function __construct(ApplicationBase $application)
    {
        $this->application  = $application;

        if(! isset($_SERVER['CONSOLE_TYPE'])) {
            $_SERVER['CONSOLE_TYPE'] = 'local';
        }
        $this->loadRegisteredCommands();
    }


    /**
     * Cargar los comandos a la aplicación.
     *
     * @return bool
     */
    public function loadRegisteredCommands()
    {

        $this->registered_commands = require base_path()."/config/commands/commands.local.php";

        if($_SERVER['CONSOLE_TYPE'] =='remote') {

            $remote_commands       = require base_path()."/config/commands/commands.remote.php";
            $this->registered_commands['remote_commands'] = $remote_commands['commands'];
        }

        return true;
    }


    function getConfig()
    {
        //return $this
    }


    /**
     * Arrancar la aplicación de consola.
     *
     * @return bool
     */
    public function run()
    {

        try {
            $this->application->run();
        } catch (\Exception $e) {

            return false;
        }

        return true;
    }

    /**
     *
     * @param Command $command
     */
    public function add(Command $command)
    {
        $this->application->add($command);
    }

    /** */
    public function registerConfiguredCommands()
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
            $this->registerConfiguredRemoteCommands();
        }

        $this->registerInternalsSkeletCommands();
    }

    /**
     *
     */
    protected function registerConfiguredRemoteCommands()
    {
        foreach ($this->registered_commands['remote_commands'] as $command=>$config) {
            $this->add(new $command($this->start_command));
        }
    }

    /**
     * Registrando comandos internos de Skelet Framework. Estos comandos solo serán
     * utilizados para procesos de división de códigos para su distribución en los
     * diferentes repositorios de skelet.
     *
     * @return bool
     */
    public function registerInternalsSkeletCommands()
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

    public function setFrameServerStartCommand($start_command)
    {
        $this->start_command = $start_command;
    }
}