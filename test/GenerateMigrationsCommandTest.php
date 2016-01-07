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

    public function testInvalidEntities()
    {
        $entityList = [
            "two",
            "three"
        ];

        $requestedEntities = [
            "one",
            "three",
            "four"
        ];

        $this->inputInterface->shouldReceive("getArgument")->with("entities")->andReturn($requestedEntities);

        $command = new GenerateMigrationsCommand($this->generator, $entityList);
        $command->run($this->inputInterface, $this->outputInterface);

        $outputCount = count($this->output);

        $this->assertGreaterThan(2, $outputCount, "Check number of output lines greater than 2");

        $lookingFor = [
            "one" => true,
            "two" => false,
            "three" => false,
            "four" => true
        ];

        $this->assertContains("Unrecognised", $this->output[0]);

        foreach ($lookingFor as $item => $expected) {
            $found = false;
            for($i = 2; $i < $outputCount; ++$i) {
                if (strpos($this->output[$i], $item) !== false) {
                    $found = true;
                    break;
                }
            }
            $this->assertEquals($expected, $found, "Checking if '$item' appears in the output. Should be " . ($expected? "true": "false"));
        }

    }

    /**
     * @dataProvider entityProvider
     *
     * @param array $entities
     */
    public function testMigrationsAreGenerated($entities)
    {
        $entityList = [
            "one",
            "two",
            "three",
            "four"
        ];

        $this->inputInterface->shouldReceive("getArgument")->with("entities")->andReturn($entities);

        $command = new GenerateMigrationsCommand($this->generator, $entityList);
        $command->run($this->inputInterface, $this->outputInterface);

        $entityCount = count($entities);
        if (empty($entityCount)) {
            $entityCount = count($entityList);
        }

        $lastLine = array_pop($this->output);

        $this->assertContains("$entityCount", $lastLine);
    }

    public function entityProvider()
    {
        return [
            [["one"]],
            [["two", "three"]],
            [[]]
        ];
    }

}
