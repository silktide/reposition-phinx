<?php

namespace Silktide\Reposition\Phinx;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * GenerateMigrationsCommand
 */
class InitialisePhinxCommand extends Command
{

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $outputDir;

    /**
     * @param string $outputDir
     * @param string $migrationDir
     * @param string $dbDriver
     * @param string $dbHost
     * @param string $dbName
     * @param string $dbUsername
     * @param string $dbPassword
     */
    public function __construct($outputDir, $migrationDir, $dbDriver, $dbHost, $dbName, $dbUsername, $dbPassword)
    {
        $this->outputDir = $outputDir;

        $this->config = [
            "paths" => ["migrations" => $migrationDir],
            "environments" => [
                "production" => [
                    "adapter" => $dbDriver,
                    "host" => $dbHost,
                    "name" => $dbName,
                    "user" => $dbUsername,
                    "pass" => $dbPassword,
                    "port" => 3306
                ]
            ]
        ];

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName("silktide:reposition:initialise-phinx")
            ->setDescription("Generate phinx.yml using the same credentials as reposition");
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename = $this->outputDir . "/phinx.yml";
        file_put_contents($filename, Yaml::dump($this->config, 10));
    }

}