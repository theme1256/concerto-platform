<?php

namespace Tests\Concerto\PanelBundle\Command\FunctionalTests;

use Concerto\PanelBundle\DAO\DBStructureDAO;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * concerto:setup runs doctrine:schema:update --force on every start. It must only manage the
 * ORM tables: user data tables (and tables created from R code) are not mapped and must survive.
 */
class SchemaUpdateKeepsDataTablesTest extends KernelTestCase
{
    const DATA_TABLE = 'contract_user_table';
    const RAW_TABLE = 'contract_raw_table';

    private $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = static::getContainer()->get('doctrine')->getConnection();
        $this->dropTables();
    }

    protected function tearDown(): void
    {
        $this->dropTables();
        parent::tearDown();
    }

    private function dropTables()
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS ' . self::DATA_TABLE . ', ' . self::RAW_TABLE);
    }

    private function runCommand(array $input)
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $output = new BufferedOutput();
        $application->run(new ArrayInput($input), $output);
        return $output->fetch();
    }

    /**
     * Isolated: the console run instantiates the native session handler, whose ini_set() calls
     * warn once PHPUnit has written output in the same process.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSchemaUpdateKeepsUnmappedTablesAndManagesMappedOnes()
    {
        $dao = new DBStructureDAO($this->connection, new NullLogger());
        $dao->createTable(self::DATA_TABLE, array(array('name' => 'v', 'type' => 'text', 'nullable' => false)), array(array('v' => 'keep me')));
        $this->connection->executeStatement('CREATE TABLE ' . self::RAW_TABLE . ' (id INT PRIMARY KEY)');
        $this->connection->executeStatement('INSERT INTO ' . self::RAW_TABLE . ' VALUES (7)');
        // a missing ORM table must still be (re)created
        $this->connection->executeStatement('DROP TABLE IF EXISTS SamlToken');

        $sql = $this->runCommand(array('command' => 'doctrine:schema:update', '--dump-sql' => true));
        $this->assertStringNotContainsStringIgnoringCase('DROP TABLE', $sql);
        $this->assertStringContainsString('CREATE TABLE SamlToken', $sql);

        $this->runCommand(array('command' => 'doctrine:schema:update', '--force' => true));

        $this->assertEquals(array('keep me'), $this->connection->fetchFirstColumn('SELECT v FROM ' . self::DATA_TABLE));
        $this->assertEquals(array(7), $this->connection->fetchFirstColumn('SELECT id FROM ' . self::RAW_TABLE));
        $this->assertEquals(0, $this->connection->fetchOne('SELECT COUNT(*) FROM SamlToken'));

        // the data table code still sees every table
        $this->assertTrue($dao->tableExists(self::DATA_TABLE));
        $this->assertTrue($dao->tableExists(self::RAW_TABLE));
        $this->assertContains(self::DATA_TABLE, $dao->getTableNames());
    }
}
