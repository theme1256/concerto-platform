<?php

namespace Concerto\PanelBundle\Controller;

use Concerto\PanelBundle\Utils\RequestParameters;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Concerto\PanelBundle\Service\ASectionService;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class ASectionController
{

    protected $entityName;
    protected $service;
    protected $templating;
    protected $translator;

    public function __construct(Environment $templating, ASectionService $service, TranslatorInterface $translator)
    {
        $this->templating = $templating;
        $this->service = $service;
        $this->translator = $translator;
    }

    protected function getSaveResponse($result)
    {
        $result["errors"] = $this->trans($result["errors"]);
        $result["result"] = count($result["errors"]) > 0 ? 1 : 0;
        $result["objectTimestamp"] = time();

        $response = new Response(json_encode($result));
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function deleteAction(Request $request, $object_ids)
    {
        $timestamp = RequestParameters::get($request, "objectTimestamp");
        if (!$this->service->canBeModified($object_ids, $timestamp, $errorMessages)) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $this->trans($errorMessages))));
            $response->headers->set('Content-Type', 'application/json');
            return $response;
        }

        $result = $this->service->delete($object_ids);
        $errors = array();
        foreach ($result as $r) {
            for ($i = 0; $i < count($r['errors']); $i++) {
                $errors[] = "#" . $r["object"]->getId() . ": " . $r["object"]->getName() . " - " . $this->trans($r['errors'][$i]);
            }
        }
        if (count($errors) > 0) {
            $response = new Response(json_encode(array("result" => 1, "errors" => $errors)));
        } else {
            $response = new Response(json_encode([
                "result" => 0,
                "objectTimestamp" => time()
            ]));
        }
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function objectAction($object_id, $format)
    {
        return new Response($this->templating->render("@ConcertoPanel/collection.$format.twig", array(
            'collection' => $this->service->get($object_id)
        )));
    }

    public function collectionAction($format)
    {
        $collection = $this->service->getAll();
        return new Response($this->templating->render("@ConcertoPanel/collection.$format.twig", array(
            'collection' => $collection
        )));
    }

    public function formAction($action = "edit", $params = array())
    {
        $p = array(
            "isAddDialog" => $action === "add"
        );
        foreach ($params as $k => $v) {
            $p[$k] = $v;
        }
        return new Response($this->templating->render("@ConcertoPanel/" . $this->entityName . "/form.html.twig", $p));
    }

    protected function trans($messages, $domain = null)
    {
        if (!$messages) return $messages;
        if (is_array($messages)) {
            foreach ($messages as &$message) {
                $message = $this->translator->trans($message, [], $domain);
            }
            return $messages;
        }
        return $this->translator->trans($messages, [], $domain);
    }
}
