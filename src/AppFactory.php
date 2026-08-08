<?php
declare(strict_types=1);

namespace Bilbofox\DeployRemote;

use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Nette\Utils\FileSystem;

class AppFactory
{
    public function __construct(
        private readonly string $rootDir,
        private readonly string $packsDir,
        private readonly string $cryptDir,
        private readonly ?string $basePath = null,
        private readonly bool $debug = false,
    )
    {

    }

    public function create(): App
    {
        $app = SlimAppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addErrorMiddleware(
            displayErrorDetails: $this->debug,
            logErrors: false,
            logErrorDetails: false
        );
        $app->add(function (Request $request, RequestHandler $handler) use ($app): Response {
            if ($this->basePath === null) {
                $basePath = '';
                $serverParams = $request->getServerParams();
                $scriptNameDir = dirname($serverParams['SCRIPT_NAME']);
                if (str_starts_with($serverParams['REQUEST_URI'], $scriptNameDir)) {
                    $basePath = rtrim($scriptNameDir, '/');
                }
            } else {
                $basePath = $this->basePath;
            }

            $app->setBasePath($basePath);

            return $handler->handle($request);
        });

        $app->get('/', function (Request $request, Response $response): Response {
            $response->getBody()->write('Deploy app is ready! Please use POST request with proper JSON body.');
            return $response;
        });
        $app->post('/', function (Request $request, Response $response): Response {
            $input = (object)$request->getParsedBody();

            /*if (!isset($input->pack) || !file_exists($packsDir . '/' . $input->pack)) {
                return $response->withStatus(400, 'Given pack file does not exist');
            }

            $zip = new ZipArchive;
            $tmpPackDir = $packsDir . '/' . pathinfo($input->pack, PATHINFO_FILENAME);
            if ($zip->open($packsDir . '/' . $input->pack) === true) {
                $zip->extractTo($tmpPackDir);
                $zip->close();
            } else {
                return $response->withStatus(500, 'Error while extracting pack file');
            }

            if (isset($input->target)) {
                $targetDir = $webrootDir . '/' . $input->target;
                $targetDirOld = $targetDir . '_old';

                if (file_exists($targetDirOld)) {
                    FileSystem::delete($targetDirOld);
                }
                if (file_exists($targetDir)) {
                    FileSystem::rename($targetDir, $targetDirOld);
                }

                FileSystem::rename($tmpPackDir, $targetDir);

                if (file_exists($targetDirOld)) {
                    FileSystem::delete($targetDirOld);
                }

                $targetCryptDir = $cryptDir . '/' . $input->target;
                if (file_exists($targetCryptDir)) {
                    foreach (scandir($targetCryptDir) as $cryptFile) {
                        if ($cryptFile !== '.' && $cryptFile !== '..') {
                            FileSystem::copy($targetCryptDir . '/' . $cryptFile, $targetDir . '/' . $cryptFile);
                        }
                    }
                }
            }*/

            return $response;
        });

    }
}