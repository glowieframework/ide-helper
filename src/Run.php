<?php

namespace Glowie\Plugins\IdeHelper;

use Util;
use ReflectionClass;
use Glowie\Core\CLI\Command;
use Glowie\Core\Database\Model;
use Glowie\Core\Resources\DbRow;

/**
 * Glowie plugin for type-hinting models.
 * @category Plugin
 * @package glowieframework/ide-helper
 * @author Glowie
 * @copyright Copyright (c) Glowie
 * @license MIT
 * @link https://glowie.gabrielsilva.dev.br
 */
class Run extends Command
{

    /**
     * The command description (for help message).
     * @var string
     */
    protected $description = 'Type-hints the application models';

    /**
     * The command args signature (for help message).
     * @var string|null
     */
    protected $signature = null;

    /**
     * The command script.
     */
    public function run()
    {
        $this->readModels();
    }

    /**
     * Reads the application models and process them.
     */
    private function readModels()
    {
        // Gets the files from the model path
        $modelsPath = Util::location('models');
        $files = Util::getFiles($modelsPath . '/*.php');

        // Gets the namespaced classname of each model
        $models = array_map(function ($path) use ($modelsPath) {
            $cleanPath = Util::replaceFirst($path, $modelsPath, '');
            $cleanPath = Util::replaceLast($cleanPath, '.php', '');
            return 'Glowie\Models\\' . trim($cleanPath, '/\\');
        }, $files);

        // Processes the models individually
        foreach ($models as $model) $this->processModel(new $model);
    }

    /**
     * Processes a model.
     * @param Model $model Model to be processed.
     */
    private function processModel(Model $model)
    {
        try {
            // Instantiates the model ReflectionClass
            $reflection = new ReflectionClass($model);

            // Gets the database table columns
            $columns = $model->query("SHOW COLUMNS FROM {$model->getTable()}");

            // Creates the comment block
            $doc = "/**\n";
            $doc .= " * {$reflection->getShortName()} model\n";
            $doc .= " *\n";

            // Gets the model casts property
            $castsProperty = $reflection->getProperty('_casts');
            $castsProperty->setAccessible(true);
            $casts = $castsProperty->getValue($model);

            // Maps each column type
            foreach ($columns as $col) {
                $type = $this->getColumnType($col, $casts);
                $doc .= " * @property {$type} \${$col->Field}\n";
            }

            // Gets the model relationships
            $parentReflection = new ReflectionClass($model);
            while (!$parentReflection->hasProperty('_relations') && $parent = $parentReflection->getParentClass()) {
                $parentReflection = $parent;
            }
            $relationsProperty = $parentReflection->getProperty('_relations');
            $relationsProperty->setAccessible(true);
            $relations = $relationsProperty->getValue($model);

            // Maps relationships
            foreach ($relations as $name => $relation) {
                $type = $this->getRelationshipType($relation);
                $doc .= " * @property {$type} \${$name}\n";
            }

            // Closes the comment block
            $doc .= " */";

            // Reads the model file content
            $filename = $reflection->getFileName();
            $content = file_get_contents($filename);

            // Gets the existing comment
            $oldDoc = $reflection->getDocComment();

            // If the comment already exists, replace it
            if ($oldDoc) {
                $newContent = str_replace($oldDoc, $doc, $content);
            } else {
                $className = "class " . $reflection->getShortName();
                $newContent = str_replace($className, $doc . "\n" . $className, $content);
            }

            // Saves the result to the model file
            file_put_contents($filename, $newContent);
            $this->success("Model {$reflection->getShortName()} processed successfully.");
        } catch (\Throwable $th) {
            $name = get_class($model);
            $this->fail("Failed to process model $name. Error: " . $th->getMessage(), 0);
        }
    }

    /**
     * Gets the column data type.
     * @param DbRow $col Column definition from the database.
     * @param array $casts Model casts property.
     * @return string Returns the column data type.
     */
    private function getColumnType(DbRow $col, array $casts)
    {
        // Gets the field name
        $field = $col->Field;

        // Checks if the field has castings
        if (!empty($casts[$field])) {
            $cast = strtolower($casts[$field]);
            $type = $this->mapCasting($cast);
        } else {
            // Maps the column type
            $type = strtolower($col->Type);
            if (Util::stringContains($type, 'int') || $type === 'bit') {
                $type = 'int';
            } else if (in_array($type, ['float', 'double', 'decimal'])) {
                $type = 'float';
            } else {
                $type = 'string';
            }
        }

        // Checks if the field is nullable
        if ($type !== 'mixed' && $col->Null === 'YES') $type .= '|null';
        return $type;
    }

    /**
     * Gets the type of a model relationship.
     * @param array $relation Relation to check.
     * @return string Returns the relation type.
     */
    private function getRelationshipType(array $relation)
    {
        switch ($relation['type']) {
            case 'one':
            case 'belongs':
            case 'one-through':
                return "{$relation['model']}|null";
            case 'many':
            case 'belongs-many':
            case 'many-through':
                $type = Util::classname($relation['model']);
                return "\Glowie\Core\Collection<{$type}>";
            default:
                return 'mixed';
        }
    }

    /**
     * Maps a model casting to its return type.
     * @param string $cast Model casting.
     * @return string Returns the type.
     */
    private function mapCasting(string $cast)
    {
        if (Util::startsWith($cast, 'callback')) return 'mixed';
        if (Util::startsWith($cast, 'date:')) return 'string';
        if (Util::startsWith($cast, 'timestamp:')) return 'string';

        switch ($cast) {
            case 'collection':
                return '\Glowie\Core\Collection';
            case 'set':
                return 'array';
            case 'decimal':
                return 'float';
            case 'json':
            case 'element':
                return '\Glowie\Core\Element';
            case 'encrypted':
            case 'base64':
                return 'string';
            case 'serialized':
                return 'mixed';
            case 'date':
                return '\DateTime';
            case 'timestamp':
                return 'int';
            case 'bool':
            case 'boolean':
                return 'bool';
            default:
                return $cast;
        }
    }
}
