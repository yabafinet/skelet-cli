<?php

    namespace Framework;

    use Framework\Component\Events\InternalEventsDispatcher;
    use Framework\Controller\ArgumentMetadataFactory;
    use Framework\Controller\ControllerResolver;
    use Illuminate\Container\Container;
    use Symfony\Component\EventDispatcher\EventDispatcher;
    use Framework\Component\Http\RedirectResponse;
    use Framework\Component\Http\Request;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Routing;
    use Symfony\Component\HttpKernel;
    use Framework\Controller\Controller;
    use Whoops\Run as Whoops_Run;
    use Whoops\Handler\PrettyPageHandler as Whoops_PrettyPageHandler;
    use Framework\Component\Debug\Debug as InternalDebug;

    class App
    {
        public  $app;
        public  static $instance;
        public  $request;
        public  $context;
        public  $route;
        public  $view;
        public  $dispatcher;
        public  $controller;
        public  $db;
        private $app_config         =[];
        private $configurations     =[];
        public  $config;
        public  $isAbort            = false;
        public  $response;
        public  $debugMode          = false;
        public  $bootComponents;
        public  $internalEventsDispatcher;
        public  $container;
        public  $application_type   = 'web'; // web, api, unitTest y console.


        /**
         * App constructor.
         */
        function __construct()
        {

            if($this->debugMode == true)
            {
                $whoops = new Whoops_Run;
                $whoops->pushHandler(new Whoops_PrettyPageHandler);
                //$whoops->allowQuit(false);
                $whoops->register();
            }

            $this->setConfig();

            $this->container        = new Container();
            //$this->request          = Request::singleton();
            //$this->route            = Route::getInstance();
            //$this->dispatcher       = new EventDispatcher();
            //$this->view             = View::getInstance($this->container); // <-- Singleton
            //$this->controller       = new Controller();
            //$this->response         = new Response();

            if ($this->application_type =='web') {

                $this->prepareFromWebApplication();

            } elseif ($this->application_type == 'console') {

                $this->prepareFromConsoleApplication();
            }


            $this->bootComponents   = new Component\BootComponents($this);

            $this->internalEventsDispatcher   = new InternalEventsDispatcher(
                $this->dispatcher, $this->request
            );

        }

        function prepareFromWebApplication()
        {
            $this->request          = Request::singleton();
            $this->route            = Route::getInstance();
            $this->dispatcher       = new EventDispatcher();
            $this->view             = View::getInstance($this->container); // <-- Singleton
            $this->controller       = new Controller();
            $this->response         = new Response();
        }

        function prepareFromConsoleApplication()
        {
            //$this->request          = Request::singleton();
            //$this->route            = Route::getInstance();
            $this->dispatcher       = new EventDispatcher();
            //$this->view             = View::getInstance($this->container); // <-- Singleton
            //$this->controller       = new Controller();
            //$this->response         = new Response();
        }


        /**
         * Punto iniciar del Framework.
         *
         */
        public function buildFromWebApplication()
        {
            $this->prepareFromWebApplication();

            try
            {
                // Components (Service Provider Register Method)
                $this->bootComponents->loadComponents('register');

                // Route Collection:
                $this->route->build($this->request);

                // Internals Events
                $this->internalEventsDispatcher->dispatch(); // <-- Disparando eventos del framework.

                list($controller, $arguments) = $this->resolverController();

                // Components (Service Provider Boot Method)
                $this->bootComponents->loadComponents('boot');


                if($this->isAbort ==false)
                {
                    if(!method_exists($controller[0],$controller[1]))
                    {
                        $this->abort('Not Found Action:'.$controller[0]."::".$controller[1], 404);

                    }else{

                        $response = call_user_func_array($controller, $arguments);

                        if($this->isAbort ==false)
                            $this->response( $response );
                    }

                } else {

                }



            } catch (SecurityCoreException $e)
            {
                $this->response('Security Exception: '.$e->getCode(), Response::HTTP_UNAUTHORIZED);

            } catch (Routing\Exception\ResourceNotFoundException $e)
            {
                $this->response('Not Found!', Response::HTTP_NOT_FOUND);

            }
            catch (Exception $exception)
            {
                //echo $exception->getMessage();
                InternalDebug::log("RunTimeException",$exception->getTrace());
            }

            // dispatch a on.response.send event
            $this->internalEventsDispatcher->onResponseSend();
            $this->response->send();

            if(!$this->isAbort)
                Component\Debug\Debug::showDebugInformation();

            //echo "Container Debug:";
            //d($this->container);

        }


        /**
         * Punto iniciar del Framework.
         *
         */
        public function buildFromConsoleApplication()
        {

            // Components (Service Provider Register Method)
            $this->bootComponents->loadComponents('register');

            // Route Collection:
            //$this->route->build($this->request);

            // Internals Events
            $this->internalEventsDispatcher->dispatch(); // <-- Disparando eventos del framework.

            //list($controller, $arguments) = $this->resolverController();

            // Components (Service Provider Boot Method)
            $this->bootComponents->loadComponents('boot');

        }

        /**
         * @return Container
         */
        public function getContainer()
        {
            return $this->container;
        }

        /**
         * @return EventDispatcher
         */
        public function event($eventName ='')
        {
            return $this->dispatcher;
        }
        /**
         * @return Request
         */
        function request()
        {
            return Request::singleton();
        }


        private function setConfig()
        {
            date_default_timezone_set("America/Santo_Domingo");
            $this->config = require_once __DIR__.'/../config/app.php';
            return $this->app_config;
        }

        public function getConfig($tag_name)
        {
            return $this->app_config[$tag_name];
        }



        /**
         * @param string $content
         * @param int    $status
         */
        function response($content ='', $status = Response::HTTP_OK)
        {
            $this->response->setContent($content);
            $this->response->setStatusCode($status);
            //$this->event()->dispatch('on.request.response', new RequestResponseEvent($this->response, $this->request));
        }

        /**
         *
         * @param array $data
         * @param bool $abort
         */
        function responseJson(array $data, $abort = false)
        {
            $this->response = new JsonResponse($data);
            $this->response->send();

            if ($abort ==true)
                exit;

        }

        /**
         *
         * @param $key
         * @param null $default
         */
        function input($key, $default = null)
        {
            $this->request()->get($key, $default);
        }

        /**
         *
         * @param string $content
         * @param int    $status
         */
        function abort($content ='', $status = Response::HTTP_BAD_REQUEST)
        {
            $this->isAbort  = true;
            $this->response($content, $status);
            $this->response->send();
            exit;
        }

        /**
         *
         * @param       $url
         * @param array $with
         * @internal param int $status
         */
        function redirect($url, array $with = null)
        {
            $this->isAbort  = true;
            $this->response = new RedirectResponse(env("app.base_path").$url);

            if(isset($with))
                $this->response->with($with);

            $this->response->send();
            exit;
        }



        /**
         * Singleton de la instancia App principal
         *  del framework.
         *
         * @return App
         */
        public static function singleton()
        {
            if (self::$instance == null)
                self::$instance = new App();

            return self::$instance;
        }

        function setSingleton()
        {
            self::singleton();
        }

        /**
         *
         * @return array
         */
        public function resolverController()
        {
            $controllerResolver     = new ControllerResolver(null, $this->container);
            $controller             = $controllerResolver->getController($this->request);
            $arguments              = $controllerResolver->getArguments($this->request, $controller);
            $this->controller       = $controller[0];
            $result                 = array($controller, $arguments);

            return $result;
        }

        /**
         * Obtener el tipo de aplicacion que
         * se esta ejecutando.
         *
         * @return string
         */
        static function type()
        {
            return self::singleton()->application_type;
        }

        function setTypeApplication($type)
        {
            $this->application_type = $type;
        }


    }