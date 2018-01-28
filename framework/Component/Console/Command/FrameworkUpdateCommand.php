<?php


    namespace Framework\Component\Console\Command;


    use Framework\Component\FrameworkUpdate\UpdateWorkStation;
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Helper\ProgressBar;
    use Symfony\Component\Console\Input\InputArgument;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    class FrameworkUpdateCommand extends Command
    {
        protected function configure()
        {
            $this
                // the name of the command (the part after "bin/console")
                ->setName('update')
                ->addArgument('station', InputArgument::REQUIRED, 'Tipo de estacion de trabajo.')

                // the short description shown while running "php bin/console list"
                ->setDescription('Manage the updates of the Skelet Framework Station ')

                // the full command description shown when running the command with
                // the "--help" option
                ->setHelp('Este comando le ayudara en la actualizacion del nucleo y estaciones de trabajo.'."\n")
            ;
        }

        /**
         *
         * @param InputInterface  $input
         * @param OutputInterface $output
         * @return int|null|void
         */
        protected function execute(InputInterface $input, OutputInterface $output)
        {
            $station = $input->getArgument('station');
            // outputs multiple lines to the console (adding "\n" at the end of each line)
            $output->writeln([
                '- - - - - - - - - - - - - - - - - - - - -',
                '- - - <info> Skelet Framework Update Station </info> - -',
                '- - - - - - - - - - - - - - - - - - - - -',
                '',
            ]);

            if($station =='workstation')
            {
                $update = new UpdateWorkStation($input, $output);
                $update->getFromMasterRepository();
            }
        }
    }