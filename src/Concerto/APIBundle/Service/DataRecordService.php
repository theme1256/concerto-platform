<?php

namespace Concerto\APIBundle\Service;

use Concerto\PanelBundle\Entity\DataTable;
use Concerto\PanelBundle\Service\DataTableService;
use Concerto\PanelBundle\DAO\DBDataDAO;
use Concerto\PanelBundle\DAO\DBStructureDAO;
use Symfony\Component\HttpFoundation\Response;

class DataRecordService
{

    private $dataTableService;
    private $dbDataDAO;
    private $dbStructureDAO;

    protected static $excluded_filters = array(
        "access_token",
        "format"
    );

    public function __construct(DataTableService $dataTableService, DBDataDAO $dbDataDAO, DBStructureDAO $dbStructureDAO)
    {
        $this->dataTableService = $dataTableService;
        $this->dbDataDAO = $dbDataDAO;
        $this->dbStructureDAO = $dbStructureDAO;
    }

    public function getData($table_id, $id, $format = null)
    {
        $table = $this->dataTableService->get($table_id, false, false);
        if ($table === null)
            return array("response" => Response::HTTP_NOT_FOUND, "result" => null);

        $result = $this->dbDataDAO->getData($table->getName(), $id);
        if (!count($result))
            return array("response" => Response::HTTP_NOT_FOUND, "result" => null);

        if ($format !== null) {
            return array("response" => Response::HTTP_OK, "result" => $this->serialize($result, $format));
        }
        return array("response" => Response::HTTP_OK, "result" => $result);
    }

    public function getDataCollection($table_id, $filter, $format = null)
    {
        $table = $this->dataTableService->get($table_id, false, false);
        if ($table === null) {
            return array("response" => Response::HTTP_NOT_FOUND, "result" => null);
        }

        foreach (static::$excluded_filters as $key) {
            unset($filter[$key]);
        }

        if (!$this->formatDataFilter($table, $filter)) {
            return array("response" => Response::HTTP_BAD_REQUEST, "result" => null);
        }

        $result = $this->dbDataDAO->getData($table->getName(), null, $filter);
        if ($format !== null) {
            return array("response" => Response::HTTP_OK, "result" => $this->serialize($result, $format));
        }
        return array("response" => Response::HTTP_OK, "result" => $result);
    }

    public function updateData($table_id, $id, $newSerializedData, $format = "json")
    {
        $table = $this->dataTableService->get($table_id, false, false);
        if ($table === null)
            return array("response" => Response::HTTP_NOT_FOUND, "result" => null);

        $newData = $this->deserialize($newSerializedData, "json");

        $error = $this->validateFields($table, $newData);
        if ($error !== null)
            return array("response" => Response::HTTP_BAD_REQUEST, "result" => $error);

        $this->dbDataDAO->updateRow($table->getName(), $id, $newData);
        $result = $this->dbDataDAO->getData($table->getName(), $id);
        if ($format !== null) {
            return array("response" => Response::HTTP_OK, "result" => $this->serialize($result, $format));
        }
        return array("response" => Response::HTTP_OK, "result" => $result);
    }

    public function insertData($table_id, $newSerializedData, $format = "json")
    {
        $table = $this->dataTableService->get($table_id, false, false);
        if ($table === null)
            return array("response" => Response::HTTP_NOT_FOUND, "result" => null);

        $newData = $this->deserialize($newSerializedData, "json");

        $error = $this->validateFields($table, $newData);
        if ($error !== null)
            return array("response" => Response::HTTP_BAD_REQUEST, "result" => $error);

        $lid = $this->dbDataDAO->insertRow($table->getName(), $newData);
        $result = $this->dbDataDAO->getData($table->getName(), $lid);
        if ($format !== null) {
            return array("response" => Response::HTTP_OK, "result" => $this->serialize($result, $format));
        }
        return array("response" => Response::HTTP_OK, "result" => $result);
    }

    public function deleteData($table_id, $id)
    {
        $table = $this->dataTableService->get($table_id, false, false);
        if ($table === null)
            return array("response" => Response::HTTP_NOT_FOUND, "result" => null);

        $this->dbDataDAO->removeRow($table->getName(), $id);
        return array("response" => Response::HTTP_OK, "result" => null);
    }

    /**
     * Checks that the decoded request body is an object whose keys are columns of the table.
     * Names must match exactly (as returned by GET): the row update skips any other key.
     *
     * @return string|null error message for the API client, null when the fields are valid
     */
    protected function validateFields(DataTable $table, $data)
    {
        $expected = array();
        foreach ($this->dbStructureDAO->getColumns($table->getName()) as $column) {
            $expected[] = DBStructureDAO::columnName($column);
        }
        $expectedList = "Expected fields: " . implode(", ", $expected);

        if (!is_array($data) || ($data !== array() && array_is_list($data))) {
            return "Incorrect fields: request body must be a JSON object of field names and values. " . $expectedList;
        }

        $unknown = array();
        foreach (array_keys($data) as $field) {
            if (!in_array((string)$field, $expected, true)) {
                $unknown[] = '"' . $field . '"';
            }
        }
        if (count($unknown) > 0) {
            return sprintf(
                "Incorrect fields: unknown field%s %s. %s",
                count($unknown) > 1 ? "s" : "",
                implode(", ", $unknown),
                $expectedList
            );
        }
        return null;
    }

    public function serialize($data, $format = "json")
    {
        switch ($format) {
            default:
                return json_encode($data);
        }
    }

    public function deserialize($data, $format = "json")
    {
        switch ($format) {
            default:
                return json_decode($data, true);
        }
    }

    protected function formatFilter($filter)
    {
        $formatted_filters = array();
        foreach ($filter as $k => $v) {
            unset($filter[$k]);
            $lc = substr($k, strlen($k) - 1, 1);
            $op = "=";
            if ($lc === ">" || $lc === "<" || $lc === "!") {
                $k = substr($k, 0, strlen($k) - 1);
                $op = $lc . $op;
            }
            array_push($formatted_filters, array(
                "name" => $k,
                "op" => $op,
                "value" => $v
            ));
        }
        return $formatted_filters;
    }

    protected function formatDataFilter(DataTable $table, &$filter)
    {
        $formatted_filters = $this->formatFilter($filter);

        $columns = $this->dbStructureDAO->getColumns($table->getName());
        for ($i = 0; $i < count($formatted_filters); $i++) {
            $f = $formatted_filters[$i];
            $found = false;
            foreach ($columns as $column) {
                if (DBStructureDAO::columnName($column) != $f["name"])
                    continue;
                $found = true;
                switch (DBStructureDAO::columnType($column)) {
                    case "date":
                        $f["value"] = new \DateTime($f["value"]);
                        break;
                    case "boolean":
                        $f["value"] = strtolower($f["value"]) == "true";
                        break;
                }
                break;
            }
            $formatted_filters[$i] = $f;
            if (!$found)
                return false;
        }
        $filter = $formatted_filters;
        return true;
    }

}
