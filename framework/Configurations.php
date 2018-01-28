<?php


    namespace Framework;

    use Symfony\Component\Yaml\Exception\ParseException;
    use Symfony\Component\Yaml\Yaml;

    class Configurations {

        public $config              = [];
        public $configWithFile      = [];
        public $configs_framework   = [];
        public $configStatic        = [];
        public $appInstances        = [];
        private static $instance;


        /**
         * Configurations constructor.
         *
         * @param array $app_config
         */
        function __construct(array $app_config =[])
        {
            $this->loadEnvironment();
            $this->loadFrameworkConfigurations();
            $this->configStatic = $this->config;
        }


        /**
         * Configuraciones de Entornos
         */
        public function loadEnvironment()
        {
            $configs = [];

            try {
                $configs = Yaml::parse(file_get_contents(base_path().'/config/.env.yml'));

            } catch (ParseException $e) {
                //printf("Unable to parse the YAML string: %s", $e->getMessage());
            }

            $this->config = $configs;
        }



        /**
         * Cargando configuraciones de skelet-framework.
         * normalmente alojadas en ==> config/_framework/framework.yml
         *
         * @return void
         */
        public function loadFrameworkConfigurations()
        {
            $configs = [];

            try {
                $configs = Yaml::parse(
                    file_get_contents(base_path().'/config/_framework/framework.yml')
                );

            } catch (ParseException $e) {
                //printf("Unable to parse the YAML string: %s", $e->getMessage());
            }

            $this->configs_framework = $configs;
        }



        public static function getInstance()
        {
            if (self::$instance == null) {
                self::$instance = new Configurations();
            }
            return self::$instance;
        }

        /**
         * Parámetros de configuración del Framework
         *  normalmente config/framework.yml
         *
         * @param $key
         * @param $default_value
         * @return mixed
         */
        public static function get($key,$default_value ='')
        {
            $instance = self::getInstance();
            $key      = explode('.',$key);
            $valueKey = $instance->parseKey2($instance->configs_framework,$key);
            if(isset($valueKey))
                return $valueKey;
            else
                return $default_value;

        }

        /**
         * Configuraciones del Framework.
         *
         * @param $file
         * @return array|mixed
         */
        public function loadFileYml($file)
        {
            $configs = [];
            try {
                $configs = Yaml::parse(file_get_contents(__DIR__.'/../config/'.$file.'.yml'));

            } catch (ParseException $e) {
                //printf("Unable to parse the YAML string: %s", $e->getMessage());
            }

            return $configs;
        }

        /**
         * Parámetros de configuración del Framework
         * normalmente config/framework.yml
         *
         * @param $key
         * @param $default_value
         * @return mixed
         */
        public static function yml($key,$default_value ='')
        {
            $instance = self::getInstance();
            $key      = explode('.',$key);
            $file     = $key[0];

            if (isset($instance->configWithFile[$file])) {

                $valueKey = $instance->parseKey2($instance->configWithFile[$file],$key);

            } else {

                $instance->configWithFile[$file] = $instance->loadFileYml($file);
                $valueKey = $instance->parseKey2($instance->configWithFile[$file],$key);
            }

            if (isset($valueKey)) {

                return $valueKey;

            } else {

                return $default_value;
            }


        }

        /**
         *
         * @param $key
         * @param $default_value
         * @return mixed
         */
        static function firewall($key, $default_value ='')
        {
            return static::yml('firewall.'.$key,$default_value);
        }


        /**
         * Parámetros de configuración del entorno
         *  Desarrollo, Test y Producción
         *
         * @param $key
         * @param $default_value
         * @return mixed
         */
        public static function env($key,$default_value ='')
        {
            $instance = self::getInstance();
            $key      = explode('.',$key);
            $valueKey = $instance->parseKey2($instance->config,$key);
            if(isset($valueKey))
                return $valueKey;
            else
                return $default_value;

        }

        /**
         * @param $config
         * @param $key
         * @return mixed
         */
        private function parseKey2($config,$key)
        {
            foreach ($key as $k1)
            {
                if(isset($config[$k1])){
                    $config = $config[$k1];
                }else{
                }

            }
            return $config;
        }


        public function __clone()
        {
            trigger_error('__clone error: ' . __CLASS__, E_USER_ERROR);
        }

        /**
         * Modificar un valor de un archivo de configuración yaml
         *
         * @param       $file
         * @param array $key_value
         * @return bool|int
         * @internal param $key
         * @internal param $new_value
         */
        static function modifyConfigFile($file,array $key_value)
        {
            $yaml_values    = Yaml::parse(file_get_contents($file));
            $yaml_values    = array_replace_recursive($yaml_values, $key_value);
            $new_yaml       = Yaml::dump($yaml_values);

            return file_put_contents($file, $new_yaml);
        }

        static function getEnvironmentFile()
        {
            return base_path().'/config/.env.yml';
        }


    }