<?php


    namespace Framework\Contracts\Console;


    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    interface Command
    {

        function configure();

        function execute(InputInterface $input, OutputInterface $output);

    }