<?php

namespace Concerto\APIBundle\Controller;

use Concerto\PanelBundle\Utils\RequestParameters;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Concerto\APIBundle\Service\DataRecordService;
use Concerto\PanelBundle\Service\AdministrationService;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/data/{table_id}')]
class DataRecordController
{

    private $service;
    private $administrationService;

    public function __construct(DataRecordService $service, AdministrationService $administrationService)
    {
        $this->service = $service;
        $this->administrationService = $administrationService;
    }

    /**
     * @param Request $request
     * @param int $table_id
     * @return Response
     */
    #[Route('', methods: ['GET', 'POST', 'PUT'])]
    public function dataCollectionAction(Request $request, $table_id)
    {
        if (!$this->administrationService->isApiEnabled() || !$this->administrationService->isDataApiEnabled())
            return self::textResponse("API disabled", Response::HTTP_FORBIDDEN);

        switch ($request->getMethod()) {
            case "GET":
                return $this->getDataCollection($request, $table_id);
            case "PUT":
            case "POST":
                return $this->insertDataObject($request, $table_id);
        }
    }

    /**
     * @param Request $request
     * @param int $table_id
     * @param int $id
     * @return Response
     */
    #[Route('/{id}', methods: ['GET', 'POST', 'PUT', 'DELETE'])]
    public function dataObjectAction(Request $request, $table_id, $id)
    {
        if (!$this->administrationService->isApiEnabled() || !$this->administrationService->isDataApiEnabled())
            return self::textResponse("API disabled", Response::HTTP_FORBIDDEN);

        switch ($request->getMethod()) {
            case "GET":
                return $this->getDataObject($request, $table_id, $id);
            case "PUT":
            case "POST":
                return $this->updateDataObject($request, $table_id, $id);
            case "DELETE":
                return $this->deleteDataObject($table_id, $id);
        }
    }

    /**
     * Error messages are plain text: they can repeat client input (e.g. unknown field names),
     * which must not be rendered as HTML.
     */
    private static function textResponse($message, $status)
    {
        return new Response($message, $status, array('Content-Type' => 'text/plain; charset=UTF-8'));
    }

    private function getDataObject(Request $request, $table_id, $id)
    {
        $format = RequestParameters::get($request, "format") ? RequestParameters::get($request, "format") : "json";
        $data = $this->service->getData($table_id, $id, $format);

        switch ($data["response"]) {
            case Response::HTTP_NOT_FOUND:
                return self::textResponse("Data not found", $data["response"]);
            default:
                $response = new Response($data["result"], $data["response"]);
                $response->headers->set('Content-Type', 'application/' . $format);
                return $response;
        }
    }

    private function getDataCollection(Request $request, $table_id)
    {
        $format = RequestParameters::get($request, "format") ? RequestParameters::get($request, "format") : "json";
        $filter = $request->query->all();
        $data = $this->service->getDataCollection($table_id, $filter, $format);

        switch ($data["response"]) {
            case Response::HTTP_NOT_FOUND:
                return self::textResponse("Data not found", $data["response"]);
            case Response::HTTP_BAD_REQUEST:
                return self::textResponse("Incorrect filter params", $data["response"]);
            default:
                $response = new Response($data["result"], $data["response"]);
                $response->headers->set('Content-Type', 'application/' . $format);
                return $response;
        }
    }

    private function updateDataObject(Request $request, $table_id, $id)
    {
        if (strpos((string)$request->getContentTypeFormat(), "json") === false) {
            return self::textResponse("Content-Type: application/json expected", Response::HTTP_BAD_REQUEST);
        }
        $format = RequestParameters::get($request, "format") ? RequestParameters::get($request, "format") : "json";
        $newSerializedData = $request->getContent();
        $data = $this->service->updateData($table_id, $id, $newSerializedData, $format);

        switch ($data["response"]) {
            case Response::HTTP_NOT_FOUND:
                return self::textResponse("Data not found", $data["response"]);
            case Response::HTTP_BAD_REQUEST:
                return self::textResponse($data["result"] ?? "Incorrect fields", $data["response"]);
            default:
                $response = new Response($data["result"], $data["response"]);
                $response->headers->set('Content-Type', 'application/' . $format);
                return $response;
        }
    }

    private function insertDataObject(Request $request, $table_id)
    {
        if (strpos((string)$request->getContentTypeFormat(), "json") === false) {
            return self::textResponse("Content-Type: application/json expected", Response::HTTP_BAD_REQUEST);
        }
        $format = RequestParameters::get($request, "format") ? RequestParameters::get($request, "format") : "json";
        $serializedData = $request->getContent();
        $data = $this->service->insertData($table_id, $serializedData, $format);

        switch ($data["response"]) {
            case Response::HTTP_NOT_FOUND:
                return self::textResponse("Data not found", $data["response"]);
            case Response::HTTP_BAD_REQUEST:
                return self::textResponse($data["result"] ?? "Incorrect fields", $data["response"]);
            default:
                $response = new Response($data["result"], $data["response"]);
                $response->headers->set('Content-Type', 'application/' . $format);
                return $response;
        }
    }

    private function deleteDataObject($table_id, $id)
    {
        $data = $this->service->deleteData($table_id, $id);

        switch ($data["response"]) {
            case Response::HTTP_NOT_FOUND:
                return self::textResponse("Data not found", $data["response"]);
            default:
                return new Response($data["result"], $data["response"]);
        }
    }

}
