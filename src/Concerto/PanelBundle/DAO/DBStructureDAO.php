<?php

namespace Concerto\PanelBundle\DAO;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;
use Psr\Log\LoggerInterface;

class DBStructureDAO
{
    /**
     * MySQL table options DBAL 2 applied by default; DBAL 3+ leaves them to the server defaults.
     * Kept explicit so data tables keep their charset and row size limits.
     */
    const MYSQL_TABLE_OPTIONS = ['charset' => 'utf8', 'collation' => 'utf8_unicode_ci', 'engine' => 'InnoDB'];

    // keys are doctrine types, rather than SQL or PHP ones
    private static $typeDefaultValues = [
        'smallint' => 0,
        'bigint' => 0,
        'integer' => 0,
        'boolean' => 0,
        'decimal' => 0,
        'float' => 0,
        'date' => "20000101",
        'datetime' => "20000101",
        'text' => "",
        'string' => ""
    ];
    private static $typeDefaultLengths = [
        'string' => 1024 //must leave at at least 1024, otherwise older imports will truncate data
    ];
    private static $typeDefaultPrecisions = [
        'decimal' => 10
    ];
    private static $typeDefaultScales = [
        'decimal' => 2
    ];

    private $connection;
    private $logger;

    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    private function schemaManager(): AbstractSchemaManager
    {
        return $this->connection->createSchemaManager();
    }

    /**
     * Runs $callback with a schema manager that sees every table. The connection's schema assets
     * filter hides tables the ORM does not map (UnmappedTablesSchemaFilter), data tables included.
     */
    public static function withAllTables(Connection $connection, callable $callback)
    {
        $configuration = $connection->getConfiguration();
        $filter = $configuration->getSchemaAssetsFilter();
        $configuration->setSchemaAssetsFilter(static function () {
            return true;
        });
        try {
            return $callback($connection->createSchemaManager());
        } finally {
            $configuration->setSchemaAssetsFilter($filter);
        }
    }

    public static function columnName(Column $column): string
    {
        return $column->getObjectName()->getIdentifier()->getValue();
    }

    public static function columnType(Column $column): string
    {
        return $column->getTypeName();
    }

    public function tableExists($table_name)
    {
        return self::withAllTables($this->connection, function (AbstractSchemaManager $schemaManager) use ($table_name) {
            return $schemaManager->tableExists($table_name);
        });
    }

    public function columnExists($table_name, $column_name)
    {
        foreach ($this->getColumns($table_name) as $col) {
            if (self::columnName($col) == $column_name) return true;
        }
        return false;
    }

    private function buildColumn($name, $type, array $options): Column
    {
        $editor = Column::editor()->setUnquotedName($name)->setTypeName($type);
        if (array_key_exists("length", $options)) $editor->setLength((int)$options["length"]);
        if (array_key_exists("precision", $options)) $editor->setPrecision((int)$options["precision"]);
        if (array_key_exists("scale", $options)) $editor->setScale((int)$options["scale"]);
        if (array_key_exists("notnull", $options)) $editor->setNotNull($options["notnull"]);
        if (array_key_exists("default", $options)) $editor->setDefaultValue($options["default"]);
        if (array_key_exists("autoincrement", $options)) $editor->setAutoincrement($options["autoincrement"]);
        return $editor->create();
    }

    private function columnOptions($type, $lengthString, $nullable)
    {
        $options = array();
        if (isset(self::$typeDefaultLengths[$type])) $options["length"] = self::$typeDefaultLengths[$type];
        if (isset(self::$typeDefaultPrecisions[$type])) $options["precision"] = self::$typeDefaultPrecisions[$type];
        if (isset(self::$typeDefaultScales[$type])) $options["scale"] = self::$typeDefaultScales[$type];
        $options["notnull"] = !$nullable;
        if ($nullable) $options["default"] = null;
        else if (isset(self::$typeDefaultValues[$type])) $options["default"] = self::$typeDefaultValues[$type];
        $this->applyLengthStringToColumnOptions($type, $lengthString, $options);
        return $options;
    }

