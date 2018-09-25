<?php
    use Framework\Component\Cache\Cache;
    use Framework\Component\Security\Authentication\SessionManager;
    use Framework\Component\Security\Encrypt\EncryptCore;
    use Framework\Component\Translation\Translator;
    use Framework\Component\UserInterface\HtmlAssistant\HtmlBuild;
    use Framework\Component\UserInterface\JavaScriptAssistant\JavaScript;
    use Framework\Component\UserInterface\MenuCreator;
    use Framework\Configurations;
    use Framework\Component\VarDumper\Dumper;
    use Illuminate\Container\Container;
    use Symfony\Component\HttpFoundation\Response;


    function app()
    {
        return Framework\App::singleton();
    }

    if (! function_exists('container')) {

        /**
         * Get the available container instance.
         *
         * @param  string  $abstract
         * @param  array   $parameters
         * @return mixed|Application
         */
        function container($abstract = null, array $parameters = [])
        {
            if (is_null($abstract)) {
                return Container::getInstance();
            }

            return empty($parameters)
                ? Container::getInstance()->make($abstract)
                : Container::getInstance()->makeWith($abstract, $parameters);
        }
    }

    /**
     * Obtener configuraciones de la lógica de negocio.
     *
     * @param           $key
     * @param   string  $default_value
     * @return  mixed
     */
    function config($key,$default_value ='')
    {
        return Configurations::get($key,$default_value);
    }

    /**
     * Obtener configuraciones de entornos,
     *
     * @param        $key
     * @param string $default_value
     * @return mixed
     */
    if (! function_exists('env')) {
        function env($key, $default_value = '')
        {
            return Configurations::env($key, $default_value);
        }
    }


    /**
     * Obtener configuraciones desde
     * un archivo .yml
     *
     * @param        $key
     * @param string $default_value
     * @return mixed
     */
    if (! function_exists('yml')) {
        function yml($key, $default_value = '')
        {
            return Configurations::yml($key, $default_value);
        }
    }


    function route($route)
    {
        return env("app.base_path").$route;
    }

    /**
     * @param $src
     * @return string
     */
    function asset($src)
    {
        $Assets = new Framework\Assets();
        return $Assets->set($src);

    }

    /**
     * @param       $view
     * @param array $params
     * @return string
     */

    function view($view,array $params =[])
    {
        $View = Framework\View::getInstance(app()->container);
        return $View->make($view,$params);
    }

    /**
     * Abortar Request Http
     *
     * @param $content
     * @param $status
     */
    function abort($content,$status = Response::HTTP_BAD_REQUEST)
    {
        app()->abort($content,$status);
    }


    if (!function_exists('dd'))
    {
        /**
         * @param  mixed
         * @return void
         */
        function dd(...$args)
        {
            foreach ($args as $x) {
                (new Dumper)->dump($x);
            }

            die(1);
        }
    }

    /**
     * @param array ...$args
     */
    function d(...$args)
    {
        foreach ($args as $x) {
            (new Dumper)->dump($x);
        }
    }

    function flash()
    {
        return \Framework\Component\UserInterface\FlashMessages::singleton();
    }

    /**
     * @return SessionManager
     */
    function session()
    {
        return SessionManager::singleton();
    }

    function auth()
    {
        return \Framework\Component\Security\Authentication\AuthController::singleton();
    }

    /**
     * @return \App\Models\Users|\Framework\Model\Model
     */
    function user()
    {
        return auth()->user();
    }


    /**
     *
     */
    function csrf_field()
    {
        return \Framework\Component\Security\CsrfProtectionComponent::csrfField();
    }


    function encrypt($value)
    {
        /** @var EncryptCore $encryptCore */
        $encryptCore   = app()->container->make(EncryptCore::class);
        $value         = $encryptCore->encrypt($value);
        return $value;
    }

    /**
     * @return \Framework\Component\Http\Request
     */
    function request()
    {
        return app()->request();
    }

    /**
     *
     * @param $key
     * @return mixed
     */
    function lang($key)
    {
        return app()->container->make(Translator::class)->get($key);
    }


    /**
     * @param null $key
     * @param null $value
     * @param null $ttl
     * @return bool|Cache|mixed|null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    function cache($key = null, $value = null, $ttl =null)
    {
        $cache = app()->container->make(Cache::class);

        // Set Value
        if(isset($key) && isset($value))
            return $cache->set($key,$value, $ttl);
        // Get a Value
        elseif (isset($key))
            return $cache->get($key);
        else
            return $cache;
    }

    /**
     *
     * @param $field_name
     * @param $value
     * @internal param $name
     * @return mixed|string
     */
    function input_default_value($field_name, $value ='')
    {
        /** @var \Framework\Component\Http\Request $request */
        $request         = app()->request;
        $valueValidation = $request->getValuesFromValidation($field_name);

        if(isset($valueValidation))
            return $valueValidation;
        else
            return $value;
    }


    function base_path()
    {
        return str_replace('/..','',dirname(__DIR__));
    }

    /**
     *
     * @return JavaScript
     */
    function js()
    {
        return new JavaScript();
    }

    /**
     *
     * @return HtmlBuild
     */
    function html()
    {
        return new HtmlBuild();
    }

    /**
     * @return MenuCreator
     */
    function ui_menu()
    {
        return app()->container->make(MenuCreator::class);
    }
