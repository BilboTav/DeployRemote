<?php
declare(strict_types=1);

namespace Bilbofox\DeployRemote;

use Nette\Utils\Json;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Nette\Utils\FileSystem;
use ZipArchive;
use RuntimeException;

/**
 * Application factory of deploy remote app
 *
 * @author Michal Kvita <Mikvt@seznam.cz>
 */
class AppFactory
{
    use LoggerAwareTrait;

    /**
     * @param string $rootDir Root directory of server, from which paths are calculated
     * @param string $cryptDir Path for directory with additional files added to unpacked packs, inside should match path from root to project
     * @param string $packsDir Temporary directory for unpacked packs
     * @param string|null $basePath Basepath for application or null for autodetection
     * @param bool $debug Debug mode on/off
     */
    public function __construct(
        private readonly string $rootDir,
        private readonly string $cryptDir,
        private readonly string $packsDir,
        private readonly ?string $basePath = null,
        private readonly bool $debug = false,
    )
    {

    }

    public function create(): App
    {
        $logger = $this->logger ?? new NullLogger();

        $app = SlimAppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addErrorMiddleware(
            displayErrorDetails: $this->debug,
            logErrors: !$this->debug,
            logErrorDetails: !$this->debug,
            logger: $logger,
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
            $response->getBody()->write(Json::encode(['ping' => 'ok']));
            return $response->withHeader('Content-Type', 'application/json');
        });
        $app->post('/', function (Request $request, Response $response) use ($logger): Response {
            $input = (object)$request->getParsedBody();

            if (!isset($input->pack)) {
                return $response->withStatus(400, '"pack" item missing in given request body');
            }
            if (!isset($input->target)) {
                return $response->withStatus(400, '"target" item missing in given request body');
            }

            $packFile = $this->packsDir . '/' . $input->pack;
            if (!file_exists($packFile)) {
                return $response->withStatus(400, sprintf('Given pack file "%s" does not exist in packs directory', $input->pack));
            }

            $logger->info(sprintf('STARTING DEPLOY for target "%s"', $input->target));

            $zip = new ZipArchive;
            $tmpPackDir = $this->packsDir . '/' . pathinfo($input->pack, PATHINFO_FILENAME);
            if ($zip->open($packFile) !== true) {
                throw new RuntimeException('Error while opening pack file');
            }
            if (!$zip->extractTo($tmpPackDir)) {
                throw new RuntimeException('Error while extracting pack file');
            }
            if (!$zip->close()) {
                throw new RuntimeException('Error while closing pack file');
            }

            $logger->info(sprintf('Pack "%s" extracted', $input->pack));

            $targetDir = $this->rootDir . '/' . $input->target;
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

            $targetCryptDir = $this->cryptDir . '/' . $input->target;
            if (file_exists($targetCryptDir)) {
                foreach (scandir($targetCryptDir) as $cryptFile) {
                    if ($cryptFile !== '.' && $cryptFile !== '..') {
                        FileSystem::copy($targetCryptDir . '/' . $cryptFile, $targetDir . '/' . $cryptFile);
                    }
                }
            }

            $logger->info('DEPLOY FINISHED successfully!');

            $response->getBody()->write(Json::encode(['deploy' => 'ok']));
            return $response->withHeader('Content-Type', 'application/json');
        });

        return $app;
    }
}