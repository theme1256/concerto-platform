<?php

namespace Tests\Concerto\PanelBundle\DAO\FunctionalTests;

use Concerto\PanelBundle\DAO\DBDataDAO;
use Concerto\PanelBundle\DAO\DBStructureDAO;
use Concerto\PanelBundle\Service\DBStructureService;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Contract for the DDL and data access behind user data tables (DBStructureDAO / DBDataDAO),
 * independent of the Doctrine DBAL version underneath.
 */
class DataTableSchemaContractTest extends KernelTestCase
{
    const TABLE = 'contract_dao_table';
    const RENAMED_TABLE = 'contract_dao_renamed';

    private $connection;
    private $structureDao;
    private $structureService;
    private $dataDao;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = static::getContainer()->get('doctrine')->getConnection();
        $this->structureDao = new DBStructureDAO($this->connection, new NullLogger());
        $this->structureService = new DBStructureService($this->structureDao, new NullLogger());
        $this->dataDao = new DBDataDAO($this->connection);
        $this->dropTables();

        $this->structureDao->createTable(self::TABLE, array(
            array('name' => 's', 'type' => 'string', 'nullable' => false, 'length' => '64'),
            array('name' => 'n', 'type' => 'decimal', 'nullable' => true, 'length' => '8,3'),
            array('name' => 'i', 'type' => 'integer', 'nullable' => true),
            array('name' => 't', 'type' => 'text', 'nullable' => false),
            array('name' => 'b', 'type' => 'boolean', 'nullable' => false),
            array('name' => 'd', 'type' => 'date', 'nullable' => true),
            array('name' => 'dt', 'type' => 'datetime', 'nullable' => true),
            array('name' => 'f', 'type' => 'float', 'nullable' => true),
            array('name' => 'bi', 'type' => 'bigint', 'nullable' => false),
        ), array(
            array('s' => 'abc', 'n' => '12.345', 'i' => 5, 't' => 'long text', 'b' => 1, 'd' => '2024-02-03', 'dt' => '2024-02-03 04:05:06', 'f' => 1.5, 'bi' => '9007199254740993'),
            array('s' => 'x', 'n' => null, 'i' => null, 't' => '', 'b' => 0, 'd' => null, 'dt' => null, 'f' => null, 'bi' => 0),
        ));
    }

    protected function tearDown(): void
    {
        $this->dropTables();
        parent::tearDown();
    }

    private function dropTables()
    {
        foreach (array(self::TABLE, self::RENAMED_TABLE) as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS ' . $table);
        }
    }

    private function isMySql()
    {
        return $this->connection->getParams()['driver'] === 'pdo_mysql';
    }

    private function rows($table)
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM ' . $table . ' ORDER BY id');
    }

    private function column($table, $name)
    {
        foreach ($this->structureService->getColumns($table) as $col) {
            if ($col['name'] === $name) return $col;
        }
        return null;
    }

    public function testCreatedTableStructure()
    {
        $this->assertEquals(array(
            array('name' => 'id', 'type' => 'bigint', 'nullable' => false, 'length' => ''),
            array('name' => 's', 'type' => 'string', 'nullable' => false, 'length' => '64'),
            array('name' => 'n', 'type' => 'decimal', 'nullable' => true, 'length' => '8,3'),
            array('name' => 'i', 'type' => 'integer', 'nullable' => true, 'length' => ''),
            array('name' => 't', 'type' => 'text', 'nullable' => false, 'length' => ''),
            array('name' => 'b', 'type' => 'boolean', 'nullable' => false, 'length' => ''),
            array('name' => 'd', 'type' => 'date', 'nullable' => true, 'length' => ''),
            array('name' => 'dt', 'type' => 'datetime', 'nullable' => true, 'length' => ''),
            array('name' => 'f', 'type' => 'float', 'nullable' => true, 'length' => ''),
            array('name' => 'bi', 'type' => 'bigint', 'nullable' => false, 'length' => ''),
        ), $this->structureService->getColumns(self::TABLE));

        $this->assertEquals(array('name' => 'n', 'type' => 'decimal', 'nullable' => true, 'length' => '8,3'), $this->structureService->getColumn(self::TABLE, 'n'));
        $this->assertSame(array(), $this->structureService->getColumn(self::TABLE, 'missing'));

        $rows = $this->rows(self::TABLE);
        $this->assertCount(2, $rows);
        $this->assertEquals(array('id' => 1, 's' => 'abc', 'n' => '12.345', 'i' => 5, 't' => 'long text', 'b' => 1, 'd' => '2024-02-03', 'dt' => '2024-02-03 04:05:06', 'f' => 1.5, 'bi' => '9007199254740993'), $rows[0]);
        $this->assertNull($rows[1]['n']);
    }

    public function testTableLookups()
    {
        $this->assertTrue($this->structureDao->tableExists(self::TABLE));
        $this->assertFalse($this->structureDao->tableExists(self::RENAMED_TABLE));
        $this->assertTrue($this->structureDao->columnExists(self::TABLE, 'dt'));
        $this->assertFalse($this->structureDao->columnExists(self::TABLE, 'nope'));
        $this->assertContains(self::TABLE, $this->structureDao->getTableNames());
        $this->assertNotContains(self::RENAMED_TABLE, $this->structureDao->getTableNames());
    }

    public function testMySqlTableOptions()
    {
        if (!$this->isMySql()) $this->markTestSkipped('MySQL only');
        $row = $this->connection->fetchAssociative(
            'SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            array(self::TABLE)
        );
        $this->assertSame('InnoDB', $row['ENGINE']);
        $this->assertMatchesRegularExpression('/^utf8(mb3)?_unicode_ci$/', $row['TABLE_COLLATION']);
    }

    public function testRenameColumnKeepsData()
    {
        $this->assertSame(array(), $this->structureService->saveColumn(self::TABLE, 's', 's2', 'string', '128', false));

        $this->assertNull($this->column(self::TABLE, 's'));
        $this->assertEquals(array('name' => 's2', 'type' => 'string', 'nullable' => false, 'length' => '128'), $this->column(self::TABLE, 's2'));
        $this->assertEquals(array('abc', 'x'), array_column($this->rows(self::TABLE), 's2'));
    }

    public function testChangeColumnTypeKeepsData()
    {
        $this->structureService->saveColumn(self::TABLE, 'i', 'i', 'text', '', true);

        $this->assertEquals(array('name' => 'i', 'type' => 'text', 'nullable' => true, 'length' => ''), $this->column(self::TABLE, 'i'));
        $this->assertSame(array('5', null), array_column($this->rows(self::TABLE), 'i'));
    }

    public function testChangeDecimalPrecisionAndNullability()
    {
        $this->structureService->saveColumn(self::TABLE, 'n', 'n', 'decimal', '10,4', true);
        $this->assertEquals(array('name' => 'n', 'type' => 'decimal', 'nullable' => true, 'length' => '10,4'), $this->column(self::TABLE, 'n'));
        $this->assertEquals('12.3450', $this->rows(self::TABLE)[0]['n']);

        $this->structureService->saveColumn(self::TABLE, 'bi', 'bi', 'bigint', '', true);
        $this->assertTrue($this->column(self::TABLE, 'bi')['nullable']);

        $this->connection->executeStatement('UPDATE ' . self::TABLE . ' SET f = 2.5 WHERE f IS NULL');
        $this->structureService->saveColumn(self::TABLE, 'f', 'f', 'float', '', false);
        $this->assertFalse($this->column(self::TABLE, 'f')['nullable']);
        $this->assertEquals(array(1.5, 2.5), array_column($this->rows(self::TABLE), 'f'));
    }

    public function testAddColumnUsesTypeDefault()
    {
        $this->structureService->saveColumn(self::TABLE, '0', 'added', 'integer', '', false);
        $this->structureService->saveColumn(self::TABLE, '0', 'added_str', 'string', '', true);

        $this->assertEquals(array('name' => 'added', 'type' => 'integer', 'nullable' => false, 'length' => ''), $this->column(self::TABLE, 'added'));
        $this->assertEquals(array('name' => 'added_str', 'type' => 'string', 'nullable' => true, 'length' => '1024'), $this->column(self::TABLE, 'added_str'));
        $rows = $this->rows(self::TABLE);
        $this->assertEquals(array(0, 0), array_column($rows, 'added'));
        $this->assertSame(array(null, null), array_column($rows, 'added_str'));
    }

    public function testSaveColumnValidation()
    {
        $this->assertSame(array('validate.table.column.name.unique'), $this->structureService->saveColumn(self::TABLE, 's', 't', 'string'));
        $this->assertSame(array('validate.table.column.name.incorrect'), $this->structureService->saveColumn(self::TABLE, 's', '1bad', 'string'));
    }

    public function testDeleteColumn()
    {
        $this->structureService->removeColumn(self::TABLE, 't');
        $this->assertNull($this->column(self::TABLE, 't'));
        $this->assertFalse($this->structureDao->columnExists(self::TABLE, 't'));
        $this->assertArrayNotHasKey('t', $this->rows(self::TABLE)[0]);
    }

    public function testRenameTableKeepsDataAndColumnTypes()
    {
        $this->assertSame(array(), $this->structureService->renameTable(self::TABLE, self::RENAMED_TABLE));

        $this->assertFalse($this->structureDao->tableExists(self::TABLE));
        $this->assertTrue($this->structureDao->tableExists(self::RENAMED_TABLE));
        // renaming recreates the table from name/type/nullable only, so lengths fall back to the type defaults
        $this->assertEquals(array(
            array('name' => 'id', 'type' => 'bigint', 'nullable' => false, 'length' => ''),
            array('name' => 's', 'type' => 'string', 'nullable' => false, 'length' => '1024'),
            array('name' => 'n', 'type' => 'decimal', 'nullable' => true, 'length' => '10,2'),
            array('name' => 'i', 'type' => 'integer', 'nullable' => true, 'length' => ''),
            array('name' => 't', 'type' => 'text', 'nullable' => false, 'length' => ''),
            array('name' => 'b', 'type' => 'boolean', 'nullable' => false, 'length' => ''),
            array('name' => 'd', 'type' => 'date', 'nullable' => true, 'length' => ''),
            array('name' => 'dt', 'type' => 'datetime', 'nullable' => true, 'length' => ''),
            array('name' => 'f', 'type' => 'float', 'nullable' => true, 'length' => ''),
            array('name' => 'bi', 'type' => 'bigint', 'nullable' => false, 'length' => ''),
        ), $this->structureService->getColumns(self::RENAMED_TABLE));

        $rows = $this->rows(self::RENAMED_TABLE);
        $this->assertEquals(array(1, 2), array_column($rows, 'id'));
        $this->assertEquals(array('abc', 'x'), array_column($rows, 's'));
        $this->assertEquals('2024-02-03 04:05:06', $rows[0]['dt']);

        $this->assertSame(array('validate.table.name.incorrect'), $this->structureService->renameTable(self::RENAMED_TABLE, 'bad name'));
    }

    public function testDeleteTable()
    {
        $this->structureService->removeTable(self::TABLE);
        $this->assertFalse($this->structureDao->tableExists(self::TABLE));
    }

    public function testRowOperations()
    {
        $this->dataDao->addBlankRow(self::TABLE);
        $rows = $this->rows(self::TABLE);
        $this->assertCount(3, $rows);
        $this->assertSame('', $rows[2]['t']);
        $this->assertEquals('', $rows[2]['s']);
        $this->assertNull($rows[2]['i']);

        // nullable columns missing from the update are reset to null
        $this->dataDao->updateRow(self::TABLE, 1, array('id' => 99, 's' => 'changed', 'b' => 0, 'unknown' => 'ignored'));
        $row = $this->rows(self::TABLE)[0];
        $this->assertEquals(1, $row['id']);
        $this->assertSame('changed', $row['s']);
        $this->assertEquals(0, $row['b']);
        $this->assertSame('long text', $row['t']);
        $this->assertNull($row['n']);
        $this->assertNull($row['dt']);

        $id = $this->dataDao->insertRow(self::TABLE, array('s' => 'inserted', 't' => 'y', 'b' => 1, 'bi' => 7));
        $this->assertEquals(4, $id);

        $this->assertEquals(array(array('id' => 4, 's' => 'inserted')), array_map(function ($r) {
            return array('id' => $r['id'], 's' => $r['s']);
        }, $this->dataDao->getData(self::TABLE, null, array(array('name' => 'bi', 'op' => '=', 'value' => 7)))));
        $this->assertSame('changed', $this->dataDao->getData(self::TABLE, 1)[0]['s']);
        $this->assertCount(0, $this->dataDao->getData(self::TABLE, 4, array(array('name' => 's', 'op' => '=', 'value' => 'other'))));

        $result = $this->dataDao->getFilteredDataResult(self::TABLE);
        $streamed = array();
        while ($r = $result->fetchAssociative()) {
            $streamed[] = $r['id'];
        }
        $this->assertEquals(array(1, 2, 3, 4), $streamed);

        $this->dataDao->removeRow(self::TABLE, 2);
        $this->assertEquals(array(1, 3, 4), array_column($this->rows(self::TABLE), 'id'));

        $this->dataDao->deleteAll(self::TABLE);
        $this->assertCount(0, $this->rows(self::TABLE));
    }

    public function testInsertBatchAndTruncate()
    {
        $batch = null;
        for ($i = 1; $i <= 3; $i++) {
            $batch = $this->dataDao->addInsertBatch(self::TABLE, array('s' => "b$i", 'n' => null, 'i' => $i, 't' => '', 'b' => 0, 'd' => null, 'dt' => null, 'f' => null, 'bi' => $i), $batch);
        }
        $this->dataDao->flushInsertBatch($batch);
        $this->assertEquals(array('abc', 'x', 'b1', 'b2', 'b3'), array_column($this->rows(self::TABLE), 's'));

        $this->dataDao->truncate(self::TABLE);
        $this->assertCount(0, $this->rows(self::TABLE));
        $this->dataDao->addBlankRow(self::TABLE);
        $this->assertEquals(1, $this->rows(self::TABLE)[0]['id']);
    }
}
