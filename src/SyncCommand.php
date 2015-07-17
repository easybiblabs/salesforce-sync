<?php
/**
 * Copyright (c) 2015 Imagine Easy Solutions LLC
 * MIT licensed, see LICENSE file for details.
 */
namespace EasyBib\Silex\Salesforce;

use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends BaseCommand
{
    /** @var Service */
    private $service;

    /**
     * @param Service $service
     */
    public function __construct(Service $service)
    {
        $this->service = $service;
        parent::__construct();
    }

    /**
     * Configuring the Command
     */
    public function configure()
    {
        $this
            ->setName('salesforce:sync')
            ->setDescription('Update the salesforce cache DB via the salesforce API')
            ->addOption('id', null, InputOption::VALUE_OPTIONAL, 'Account ID to sync (if not given, sync all accounts)')
            ->setHelp('Queries the salesforce API and writes into a local database');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $id = $input->getOption('id');
        if ($id === null) {
            $output->writeln('<info>Syncing with all Salesforce accounts</info>');
        } else {
            $output->writeln(sprintf('<info>Syncing with Salesforce account %s</info>', $id));
        }
        list($salesforceRecords, $localUpdates) = $this->service->sync($id);
        $output->writeln(sprintf('<info>Got <comment>%d</comment> records from Salesforce</info>', $salesforceRecords));
        $output->writeln(sprintf('<info>Updated <comment>%d</comment> local records</info>', $localUpdates));
    }
}
