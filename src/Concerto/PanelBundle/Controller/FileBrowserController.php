<?php

namespace Concerto\PanelBundle\Controller;

use Concerto\PanelBundle\Utils\RequestParameters;
use Concerto\PanelBundle\Service\FileService;
use Twig\Environment;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted(new Expression("is_granted('ROLE_FILE') or is_granted('ROLE_SUPER_ADMIN')"))]
class FileBrowserController
{

    private $templating;
    private $fileService;
    private $translator;

    public function __construct(Environment $templating, FileService $fileService, TranslatorInterface $translator)
    {
        $this->templating = $templating;
        $this->fileService = $fileService;
        $this->translator = $translator;
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/list', name: 'FileBrowser_list', methods: ['POST'])]
    public function listAction(Request $request)
    {
        $path = RequestParameters::get($request, "path");
        $result = $this->fileService->listFiles($path);
        return $this->successPostResponse($result);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/upload', name: 'FileBrowser_upload', methods: ['POST'])]
    public function uploadAction(Request $request)
    {
        $dir = RequestParameters::get($request, "dir");
        $destination = RequestParameters::get($request, "destination");
        if ($dir === null) $dir = FileService::DIR_PUBLIC;
        $result = $this->fileService->uploadFiles($dir, $destination, $request->files, $error);
        if ($result === true) return $this->successPostResponse();
        else return $this->errorResponse($error);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/rename', name: 'FileBrowser_rename', methods: ['POST'])]
    public function renameAction(Request $request)
    {
        $item = RequestParameters::get($request, "item");
        $newItemPath = RequestParameters::get($request, "newItemPath");
        $result = $this->fileService->renameFile($item, $newItemPath, $error);
        if ($result === true) return $this->successPostResponse();
        else return $this->errorResponse($error);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/copy', name: 'FileBrowser_copy', methods: ['POST'])]
    public function copyAction(Request $request)
    {
        $items = RequestParameters::get($request, "items");
        $newPath = RequestParameters::get($request, "newPath");
        $singleDstFileName = RequestParameters::get($request, "singleFilename");
        $result = $this->fileService->copyFiles($items, $newPath, $singleDstFileName, $error);
        if ($result === true) return $this->successPostResponse();
        else return $this->errorResponse($error);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/move', name: 'FileBrowser_move', methods: ['POST'])]
    public function moveAction(Request $request)
    {
        $items = RequestParameters::get($request, "items");
        $newPath = RequestParameters::get($request, "newPath");
        $result = $this->fileService->moveFiles($items, $newPath, $error);
        if ($result === true) return $this->successPostResponse();
        else return $this->errorResponse($error);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/delete', name: 'FileBrowser_delete', methods: ['POST'])]
    public function deleteAction(Request $request)
    {
        $items = RequestParameters::get($request, "items");
        $result = $this->fileService->deleteFiles($items, $error);
        if ($result === true) return $this->successPostResponse();
        else return $this->errorResponse($error);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/edit', name: 'FileBrowser_edit', methods: ['POST'])]
    public function editAction(Request $request)
    {
        $item = RequestParameters::get($request, "item");
        $content = RequestParameters::get($request, "content");
        $result = $this->fileService->editFile($item, $content, $error);
        if ($result === true) return $this->successPostResponse();
        else return $this->errorResponse($error);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/content', name: 'FileBrowser_content', methods: ['POST'])]
    public function getContentAction(Request $request)
    {
        $item = RequestParameters::get($request, "item");
        $result = $this->fileService->getFileContent($item, $error);
        if ($result === false) return $this->errorResponse($error);
        else return $this->successPostResponse($result);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/create_directory', name: 'FileBrowser_create_directory', methods: ['POST'])]
    public function createDirectoryAction(Request $request)
    {
        $newPath = RequestParameters::get($request, "newPath");
        $result = $this->fileService->createDirectory($newPath, $error);
        if ($result === true) return $this->successPostResponse();
        else return $this->errorResponse($error);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/download', name: 'FileBrowser_download', methods: ['GET'])]
    public function downloadAction(Request $request)
    {
        $path = RequestParameters::get($request, "path");
        $fileName = $this->fileService->canonicalizePath(basename($path));
        $path = realpath($this->fileService->getPublicUploadDirectory()) . "/" . $this->fileService->canonicalizePath($path);
        if (!file_exists($path)) {
            return $this->errorResponse("file_not_found");
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $path);
        finfo_close($finfo);

        $response = new Response(file_get_contents($path));
        $response->headers->set("Content-Disposition", "attachment; filename=\"$fileName\"");
        $response->headers->set("Cache-Control", "must-revalidate, post-check=0, pre-check=0");
        $response->headers->set("Content-Type", $mime_type);
        $response->headers->set("Pragma", "public");
        $response->headers->set("Content-Length", filesize($path));
        return $response;
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/download_multiple', name: 'FileBrowser_download_multiple', methods: ['GET'])]
    public function downloadMultipleAction(Request $request)
    {
        $toFilename = RequestParameters::get($request, "toFilename");
        $items = json_decode(RequestParameters::get($request, "items"), true);

        $zipResult = $this->fileService->createTempArchive($items, $archivePath, $error);
        if ($zipResult !== true) {
            return $this->errorResponse($error);
        }

        $response = new Response(file_get_contents($archivePath));
        $response->headers->set("Content-Disposition", "attachment; filename=\"$toFilename\"");
        $response->headers->set("Cache-Control", "must-revalidate, post-check=0, pre-check=0");
        $response->headers->set("Content-Type", "application/zip");
        $response->headers->set("Pragma", "public");
        $response->headers->set("Content-Length", filesize($archivePath));
        unlink($archivePath);
        return $response;
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/compress', name: 'FileBrowser_compress', methods: ['POST'])]
    public function compressAction(Request $request)
    {
        $items = RequestParameters::get($request, "items");
        $destination = RequestParameters::get($request, "destination");
        $compressedFilename = RequestParameters::get($request, "compressedFilename");
        $result = $this->fileService->compressFiles($items, $destination, $compressedFilename, $error);
        if ($result === true) return $this->successPostResponse();
        else return $this->errorResponse($error);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/extract', name: 'FileBrowser_extract', methods: ['POST'])]
    public function extractAction(Request $request)
    {
        $destination = RequestParameters::get($request, "destination");
        $item = RequestParameters::get($request, "item");
        $folderName = RequestParameters::get($request, "folderName");
        $result = $this->fileService->extractFiles($destination, $item, $folderName, $error);
        if ($result === true) return $this->successPostResponse();
        else return $this->errorResponse($error);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/permissions', name: 'FileBrowser_permissions', methods: ['POST'])]
    public function permissionsAction(Request $request)
    {
        $items = RequestParameters::get($request, "items");
        $permsCode = RequestParameters::get($request, "permsCode");
        $recursive = RequestParameters::get($request, "recursive");
        $result = $this->fileService->setPermissions($items, $permsCode, $recursive, $error);
        if ($result === true) return $this->successPostResponse();
        else return $this->errorResponse($error);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/file/browser', name: 'FileBrowser_browser')]
    public function fileBrowserAction(Request $request)
    {
        return new Response($this->templating->render('@ConcertoPanel/FileBrowser/file_browser.html.twig'));
    }

    private function errorResponse($errorMessage, $status = 500)
    {
        $response = new Response(json_encode(array("result" => array(
            "success" => false,
            "error" => $this->translator->trans("errors.$errorMessage", array(), "FileBrowser")
        ))), $status);
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    private function successPostResponse($result = null)
    {
        $response = new Response();
        if ($result !== null) {
            $response->setContent(json_encode(array("result" => $result)));
        } else {
            $response->setContent(json_encode(array("result" => array(
                "success" => true,
                "error" => null
            ))));
        }
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}
