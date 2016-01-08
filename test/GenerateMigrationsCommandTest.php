<?php

namespace Silktide\Reposition\Phinx\Test;
use Silktide\Reposition\Phinx\GenerateMigrationsCommand;

/**
 * GenerateMigrationsCommandTest
 */
class GenerateMigrationsCommandTest extends \PHPUnit_Framework_TestCase
{

    protected $generator;

    protected $output;

    protected $outputInterface;

    protected $inputInterface;

    public function setup()
    {
        $this->generator = \Mockery::mock("Silktide\\Reposition\\Phinx\\MigrationGenerator");
        $this->generator->shouldIgnoreMissing(false);

        $this->output = [];

        $this->outputInterface = \Mockery::mock("Symfony\\Component\\Console\\Output\\OutputInterface");
        $this->outputInterface->shouldReceive("writeln")->andReturnUsing(function($message) {
            $this->output[] = $message;
            return null;
        });

        $this->inputInterface = \Mockery::mock("Symfony\\Component\\Console\\Input\\InputInterface");
        $this->inputInterface->shouldIgnoreMissing(false);

    }

    /**
     * @dataProvider entityProvider
     *
     * @param array $entities
     */
    public function testMigrationsAreGenerated($entities)
    {
        $this->inputInterface->shouldReceive("getArgument")->with("entities")->andReturn($entities);

        $command = new GenerateMigrationsCommand($this->generator);
        $command->run($this->inputInterface, $this->outputInterface);

        $entityCount = count($entities);

        $lastLine = array_pop($this->output);

        $this->assertContains("$entityCount", $lastLine);
    }

    public function entityProvider()
    {
        return [
            [["one"]],
            [["two", "three"]],
            [["one", "two", "three", "four"]]
        ];
    }

}
