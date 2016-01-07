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

    public function __construct(MigrationGenerator $generator, array $entityList)
    {
        $this->generator = $generator;
        $this->entityList = $entityList;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName("silktide:reposition:generate-migrations")
            ->setDescription("Create migration files for entities, ready to be migrated using Phinx")
            ->addArgument("entities", InputArgument::IS_ARRAY, "The list of entities you want to generate migration files for");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $entities = $input->getArgument("entities");
        if (empty($entities)) {
            $entities = $this->entityList;
        } else {
            // parse entities to make sure they are in the list
            $diff = array_diff($entities, $this->entityList);
            if (!empty($diff)) {
                $output->writeln("<error>Unrecognised entities</error>");
                $output->writeln("<comment>The following entities are not available</comment>");
                foreach ($diff as $entity) {
                    $output->writeln("<info>$entity</info>");
                }
                return;
            }
        }

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