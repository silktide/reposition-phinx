<?php

namespace Silktide\Reposition\Phinx;

use Phinx\Util\Util;
use Silktide\Reposition\Metadata\EntityMetadata;
use Silktide\Reposition\Metadata\EntityMetadataProviderInterface;
use Silktide\Reposition\Phinx\Exception\MigrationGenerationException;

/**
 * MigrationGenerator
 */
class MigrationGenerator
{

    protected $metadataProvider;

    protected $outputDirectory;

    protected $templateFile;

    protected $baseMigrationClass;

    protected $tables = [];

    protected $fieldTypeMap = [
        EntityMetadata::FIELD_TYPE_STRING => "string",
        EntityMetadata::FIELD_TYPE_INT => "integer",
        EntityMetadata::FIELD_TYPE_FLOAT => "float",
        EntityMetadata::FIELD_TYPE_BOOL => "boolean",
        EntityMetadata::FIELD_TYPE_ARRAY => "text",
        EntityMetadata::FIELD_TYPE_DATETIME => "datetime",
    ];

    public function __construct(
        EntityMetadataProviderInterface $metadataProvider,
        $outputDirectory,
        $templateFile = "migration.php.template",
        $baseMigrationClass = "Phinx\\Migration\\AbstractMigration"
    ) {
        $this->metadataProvider = $metadataProvider;
        $this->setOutputDirectory($outputDirectory);
        $this->setTemplateFile($templateFile);
        $this->setBaseMigrationClass($baseMigrationClass);
    }

    protected function setOutputDirectory($directory)
    {
        if (!is_dir($directory) && !is_writeable($directory)) {
            throw new MigrationGenerationException("The output directory path '$directory' is not a directory or is not writable");
        }
        $this->outputDirectory = $directory;
    }

    protected function setTemplateFile($templateFile)
    {
        if (!is_file($templateFile)) {
            $absoluteTemplateFile = __DIR__ . "/" . $templateFile;
            if (!is_file($absoluteTemplateFile)) {
                throw new MigrationGenerationException("No template file found for the paths '$templateFile' and '$absoluteTemplateFile'");
            }
            $templateFile = $absoluteTemplateFile;
        }

        if (!is_readable($templateFile)) {
            throw new MigrationGenerationException("The template file '$templateFile' is not readable");
        }
        $this->templateFile = $templateFile;
    }

    protected function setBaseMigrationClass($baseMigrationClass)
    {
        if (!class_exists($baseMigrationClass)) {
            throw new MigrationGenerationException("The base migration class '$baseMigrationClass' does not exist");
        }
        $this->baseMigrationClass = $baseMigrationClass;
    }

    public function generateMigrationFor($entityClass)
    {
        $this->tables = [];

        $entityMetadata = $this->metadataProvider->getEntityMetadata($entityClass);

        $collection = $entityMetadata->getCollection();
        $primaryKey = $entityMetadata->getPrimaryKey();
        $fields = $entityMetadata->getFields();
        $relationships = $entityMetadata->getRelationships();

        // process relationships for extra fields to process
        $relationshipFields = $this->getRelationshipFields($relationships, $collection, $primaryKey);

        if (!empty($relationshipFields)) {
            $fields = array_merge($relationshipFields, $fields);
        }

        // generate the code for this table
        array_unshift($this->tables, $this->getTableCode($collection, $primaryKey, $fields));

        // create the migration from the template
        return $this->createMigrationFile($entityClass);

    }

    protected function getRelationshipFields(array $relationships, $collection, $primaryKey)
    {
        $relationshipFields = [];
        foreach ($relationships as $relationship) {
            if (empty($relationship[EntityMetadata::METADATA_RELATIONSHIP_TYPE])) {
                throw new MigrationGenerationException("The relationship metadata was missing the " . EntityMetadata::METADATA_RELATIONSHIP_TYPE . " field");
            }

            $type = $relationship[EntityMetadata::METADATA_RELATIONSHIP_TYPE];

            if ($type != EntityMetadata::RELATIONSHIP_TYPE_MANY_TO_MANY) {
                if (!empty($relationship[EntityMetadata::METADATA_RELATIONSHIP_OUR_FIELD])) {
                    $relationshipFields[$relationship[EntityMetadata::METADATA_RELATIONSHIP_OUR_FIELD]] = [
                        EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_INT
                    ];
                }
            } else {
                // many to many involves an intermediary table which we must create here

                if (empty($relationship[EntityMetadata::METADATA_ENTITY])) {
                    throw new MigrationGenerationException("The relationship metadata was missing the " . EntityMetadata::METADATA_ENTITY . " field");
                }

                $ourField = !empty($relationship[EntityMetadata::METADATA_RELATIONSHIP_OUR_FIELD])
                    ? $relationship[EntityMetadata::METADATA_RELATIONSHIP_OUR_FIELD]
                    : $primaryKey;

                $theirMetadata = $this->metadataProvider->getEntityMetadata($relationship[EntityMetadata::METADATA_ENTITY]);
                $theirCollection = $theirMetadata->getCollection();
                $theirField = !empty($relationship[EntityMetadata::METADATA_RELATIONSHIP_THEIR_FIELD])
                    ? $theirField = $relationship[EntityMetadata::METADATA_RELATIONSHIP_THEIR_FIELD]
                    : $theirField = $theirMetadata->getPrimaryKey();

                $joinTable = $relationship[EntityMetadata::METADATA_RELATIONSHIP_JOIN_TABLE];

                $fields = [
                    "{$collection}_{$ourField}" => [EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_INT],
                    "{$theirCollection}_{$theirField}" => [EntityMetadata::METADATA_FIELD_TYPE => EntityMetadata::FIELD_TYPE_INT],
                ];

                $primaryKeys = array_keys($fields);

                $this->tables[] = $this->getTableCode($joinTable, $primaryKeys, $fields);
            }

        }

        return $relationshipFields;
    }

