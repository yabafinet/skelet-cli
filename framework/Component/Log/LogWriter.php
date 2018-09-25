<?php
    /**
     * Desde esta parte manejamos todos los log
     * disparamos eventos, segun el nivel de errores.
     */

    namespace Framework\Component\Log;


    use Closure;
    use Framework\Component\Cache\Cache;
    use Framework\Component\Events\Logs\MessageLoggedEvent;
    use Framework\Contracts\Log\Log;
    use Illuminate\Log\Writer;
    use Illuminate\Support\Str;
    use Monolog\Logger as MonologLogger;
    use Psr\SimpleCache\InvalidArgumentException;
    use Symfony\Component\EventDispatcher\EventDispatcher;

    class LogWriter extends Writer implements Log
    {

        private $config;
        public  $cache;


        /**
         * Create a new log writer instance.
         *
         * @param  \Monolog\Logger $monolog
         * @param EventDispatcher $dispatcher
         * @param array $config
         * @param Cache $cache
         */
        public function __construct(MonologLogger $monolog, EventDispatcher $dispatcher = null, array $config, Cache $cache =null)
        {
            $this->monolog = $monolog;

            if (isset($dispatcher)) {
                $this->dispatcher = $dispatcher;
            }

            $this->config   = $config;
            $this->cache    = $cache;
        }

        function isCache()
        {
            return isset($this->cache);
        }


        /**
         * Fires a log event.
         *
         * @param  string $level
         * @param  string $message
         * @param  array $context
         * @return void
         * @throws InvalidArgumentException
         */
        protected function fireLogEvent($level, $message, array $context = [])
        {
            // If the event dispatcher is set, we will pass along the parameters to the
            // log listeners. These are useful for building profilers or other tools
            // that aggregate all of the log messages for a given "request" cycle.

            $this->increaseNumberOfLogReported($level);

            if (isset($this->dispatcher)) {

                $this->dispatchIfIsAllEvents($level, $message, $context);
                // Dispatch an specific level events

            }
        }

        /**
         * Dispatch a events listeners all level.
         *
         * @param $level
         * @param $message
         * @param array $context
         */
        function dispatchIfIsAllEvents($level, $message, array $context = [])
        {
            // Dispatch all events
            $this->dispatcher->dispatch('log.*', new MessageLoggedEvent($level, $message, $context));
        }

        /**
         * Dispatch a events listeners a specific number of reported.
         *
         * @param $level
         * @param $message
         * @param array $context
         */
        function dispatchReportedNumberComplete($level, $message, array $context = [])
        {
            try {
                $this->dispatcher->dispatch(
                    'log.' . $level,
                    new MessageLoggedEvent(
                        $level, $message, $context, $this->getNumberOfLogReported($level), app()
                    )
                );
            } catch (InvalidArgumentException $e) {
            };
        }

        /**
         *
         * @param $level
         * @throws InvalidArgumentException
         */
        function increaseNumberOfLogReported($level)
        {
            $this->cache->increase('log.reported.'.$level);
        }

        /**
         *
         * @param $level
         * @throws \Psr\SimpleCache\InvalidArgumentException
         */
        function getNumberOfLogReported($level)
        {
            $this->cache->get('log.reported.'.$level);
        }


        /**
         * Register a new callback handler for when a log event is triggered.
         *
         * @param  \Closure $callback
         * @param array $configs
         * @return void
         */
        public function listen(Closure $callback, array $configs =[])
        {
            if (! isset($this->dispatcher)) {
                throw new \RuntimeException('Events dispatcher has not been set.');
            }

            if(is_array($configs['levels'])) {
                foreach ($configs['levels'] as $level) {

                    if(Str::contains(':', $level)){
                        $level = Str::replaceFirst(':','.', $level);
                    }
                    $this->dispatcher->addListener('log.'.$level, $callback);
                }
            }

        }


        /**
         * Registering the configured listeners.
         *
         * @return $this
         */
        function registerEventConfiguredListeners()
        {
            $listeners_log = $this->config['events.log.listeners'];

            foreach ($listeners_log as $listener_class=>$config)
            {
                if(is_array($listener_class)) {
                    $this->listen(new $listener_class, $config);
                }

            }

            return $this;
        }

    }