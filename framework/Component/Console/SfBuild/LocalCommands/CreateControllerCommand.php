<?php

    namespace Framework\Component\Console\SfBuild\LocalCommands;


    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Framework\Component\Console\SfBuild\StartCommand;
    use Framework\Component\Console\SfBuild\FrameServerCommandInterface;

    class CreateControllerCommand extends Command implements FrameServerCommandInterface
    {
        public $start_command;

        /**
         * CreateControllerCommand constructor.
         *
         * @param StartCommand $startCommand
         */
        public function __construct(StartCommand $startCommand = null)
        {
            parent::__construct();

            $this->start_command = $startCommand;
        }


        protected function configure()
        {

            $this
                ->setName('create:controller')
                ->addArgument('name', InputArgument::REQUIRED, 'Nombre del controlador.')
                ->setDescription('Asistente para la creacion de controladores.')
                ->setHelp(''."\n")
            ;

        }


        protected function execute(InputInterface $input, OutputInterface $output)
        {
            //$this->start_command->info('Comando '.$this->getName()." ejecutado!");
        }
    }