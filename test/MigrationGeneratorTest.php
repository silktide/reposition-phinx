<?php

namespace Silktide\Reposition\Phinx\Test;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamWrapper;
use Silktide\Reposition\Metadata\EntityMetadata;
use Silktide\Reposition\Phinx\MigrationGenerator;

/**
 * MigrationGeneratorTest
 */
class MigrationGeneratorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Mockery\Mock|\Silktide\Reposition\Metadata\EntityMetadataProviderInterface
     */
    protected $metadataProvider;

    /**
     * @var \Mockery\Mock|\Silktide\Reposition\Metadata\EntityMetadata
     */
    protected $metadata;

    /**
     * @var \Mockery\Mock|\Silktide\Reposition\Metadata\EntityMetadata
     */
    protected $intermediaryMetadata;

    protected $testDir = "test";

    protected $outputDir;

    public function setup()
    {
        $this->metadata = \Mockery::mock("Silktide\\Reposition\\Metadata\\EntityMetadata");
        $this->metadata->shouldReceive("getCollection")->andReturn("test", "one");

        $this->intermediaryMetadata = \Mockery::mock("Silktide\\Reposition\\Metadata\\EntityMetadata");

        $this->metadataProvider = \Mockery::mock("Silktide\\Reposition\\Metadata\\EntityMetadataProviderInterface");
        $this->metadataProvider->shouldReceive("getEntityMetadata")->andReturn($this->metadata);
        $this->metadataProvider->shouldReceive("getEntityMetadataForIntermediary")->andReturn($this->intermediaryMetadata);

        $this->outputDir = vfsStream::url($this->testDir);

        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory($this->testDir, 0777));
    }

    /**
     * @dataProvider fieldListProvider
     *
     * @param array $fieldList
     * @param array $expectedPatterns
     * @param string $primaryKey
     */
    public function testProcessingFields(array $fieldList, array $expectedPatterns, array $unexpectedPatterns = [], $primaryKey = "id")
    {
        $this->metadata->shouldReceive("getFields")->andReturn($fieldList);
        $this->metadata->shouldReceive("getRelationships")->andReturn([]);
        $this->metadata->shouldReceive("getPrimaryKey")->andReturn($primaryKey);

        $generator = new MigrationGenerator($this->metadataProvider, $this->outputDir);
        $fileName = $generator->generateMigrationFor("Test\\Entity");

        $this->assertFileExists($fileName);

        $fileContents = file_get_contents($fileName);

        foreach ($expectedPatterns as $pattern) {
            $this->assertRegExp($pattern, $fileContents);
        }

        foreach ($unexpectedPatterns as $pattern) {
            $this->assertNotRegExp($pattern, $fileContents);
        }
    }

    public function fieldListProvider()
    {
        return [
            [ #0 single string field
                [
                    "id" => [EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_INT],
                    "first_field" => [EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_STRING]
                ],
                ["/addColumn.*first_field.*string.*limit/"]
            ],
            [ #1 multiple fields of varying types
                [
                    "id" => [EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_INT],
                    "first_field" => [EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_INT],
                    "second_field" => [EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_FLOAT],
                    "third_field" => [EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_BOOL],
                    "fourth_field" => [EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_DATETIME],
                    "fifth_field" => [EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_ARRAY]
                ],
                [
                    "/addColumn.*first_field.*integer/",
                    "/addColumn.*second_field.*float/",
                    "/addColumn.*third_field.*boolean/",
                    "/addColumn.*fourth_field.*datetime/",
                    "/addColumn.*fifth_field.*text/"
                ]
            ],
            [ #2 alternate primary key
                [
                    "first_field" => [EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_INT]
                ],
                ["/table.*id..?=>.?.first_field.*/"],
                ["/addColumn.*first_field/"],
                "first_field"
            ],
            [ #3 non-incrementing alternate primary key
                [
                    "first_field" => [
                        EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_STRING,
                        EntityMetadata::METADATA_FIELD_AUTO_INCREMENTING => false
                    ]
                ],
                [
                    "/table.*id.*false.*primary_key.*first_field/",
                    "/addColumn.*first_field.*string.*limit/"

                ],
                [],
                "first_field"
            ]
        ];
    }

    /**
     * @expectedException \Silktide\Reposition\Phinx\Exception\MigrationGenerationException
     * @expectedExceptionMessageRegExp #.*primary key.*could not be found.*#
     */
    public function testExceptionOnMissingPrimaryKey()
    {
        $this->metadata->shouldReceive("getFields")->andReturn([]);
        $this->metadata->shouldReceive("getRelationships")->andReturn([]);
        $this->metadata->shouldReceive("getPrimaryKey")->andReturn("id");

        $generator = new MigrationGenerator($this->metadataProvider, $this->outputDir);
        $generator->generateMigrationFor("Test\\Entity");
    }

    /**
     * @dataProvider relationshipProvider
     *
     * @param array $relationships
     * @param array $expectedPatterns
     * @param array $unexpectedPatterns
     * @param string $intermediaryCollection
     */
    public function testProcessingRelationships(array $relationships, array $expectedPatterns, array $unexpectedPatterns = [], $intermediaryCollection = "")
    {
        $outputDir = vfsStream::url($this->testDir);
        $this->metadata->shouldReceive("getFields")->andReturn([
            "id" => [EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_INT]
        ]);
        $this->metadata->shouldReceive("getPrimaryKey")->andReturn("id");
        $this->metadata->shouldReceive("getRelationships")->andReturn($relationships);

        if (!empty($intermediaryCollection)) {
            // setup intermediary metadata
            $this->intermediaryMetadata->shouldReceive("getCollection")->andReturn($intermediaryCollection);
        }


        $generator = new MigrationGenerator($this->metadataProvider, $outputDir);
        $fileName = $generator->generateMigrationFor("Test\\Entity");

        $this->assertFileExists($fileName);

        $fileContents = file_get_contents($fileName);

        foreach ($expectedPatterns as $pattern) {
            $this->assertRegExp($pattern, $fileContents);
        }

        foreach ($unexpectedPatterns as $pattern) {
            $this->assertNotRegExp($pattern, $fileContents);
        }
    }

    public function relationshipProvider()
    {

        return [
            [ #0 our field
                [
                    "one" => [
                        EntityMetadata::METADATA_RELATIONSHIP_TYPE => EntityMetadata::RELATIONSHIP_TYPE_ONE_TO_ONE,
                        EntityMetadata::METADATA_RELATIONSHIP_OUR_FIELD => "one_id"
                    ]
                ],
                ["/addColumn.*one_id.*integer/"]
            ],
            [ #1 their field
                [
                    "one" => [
                        EntityMetadata::METADATA_RELATIONSHIP_TYPE => EntityMetadata::RELATIONSHIP_TYPE_ONE_TO_ONE,
                        EntityMetadata::METADATA_RELATIONSHIP_THEIR_FIELD => "test_id"
                    ]
                ],
                [],
                ["/one/"]
            ],
            [ #2 many-to-many join table
                [
                    "one" => [
                        EntityMetadata::METADATA_RELATIONSHIP_TYPE => EntityMetadata::RELATIONSHIP_TYPE_MANY_TO_MANY,
                        EntityMetadata::METADATA_RELATIONSHIP_JOIN_TABLE => "test_one",
                        EntityMetadata::METADATA_ENTITY => "Test\\One"
                    ]
                ],
                [
                    "/table.*test_one.*id.*false.*primary_key/",
                    "/primary_key.*test_id/",
                    "/primary_key.*one_id/",
                    "/addColumn.*test_id.*integer/",
                    "/addColumn.*one_id.*integer/"
                ],
                [],
                "test_one"
            ]
        ];

    }

}