    public function createTable($table_name, $structure, $data)
    {
        $columns = array($this->buildColumn("id", "bigint", array('autoincrement' => true)));
        foreach ($structure as $col) {
            if ($col["name"] == "id") {
                continue;
            }
            $columns[] = $this->buildColumn($col["name"], $col["type"], $this->columnOptions($col["type"], $col["length"] ?? "", $col["nullable"]));
        }

        $editor = Table::editor()
            ->setUnquotedName($table_name)
            ->setColumns(...$columns)
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames("id")->create());
        if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $editor->setOptions(self::MYSQL_TABLE_OPTIONS);
        }
        $this->schemaManager()->createTable($editor->create());

        foreach ($data as $row) {
            $esc_row = array();
            foreach ($row as $k => $v) {
                $esc_row[$k] = $v;
            }
            $this->connection->insert($table_name, $esc_row);
        }
    }

    /**
     * @return list<Column>
     */
    public function getColumns($table_name)
    {
        return $this->schemaManager()->introspectTableColumnsByUnquotedName($table_name);
    }

    private function findColumn($table_name, $column_name): ?Column
    {
        foreach ($this->getColumns($table_name) as $col) {
            if (self::columnName($col) == $column_name) return $col;
        }
        return null;
    }

    public function deleteColumn($table_name, $column_name)
    {
        $col = $this->findColumn($table_name, $column_name);
        if ($col === null) return;
        $this->alterTable($table_name, array(), array(), array($col));
    }

    public function saveColumn($table_name, $column_name, $name, $type, $lengthString = "", $nullable = false)
    {
        $newColumn = $this->buildColumn($name, $type, $this->columnOptions($type, $lengthString, $nullable));
        if ($column_name === "0") {
            $this->alterTable($table_name, array($newColumn));
            return;
        }
        $col = $this->findColumn($table_name, $column_name);
        if ($col === null) return;
        // a changed column keeps its data, also when renamed (MySQL: ALTER TABLE ... CHANGE old new)
        $this->alterTable($table_name, array(), array(new ColumnDiff($col, $newColumn)));
    }

    private function alterTable($table_name, array $added, array $changed = array(), array $dropped = array())
    {
        $schemaManager = $this->schemaManager();
        $table = $schemaManager->introspectTableByUnquotedName($table_name);
        $schemaManager->alterTable(new TableDiff($table, $added, $changed, $dropped));
    }

    public function renameTable($table_old_name, $table_new_name)
    {
        $data = $this->connection->createQueryBuilder()->select("*")->from($table_old_name, "d")->executeQuery()->iterateAssociative();

        $cols = array();
        foreach ($this->getColumns($table_old_name) as $col) {
            array_push($cols, array("name" => self::columnName($col), "type" => self::columnType($col), "nullable" => !$col->getNotnull()));
        }

        $this->createTable($table_new_name, $cols, $data);
        $this->deleteTable($table_old_name);
    }

    public function deleteTable($table_name)
    {
        $this->schemaManager()->dropTable($table_name);
    }

    public function getTableNames()
    {
        $names = self::withAllTables($this->connection, function (AbstractSchemaManager $schemaManager) {
            return $schemaManager->introspectTableNames();
        });
        return array_map(function (OptionallyQualifiedName $name) {
            return $name->getUnqualifiedName()->getValue();
        }, $names);
    }

    public function getLengthString(Column $column)
    {
        switch (self::columnType($column)) {
            case "string":
                return $column->getLength();
            case "decimal":
                return $column->getPrecision() . "," . $column->getScale();
        }
        return "";
    }

    public function applyLengthStringToColumnOptions($type, $string, &$options)
    {
        $string = trim((string)$string);
        switch ($type) {
            case "string":
                if (is_numeric($string)) $options["length"] = $string;
                break;
            case "decimal":
                $elems = explode(",", $string);
                if (count($elems) > 0) {
                    $precision = trim($elems[0]);
                    if (is_numeric($precision)) $options["precision"] = $precision;
                }
                if (count($elems) > 1) {
                    $scale = trim($elems[1]);
                    if (is_numeric($scale)) $options["scale"] = $scale;
                }
                break;
        }
    }
}
