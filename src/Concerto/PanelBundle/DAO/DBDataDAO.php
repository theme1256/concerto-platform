<?php

namespace Concerto\PanelBundle\DAO;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;

class DBDataDAO
{

    public $connection;

    public function __construct(Connection $con)
    {
        $this->connection = $con;
    }

    /**
     * Configured driver name (pdo_mysql, pdo_pgsql, ...); Driver::getName() is deprecated.
     */
    private function getDriverName()
    {
        return $this->connection->getParams()["driver"] ?? "";
    }

    private static function mysqlBufferedQueryAttribute()
    {
        // PHP 8.4 added Pdo\Mysql; PHP 8.5 deprecates the PDO::MYSQL_* constants
        return \defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY') ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY : \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
    }

    private function id($name)
    {
        return DBStructureDAO::quoteName($this->connection, $name);
    }

    private function quoteKeys(array $values)
    {
        $quoted = array();
        foreach ($values as $k => $v) {
            $quoted[$this->id($k)] = $v;
        }
        return $quoted;
    }

    private static function sortDirection($dir)
    {
        $dir = strtoupper(trim((string)$dir));
        if ($dir !== "ASC" && $dir !== "DESC") {
            throw new \InvalidArgumentException("Invalid sort direction");
        }
        return $dir;
    }

    /**
     * @return list<Column>
     */
    private function getColumns($table_name)
    {
        return $this->connection->createSchemaManager()->introspectTableColumnsByUnquotedName($table_name);
    }

    public function getData($table_name, $id = null, $filter = null)
    {
        $stmt = $this->getFilteredDataResult($table_name, $id, $filter);
        return $stmt->fetchAllAssociative();
    }

    public function getFilteredDataResult($table_name, $id = null, $filter = null)
    {
        if ($this->getDriverName() === "pdo_mysql") {
            // stream large tables instead of buffering them in PHP memory
            $this->connection->getNativeConnection()->setAttribute(self::mysqlBufferedQueryAttribute(), false);
        }
        $q = $this->connection->createQueryBuilder()->select("*")->from($this->id($table_name), "d");

        $i = 0;
        if ($filter !== null) {
            foreach ($filter as $f) {

                if (is_a($f["value"], "DateTime"))
                    $f["value"] = $f["value"]->format("Y-m-d");
                if ($i == 0) {
                    $q = $q->where("d." . $this->id($f["name"]) . " " . $f["op"] . " :p$i")->setParameter("p$i", $f["value"]);
                } else {
                    $q = $q->andWhere("d." . $this->id($f["name"]) . " " . $f["op"] . " :p$i")->setParameter("p$i", $f["value"]);
                }
                $i++;
            }
        }

        if ($id) {
            if ($i == 0)
                $q = $q->where("d.id = :id");
            else
                $q = $q->andWhere("d.id = :id");
            $q = $q->setParameter("id", $id);
        }
        return $q->executeQuery();
    }

    /**
     * @param $table_name
     * @param $filters array($k => "name", $v => "value")
     * @return array
     */
    public function fetchMatchingData($table_name, $filters)
    {
        $builder = $this->connection->createQueryBuilder()->select("*")->from($this->id($table_name), "d");

        if (!$filters) {
            return $builder->executeQuery()->fetchAllAssociative();
        }
        $f = json_decode($filters, true);

        $this->addGridFilters($builder, $f["filters"]);
        foreach ($f["sorting"] as $sort) {
            $builder->addOrderBy("d." . $this->id($sort["name"]), self::sortDirection($sort["dir"]));
        }

        return $builder->setFirstResult(($f["paging"]["page"] - 1) * $f["paging"]["pageSize"])->setMaxResults($f["paging"]["pageSize"])->executeQuery()->fetchAllAssociative();
    }

    public function countMatchingData($table_name, $filters)
    {
        $builder = $this->connection->createQueryBuilder()->select('count(d.id)')->from($this->id($table_name), "d");

        if ($filters) {
            $f = json_decode($filters, true);
            $this->addGridFilters($builder, $f["filters"]);
        }

        return (int)$builder->executeQuery()->fetchOne();
    }

    private function addGridFilters($builder, $filters)
    {
        $i = 0;
        foreach ($filters as $k => $v) {
            if (!$v) continue;
            $builder->andWhere("d." . $this->id($k) . " LIKE :f$i")->setParameter("f$i", '%' . $v . '%');
            $i++;
        }
    }

