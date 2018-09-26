<?php

    namespace Framework;

    use Framework\Component\Events\InternalEventsDispatcher;
    use Framework\Controller\ArgumentMetadataFactory;
    use Framework\Controller\ControllerResolver;
    use Framework\Events\RequestResponseEvent;
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

        /**
         * @var Route
         */
        public  $route;

        /**
         * @var View
         */
        public  $view;
        public  $dispatcher;
        public  $controller;
        public  $db;
        private $app_config         = [];
        private $configurations     = [];
        public  $config;
        public  $isAbort            = false;
        /**
         * @var Response
         */
        public  $response;
        public  $debugMode          = false;
        public  $bootComponents;
        public  $internalEventsDispatcher;
        public  $container;
        public  $application_type   = 'web'; // web, api, unitTest y console.


        /**
         * App constructor.
         */
        public function __construct()
        {

            if($this->debugMode == true) {
                $whoops = new Whoops_Run;
                $whoops->pushHandler(new Whoops_PrettyPageHandler);
                //$whoops->allowQuit(false);
                $whoops->register();
            }

            $this->setConfig();

            $this->container  = new Container();

            if ($this->application_type =='web') {

                $this->prepareFromWebApplication();

            } elseif ($this->application_type == 'api') {

                $this->prepareFromApiApplication();

            } elseif ($this->application_type == 'console') {

                $this->prepareFromConsoleApplication();
            }


            $this->bootComponents   = new Component\BootComponents($this);

            $this->internalEventsDispatcher   = new InternalEventsDispatcher(
                $this->dispatcher, $this->request
            );

        }

        public function prepareFromWebApplication()
        {
            $this->request          = Request::singleton();
            $this->route            = Route::getInstance();
            $this->dispatcher       = new EventDispatcher();
            $this->view             = View::getInstance($this->container);
            $this->controller       = new Controller();
            $this->response         = new Response();
        }

        public function prepareFromApiApplication()
        {
            $this->request          = Request::singleton();
            $this->route            = Route::getInstance();
            $this->dispatcher       = new EventDispatcher();
            $this->view             = View::getInstance($this->container);
            $this->controller       = new Controller();
            $this->response         = new Response();
        }

        public function prepareFromConsoleApplication()
        {
            $this->dispatcher       = new EventDispatcher();
        }


        /**
         * Punto iniciar del Framework.
         *
         */
        public function buildFromWebApplication()
        {
            $this->prepareFromWebApplication();

            try {
                // Components (Service Provider Register Method)
                $this->bootComponents->loadComponents('register');

                // Route Collection:
                $this->route->build($this->request);

                // Internals Events
                $this->internalEventsDispatcher->dispatch(); //<-- Eventos del framework.

                list($controller, $arguments) = $this->resolverController();

                // Components (Service Provider Boot Method)
                $this->bootComponents->loadComponents('boot');


                if($this->isAbort ==false) {
                    if(!method_exists($controller[0],$controller[1])) {
                        $this->abort(
                            'Not Found Action:'.$controller[0]
                                    ."::" .$controller[1],
                            404
                        );

                    } else {

                        $response = call_user_func_array($controller, $arguments);

                        if($this->isAbort ==false)
                            $this->response( $response );
                    }
                }


            } catch (SecurityCoreException $e) {
                $this->response(
                    'Security Exception: ' .$e->getCode(),
                    Response::HTTP_UNAUTHORIZED
                );

            } catch (Routing\Exception\ResourceNotFoundException $e) {

                $this->response('', Response::HTTP_NOT_FOUND);

            }


            // dispatch a on.response.send event
            $this->internalEventsDispatcher->onResponseSend();
            $this->response->send();

            //d(Component\Debug\Debug::showDebugInformation());
            //d($this->container);

        }


        /**
         * Punto iniciar del Framework.
         */
        public function buildFromConsoleApplication()
        {

            // Components (Service Provider Register Method)
            $this->bootComponents->loadComponents('register');

            // Internals Events
            $this->internalEventsDispatcher->dispatch(); // <-- Eventos del framework.

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
         * @param string $eventName
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
         * @param int $status
         * @return Response
         */
        public function response($content ='', $status = Response::HTTP_OK)
        {
            if($content) {
                $this->response->setContent($content);
                $this->response->setStatusCode($status);
            }

            // Disparando Eventos de Respuesta al Cliente:
            $this->event()->dispatch(
                'on.request.response', new RequestResponseEvent($this->response, $this->request)
            );

            return $this->response;
        }

        /**
         *
         * @param array $data
         * @param bool  $abort
         * @param int   $status
         */
        public function responseJson(array $data, $abort = false, $status = 200)
        {
            $this->response = new JsonResponse($data, $status);
            $this->response->send();

            if ($abort ==true)
                exit;
        }

        /**
         *
         * @param $key
         * @param null $default
         */
        public function input($key, $default = null)
        {
            $this->request()->get($key, $default);
        }

        /**
         *
         * @param string $content
         * @param int    $status
         */
        public function abort($content ='', $status = Response::HTTP_BAD_REQUEST)
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
        public function redirect($url, array $with = null)
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

        public function setSingleton()
        {
            self::singleton();
        }

        /**
         *
         * @return array
         */
        public function resolverController()
        {
            $controllerResolver     = new ControllerResolver(
                null, $this->container
            );
            $controller             = $controllerResolver->getController($this->request);
            $arguments              = $controllerResolver->getArguments($this->request, $controller);

            $this->controller       = is_array($controller)
                    ? $controller[0]
                    : $controller;

            $result                 = array($controller, $arguments);

            return $result;
        }

        /**
         * Obtener el tipo de aplicación que
         * se esta ejecutando.
         *
         * @return string
         */
        public static function type()
        {
            return self::singleton()->application_type;
        }

        public function setTypeApplication($type)
        {
            $this->application_type = $type;
        }


    }