    protected function getTableCode($collection, $primaryKey, array $fields)
    {
        if (!is_array($primaryKey)) {
            $primaryKey = [$primaryKey];
        }

        // process primary key field for extra table options
        foreach ($primaryKey as $key) {
            if (empty($fields[$key])) {
                throw new MigrationGenerationException("The primary key '$key' for this entity could not be found in the list of fields");
            }
            if (empty($fields[$key][EntityMetadata::METADATA_FIELD_TYPE])) {
                throw new MigrationGenerationException("The field metadata for the primary key '$key' is malformed. No field type found");
            }
        }

        $firstPk = $primaryKey[0];

        $pkField = $fields[$firstPk];

        if (count($primaryKey) > 1 || ($pkField[EntityMetadata::METADATA_FIELD_TYPE] != EntityMetadata::FIELD_TYPE_INT)) {
            // non integer primary key (no auto increment, compound key
            $tableOptions = ", ['id' => false, 'primary_key' => ['" . implode("', '", $primaryKey) . "']]";
        } else {

            if (isset($pkField[EntityMetadata::METADATA_FIELD_AUTO_INCREMENTING]) && $pkField[EntityMetadata::METADATA_FIELD_AUTO_INCREMENTING] == false) {
                $tableOptions = ", ['id' => false]";
            } else {
                $tableOptions = ", ['id' => '$firstPk']";
                // setting the 'id' on a table generates the field automatically, so we can remove it from the field list
                unset ($fields[$firstPk]);
            }
        }

        // process fields
        $fieldCode = [];
        foreach ($fields as $name => $meta) {
            if (empty($meta[EntityMetadata::METADATA_FIELD_TYPE])) {
                // malformed field metadata
                continue;
            }
            $fieldCode[] = $this->getCodeForField($name, $meta[EntityMetadata::METADATA_FIELD_TYPE]);
        }

        return "if (!\$this->hasTable('$collection')) {
            \$table = \$this->table('$collection'$tableOptions);
            " . implode("\n            ", $fieldCode) . "
            \$table->create();
        }";
    }

    protected function createMigrationFile($entityClass)
    {
        $baseClassName = $this->getClassNameFromFQCN($this->baseMigrationClass);
        $className = $this->getClassNameFromFQCN($entityClass) . "Migration";

        // validate class name
        if (!Util::isValidPhinxClassName($className) || !Util::isUniqueMigrationClassName($className, $this->outputDirectory)) {
            throw new MigrationGenerationException("The class name '$className' is invalid or already exists");
        }

        $fileName = Util::mapClassNameToFileName($className);

        // create tableDefinitions string
        $tableDefinition = implode("\n\n        ", $this->tables);

        // inject data into the template
        $template = file_get_contents($this->templateFile);

        $template = str_replace("{{baseFQCN}}", $this->baseMigrationClass, $template);
        $template = str_replace("{{baseClass}}", $baseClassName, $template);
        $template = str_replace("{{className}}", $className, $template);
        $template = str_replace("{{tableDefinition}}", $tableDefinition, $template);

        $filePath = $this->outputDirectory . "/" . $fileName;

        // write the file
        file_put_contents($filePath, $template);

        return $filePath;
    }

    protected function getCodeForField($name, $type)
    {
        $type = $this->mapToPhinxFieldType($type);

        $options = "";
        switch ($type) {
            case "string":
                $options = ", ['limit' => 255]";
                break;
            case "integer":
                $options = ", ['signed' => false]";
                break;
        }

        return "\$table->addColumn('$name', '$type'$options);";
    }

    protected function mapToPhinxFieldType($type)
    {
        if (empty($this->fieldTypeMap[$type])) {
            throw new MigrationGenerationException("An unrecognised field type was detected: '$type'");
        }
        return $this->fieldTypeMap[$type];
    }

    protected function getClassNameFromFQCN($fqcn)
    {
        return substr($fqcn, strrpos($fqcn, "\\") + 1);
    }

}