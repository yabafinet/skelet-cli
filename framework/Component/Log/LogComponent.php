<?php

    namespace Framework\Component\Log;


    use Framework\Component\BaseComponent;
    use Framework\Component\Cache\Cache;
    use Framework\Contracts\Log\Log;
    use Monolog\Logger as MonologLogger;
    use Symfony\Component\EventDispatcher\EventDispatcher;

    class LogComponent extends BaseComponent
    {

        function register(EventDispatcher $dispatcher, Cache $cache)
        {
            $this->app->container->singleton(Log::class, function () use ($dispatcher, $cache){

                $base_path = base_path().'/';

                // Default configurations
                // If not exist in .env.yml file.
                $log_config_default = [
                  'channel'     => 'Skelet Channel Logs',
                  'daily_path'  => $base_path.'framework/storage/logs',
                  'rotate_days' => 3,
                ];
                $log_config = env('log', $log_config_default);

                $log        = new LogWriter(new MonologLogger($log_config['channel']),$dispatcher, $log_config, $cache);

                $log->useDailyFiles($base_path.$log_config['daily_path'],$log_config['rotate_days']);

                $log->registerEventConfiguredListeners();

                return $log;
            });

        }

        function boot(){ }

    }