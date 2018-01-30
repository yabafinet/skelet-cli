<?php


    namespace Framework\Component\Console\SkeletCli\LocalCommands;


    use Symfony\Component\Yaml\Exception\ParseException;
    use Symfony\Component\Yaml\Yaml;

    class Configuration
    {

        public $config = array();

        public function __construct()
        {
            $this->getConfigurationFromFile();
        }


        /**
         * Obtener la configuración de skelet framework.
         *
         * @return mixed
         */
        public function getConfigurationFromFile()
        {
            $configs = [];
            try {
                $configs = Yaml::parse(file_get_contents(base_path().'/config/_skelet-cli/workspace.yml'));

            } catch (ParseException $e) {

            }
            $this->config = $configs;

            return $this->config;
        }

        public function getProjectServerHost()
        {
            return $this->config['server']['host'];
        }

        /**
         * Obtener el nombre del usuario conectado al
         * sistema operativo. Normalmente se obtiene
         * cuando estamos ejecutando comandos remotos.
         *
         * @return string
         */
        public function currentOsUsername()
        {
            return trim($this->config['server']['user']);
        }


        public function getUserRepositoryPath()
        {
            return $this->getProjectPath().'/repos/'.$this->currentOsUsername();
        }

        public function getProjectPath()
        {
            return $this->config['project']['path'];
        }


    }