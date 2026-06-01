<?php

namespace Ramblers\Plugin\Console\Ra_delivery\Command;

defined('JPATH_PLATFORM') or die;

use Joomla\Console\Command\AbstractCommand;
use Ramblers\Component\Ra_delivery\Site\Helper\ActivityHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PollactivityCommand extends AbstractCommand
{
    protected static $defaultName = 'ra_delivery:pollactivity';

    private $helper;
    private $ioStyle;

    public function __construct()
    {
        parent::__construct();
        $this->helper = new ActivityHelper();
    }

    protected function configure(): void
    {
        $help = "<info>%command.name%</info> Poll provider activity\n"
            . "Usage: <info>php %command.full_name%</info>\n"
            . "Fetches configured SMTP2GO activity events and stores them locally.";

        $this->setDescription('Poll SMTP2GO account activity');
        $this->setHelp($help);
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $this->ioStyle = new SymfonyStyle($input, $output);
        $this->ioStyle->comment('Starting delivery activity poll');

        $result = $this->helper->pollConfiguredEvents();

        foreach ($this->helper->getMessages() as $message) {
            $this->ioStyle->comment($message);
        }

        if ($result === false) {
            $this->ioStyle->error('Delivery activity poll failed');
            return 1;
        }

        $this->ioStyle->success('Delivery activity poll complete');
        return 0;
    }
}