    public function updateRow($table_name, $row_id, $values, $id_field = "id")
    {
        $qb = $this->connection->createQueryBuilder()->update($this->id($table_name));
        $i = -1;
        $cols = $this->getColumns($table_name);
        foreach ($values as $k => $v) {
            $i++;
            if ($k == "id") continue;
            $found = false;
            foreach ($cols as $col) {
                if (DBStructureDAO::columnName($col) == $k) {
                    $found = true;
                    break;
                }
            }
            if (!$found) continue;

            $qb->set($this->id($k), ":k" . $i)->setParameter("k" . $i, $v);
        }

        foreach ($cols as $col) {
            if ($col->getNotnull()) continue;
            $i++;
            $found = false;
            foreach ($values as $k => $v) {
                if ($k === DBStructureDAO::columnName($col)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $qb->set($this->id(DBStructureDAO::columnName($col)), ":k" . $i)->setParameter("k" . $i, null);
            }
        }

        $qb->where($this->id($id_field) . "=:id")->setParameter("id", $row_id)->executeStatement();
        return array();
    }

    public function removeRow($table_name, $id, $id_field = "id")
    {
        $this->connection->delete($this->id($table_name), array($this->id($id_field) => $id));
        return array();
    }

    public function getColumnDefaultValue(Column $col)
    {
        if (!$col->getNotnull()) return null;
        switch (DBStructureDAO::columnType($col)) {
            case "text":
                return "";
        }
        return false;
    }

    public function addBlankRow($table_name)
    {
        switch ($this->getDriverName()) {
            case 'pdo_pgsql':
            {
                $this->connection->executeStatement('INSERT INTO ' . $this->id($table_name) . ' ( id )  VALUES ( DEFAULT )');
                break;
            }
            case 'pdo_sqlsrv':
            {
                $this->connection->executeStatement('INSERT INTO ' . $this->id($table_name) . ' DEFAULT VALUES ');
                break;
            }
            default:
            {
                $vals = array();
                $cols = $this->getColumns($table_name);
                foreach ($cols as $col) {
                    $defaultValue = $this->getColumnDefaultValue($col);
                    if ($defaultValue !== false) {
                        $vals[DBStructureDAO::columnName($col)] = $defaultValue;
                    }
                }

                $this->connection->insert($this->id($table_name), $this->quoteKeys($vals));
                break;
            }
        }

        return array();
    }

    public function insertRow($table_name, $values)
    {
        $this->connection->insert($this->id($table_name), $this->quoteKeys($values));
        return $this->getLastInsertId();
    }

    public function getLastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    public function addInsertBatch($table_name, $data, $batch = null)
    {
        //TODO check sqlite and sql server

        if ($this->getDriverName() == "pdo_sqlsrv") {
            $this->connection->executeStatement("SET IDENTITY_INSERT " . $this->id($table_name) . " ON");
        }

        if ($this->getDriverName() !== "pdo_mysql") {
            $this->insertRow($table_name, $data);
        } else {

            $cols = $this->getColumns($table_name);
            foreach ($cols as $col) {
                $defaultValue = $this->getColumnDefaultValue($col);
                if ($defaultValue !== false && $data[DBStructureDAO::columnName($col)] === "") {
                    $data[DBStructureDAO::columnName($col)] = $defaultValue;
                }
            }

            if ($batch === null) {
                $batch = array(
                    "insert_template" => 'INSERT INTO ' . $this->id($table_name) . ' (' . implode(', ', array_keys($this->quoteKeys($data))) . ')  VALUES ',
                    "values_template" => '(' . implode(', ', array_fill(0, count($data), '?')) . ')',
                    "index" => 0,
                    "sql" => "",
                    "params" => array()
                );
            }
            if ($batch["index"] == 0) {
                $batch["sql"] .= $batch["insert_template"];
            } else {
                $batch["sql"] .= ",";
            }
            $batch["sql"] .= $batch["values_template"];
            $batch["params"] = array_merge($batch["params"], array_values($data));
            $batch["index"]++;
            if ($batch["index"] % 50 == 0) {
                $batch = $this->flushInsertBatch($batch);
            }
        }
        return $batch;
    }

    public function flushInsertBatch($batch)
    {
        if ($this->getDriverName() === "pdo_mysql") {
            if ($batch !== null && $batch["index"] > 0) {
                $this->connection->executeStatement($batch["sql"], $batch["params"]);
                $batch["sql"] = "";
                $batch["params"] = array();
                $batch["index"] = 0;
            }
        }
        return $batch;
    }

    public function truncate($table_name)
    {
        $dbPlatform = $this->connection->getDatabasePlatform();
        if ($this->getDriverName() === "pdo_mysql")
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        $q = $dbPlatform->getTruncateTableSQL($this->id($table_name));
        $this->connection->executeStatement($q);

        if ($this->getDriverName() === "pdo_mysql")
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        return array();
    }

    public function deleteAll($table_name)
    {
        $this->connection->delete($this->id($table_name), array("1" => 1));
        return array();
    }
}
