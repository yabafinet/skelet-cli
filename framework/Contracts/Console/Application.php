<?php


    namespace Framework\Contracts\Console;


    use Symfony\Component\Console\Command\Command;

    interface Application
    {

        public function run();
        public function add(Command $command);
    }