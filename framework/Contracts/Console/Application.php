<?php


    namespace Framework\Contracts\Console;


    use Symfony\Component\Console\Command\Command;

    interface Application
    {

        function run();
        function add(Command $command);
    }