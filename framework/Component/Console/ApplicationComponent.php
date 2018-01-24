<?php


    namespace Framework\Component\Console;


    use Framework\Component\BaseComponent;
    use Symfony\Component\Console\Application as BaseApplicationConsole;

    class ApplicationComponent extends BaseComponent
    {

        function register()
        {
            $this->app->container->singleton(Application::class, function (){

                $application = new Application(new BaseApplicationConsole(), $this->app);

                $application->registerConfiguredCommands();

                return $application;

            });
        }

    }