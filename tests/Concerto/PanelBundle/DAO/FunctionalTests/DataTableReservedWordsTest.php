<?php

namespace Tests\Concerto\PanelBundle\DAO\FunctionalTests;

use Concerto\PanelBundle\DAO\DBDataDAO;
use Concerto\PanelBundle\DAO\DBStructureDAO;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * User data tables may use names that are reserved words in MySQL 8 but not in 5.7
 * ("groups", "rank", "row", "system", "lead", ...). Tables created on 5.7 must keep working
 * after the database is upgraded, so every data table operation has to quote identifiers.
 */
class DataTableReservedWordsTest extends KernelTestCase
{
    const TABLE = 'groups';
    const RENAMED_TABLE = 'window';

    private $connection;
    private $structureDao;
    private $dataDao;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = static::getContainer()->get('doctrine')->getConnection();
        $this->structureDao = new DBStructureDAO($this->connection, new NullLogger());
        $this->dataDao = new DBDataDAO($this->connection);
        $this->dropTables();

        $this->structureDao->createTable(self::TABLE, array(
            array('name' => 'rank', 'type' => 'integer', 'nullable' => false),
            array('name' => 'row', 'type' => 'string', 'nullable' => true),
            array('name' => 'system', 'type' => 'text', 'nullable' => false),
        ), array(
            array('rank' => 2, 'row' => 'b', 'system' => 'two'),
            array('rank' => 1, 'row' => 'a', 'system' => 'one'),
        ));
    }

    protected function tearDown(): void
    {
        $this->dropTables();
        parent::tearDown();
    }

    private function dropTables()
    {
        $q = function ($name) {
            return $this->connection->quoteSingleIdentifier($name);
        };
        $this->connection->executeStatement('DROP TABLE IF EXISTS ' . $q(self::TABLE) . ', ' . $q(self::RENAMED_TABLE));
    }

    private function rows($table = self::TABLE)
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM ' . $this->connection->quoteSingleIdentifier($table) . ' ORDER BY id');
    }

    private function grid(array $filters, array $sorting)
    {
        return json_encode(array('filters' => $filters, 'sorting' => $sorting, 'paging' => array('page' => 1, 'pageSize' => 10)));
    }

    public function testStructure()
    {
        $this->assertTrue($this->structureDao->tableExists(self::TABLE));
        $this->assertTrue($this->structureDao->columnExists(self::TABLE, 'rank'));
        $this->assertEquals(array('id', 'rank', 'row', 'system'), array_map(array(DBStructureDAO::class, 'columnName'), $this->structureDao->getColumns(self::TABLE)));
        $this->assertEquals(array('b', 'a'), array_column($this->rows(), 'row'));
    }

    public function testRowOperations()
    {
        $id = $this->dataDao->insertRow(self::TABLE, array('rank' => 3, 'row' => 'c', 'system' => 'three'));
        $this->assertEquals(3, $id);

        $this->dataDao->updateRow(self::TABLE, 1, array('rank' => 20, 'system' => 'twenty'));
        $row = $this->rows()[0];
        $this->assertEquals(20, $row['rank']);
        $this->assertSame('twenty', $row['system']);
        $this->assertNull($row['row']);

        $this->assertEquals(array(3), array_column($this->dataDao->getData(self::TABLE, null, array(array('name' => 'rank', 'op' => '>=', 'value' => 3), array('name' => 'rank', 'op' => '<', 'value' => 20))), 'id'));
        $this->assertSame('three', $this->dataDao->getData(self::TABLE, 3)[0]['system']);

        $this->assertEquals(array(2, 3, 1), array_column($this->dataDao->fetchMatchingData(self::TABLE, $this->grid(array(), array(array('name' => 'rank', 'dir' => 'asc')))), 'id'));
        $this->assertEquals(array(3), array_column($this->dataDao->fetchMatchingData(self::TABLE, $this->grid(array('system' => 'hre', 'row' => 'c'), array())), 'id'));
        $this->assertSame(1, $this->dataDao->countMatchingData(self::TABLE, $this->grid(array('system' => 'hre'), array())));
        $this->assertSame(3, $this->dataDao->countMatchingData(self::TABLE, null));

        $this->dataDao->addBlankRow(self::TABLE);
        $this->assertEquals(0, $this->rows()[3]['rank']);

        $this->dataDao->removeRow(self::TABLE, 2);
        $this->assertEquals(array(1, 3, 4), array_column($this->rows(), 'id'));

        $batch = null;
        foreach (array(5, 6) as $rank) {
            $batch = $this->dataDao->addInsertBatch(self::TABLE, array('rank' => $rank, 'row' => 'r' . $rank, 'system' => ''), $batch);
        }
        $this->dataDao->flushInsertBatch($batch);
        $this->assertEquals(array(20, 3, 0, 5, 6), array_column($this->rows(), 'rank'));

        $this->dataDao->deleteAll(self::TABLE);
        $this->assertCount(0, $this->rows());
        $this->dataDao->addBlankRow(self::TABLE);
        $this->dataDao->truncate(self::TABLE);
        $this->assertCount(0, $this->rows());
    }

    /**
     * Grid filter keys and sort names come from the request: they are column names, never SQL.
     */
    public function testGridFilterAndSortKeysAreIdentifiersOnly()
    {
        $injections = array(
            $this->grid(array('rank = 1 OR rank' => 'x'), array()),
            $this->grid(array('rank` = 1 OR `rank' => 'x'), array()),
            $this->grid(array(), array(array('name' => 'rank, (SELECT 1)', 'dir' => 'asc'))),
        );
        foreach ($injections as $grid) {
            try {
                $rows = $this->dataDao->fetchMatchingData(self::TABLE, $grid);
                $this->fail('no error for ' . $grid . ', got ' . json_encode($rows));
            } catch (\Doctrine\DBAL\Exception $e) {
                $this->assertStringContainsString('Unknown column', $e->getMessage(), $grid);
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->dataDao->fetchMatchingData(self::TABLE, $this->grid(array(), array(array('name' => 'rank', 'dir' => 'asc; DELETE FROM x'))));
    }

    public function testColumnAndTableChanges()
    {
        $this->structureDao->saveColumn(self::TABLE, '0', 'lead', 'string', '', true);
        $this->structureDao->saveColumn(self::TABLE, 'rank', 'over', 'string', '', false);
        $this->structureDao->deleteColumn(self::TABLE, 'row');
        $this->assertEquals(array('id', 'over', 'system', 'lead'), array_map(array(DBStructureDAO::class, 'columnName'), $this->structureDao->getColumns(self::TABLE)));
        $this->assertEquals(array('2', '1'), array_column($this->rows(), 'over'));

        $this->structureDao->renameTable(self::TABLE, self::RENAMED_TABLE);
        $this->assertFalse($this->structureDao->tableExists(self::TABLE));
        $this->assertEquals(array('two', 'one'), array_column($this->rows(self::RENAMED_TABLE), 'system'));
        $this->assertContains(self::RENAMED_TABLE, $this->structureDao->getTableNames());

        $this->structureDao->deleteTable(self::RENAMED_TABLE);
        $this->assertFalse($this->structureDao->tableExists(self::RENAMED_TABLE));
    }
}
