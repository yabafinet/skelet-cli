<?php

    try{

        // Load App PSR-4
        spl_autoload_register(function ($class_name) {

            $class_name = str_replace(
                ['\\','Framework/','App/'],
                ['/','framework/','app/'],
                $class_name
            );
            $file       = __DIR__.'/../'.$class_name.'.php';

            if(file_exists($file))
                require $file;

            //echo "require: ".$file."\n";

        });

    }catch (Exception $e)
    {

    }

    // Helpers:
    require_once __DIR__.'/Helpers.php';