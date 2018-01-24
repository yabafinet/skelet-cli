<?php


    namespace Framework\Component\Console;


    use Symfony\Component\Process\Process as ProcessBase;

    class Process extends ProcessBase
    {


        static function exec($commandline)
        {
            $process = new ProcessBase($commandline);
            $process->run();

            return $process->getOutput();
        }

    }