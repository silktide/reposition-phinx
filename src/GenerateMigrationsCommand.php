<?php

namespace Silktide\Reposition\Phinx;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * GenerateMigrationsCommand
 */
class GenerateMigrationsCommand extends Command
{

    protected $generator;

    protected $entityList;

    public function __construct(MigrationGenerator $generator)
    {
        $this->generator = $generator;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName("silktide:reposition:generate-migrations")
            ->setDescription("Create migration files for entities, ready to be used with Phinx")
            ->addArgument("entities", InputArgument::IS_ARRAY | InputArgument::REQUIRED, "The list of entities you want to generate migration files for");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $entities = $input->getArgument("entities");

        $counter = 0;
        foreach ($entities as $entity) {
            $output->writeln("<comment>Generating migration for </comment><info>$entity</info>");
            $this->generator->generateMigrationFor($entity);
            ++$counter;
        }

        $message = $counter == 1
            ? "migration was generated"
            : "migrations were generated";

        $output->writeln("");
        $output->writeln("<comment>Done - $counter $message</comment>");
    }



}