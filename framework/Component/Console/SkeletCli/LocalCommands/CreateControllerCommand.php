<?php

    namespace Framework\Component\Console\SkeletCli\LocalCommands;


    use Framework\Component\Console\SkeletCli\Utilities;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Framework\Component\Console\SkeletCli\InitCommand;
    use Framework\Component\Console\SkeletCli\FrameServerCommandInterface;

    class CreateControllerCommand extends Command implements FrameServerCommandInterface
    {
        public $start_command;

        /**
         * CreateControllerCommand constructor.
         *
         * @param InitCommand $startCommand
         */
        public function __construct(InitCommand $startCommand = null)
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
            Utilities::local($input, $output)->info('create:controller ejecutado...');
        }
    }