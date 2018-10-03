<?php


    namespace Framework\Contracts\Console;


    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    interface Command
    {

        public function configure();

        public function execute(InputInterface $input, OutputInterface $output);

    }