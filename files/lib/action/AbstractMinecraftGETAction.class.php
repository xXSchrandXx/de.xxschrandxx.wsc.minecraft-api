<?php

namespace wcf\action;

use BadMethodCallException;
use Exception;
use Laminas\Diactoros\HeaderSecurity;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequestFactory;
use ParagonIE\ConstantTime\Base64;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RangeException;
use TypeError;
use wcf\data\minecraft\Minecraft;
use wcf\data\minecraft\MinecraftList;
use wcf\system\event\EventHandler;
use wcf\system\flood\FloodControl;
use wcf\system\request\RouteHandler;
use wcf\util\StringUtil;

/**
 * Abstract Minecraft action class
 *
 * @author   xXSchrandXx
 * @license  Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * @package  WoltLabSuite\Core\Action
 */
abstract class AbstractMinecraftGETAction implements RequestHandlerInterface
{
    private string $floodgate = 'de.xxschrarndxx.wsc.minecraft-api.floodgate';

    /**
     * List of available minecraftIDs
     * @var string
     */
    public $availableMinecraftIDs;

    /**
     * Supported HTTP Method
     * @var string
     */
    protected $supportetMethod = 'GET';

    /**
     * needed modules to execute this action
     * @var string[]
     */
    public $neededModules = [];

    public function __run()
    {
        return $this->handle(ServerRequestFactory::fromGlobals());
    }

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): JsonResponse
    {
        $eventHandler = EventHandler::getInstance();
        $response = null;

        // validate Request
        $this->prepare($request, $response);
        if ($response instanceof JsonResponse) {
            return $response;
        }
        $eventHandler->fireAction($this, 'prepare');

        // validate Header
        $this->validateHeader($request, $response);
        if ($response instanceof JsonResponse) {
            return $response;
        }
        $eventHandler->fireAction($this, 'validateHeader');

        // gets Minecraft
        $minecraft = $this->getMinecraft($request, $response);
        if ($response instanceof JsonResponse) {
            return $response;
        }
        $parameters = [
            'minecraft' => $minecraft,
            'minecraftID' => $minecraft->minecraftID
        ];
        $eventHandler->fireAction($this, 'getMinecraft');

        // check Modules
        $this->checkModules($minecraft, $response);
        if ($response instanceof JsonResponse) {
            return $response;
        }
        $eventHandler->fireAction($this, 'checkModules');

        // reads Parameters
        $this->readParameters($request, $parameters, $response);
        if ($response instanceof JsonResponse) {
            return $response;
        }
        $eventHandler->fireAction($this, 'readParameters', $parameters);

        // validates Parameters
        $this->validateParameters($parameters, $response);
        if ($response instanceof JsonResponse) {
            return $response;
        }
        $eventHandler->fireAction($this, 'validateParameters', $parameters);

        // executes action
        $response = $this->execute($parameters);
        $parameters['response'] = $response;
        $eventHandler->fireAction($this, 'execute', $parameters);

        if (isset($parameters['response']) && $parameters['response'] instanceof JsonResponse) {
            return $parameters['response'];
        } else if (ENABLE_DEBUG_MODE) {
            return $this->send('Internal Error. No valid Response.', 500);
        } else {
            return $this->send('Internal Error.', 500);
        }
    }

    /**
     * Validates request.
     * Checks floodgate and request method.
     * @return void
     */
    private function prepare($request, &$response): void
    {
        if (!RouteHandler::getInstance()->secureConnection()) {
            $response = $this->send('SSL Certificate Required', 496);
            return;
        }

        // Flood control
        if (MINECRAFT_FLOODGATE_MAXREQUESTS > 0) {
            FloodControl::getInstance()->registerContent($this->floodgate);

            $secs = MINECRAFT_FLOODGATE_RESETTIME * 60;
            $time = \ceil(TIME_NOW / $secs) * $secs;
            $data = FloodControl::getInstance()->countContent($this->floodgate, new \DateInterval('PT' . MINECRAFT_FLOODGATE_RESETTIME . 'M'), $time);
            if ($data['count'] > MINECRAFT_FLOODGATE_MAXREQUESTS) {
                $response = $this->send('Too Many Requests.', 429, [], ['retryAfter' => $time - TIME_NOW]);
                return;
            }
        }

        if (!isset($request)) {
            if (ENABLE_DEBUG_MODE) {
                $response =  $this->send('Bad Request. Could not read request.', 400);
            } else {
                $response =  $this->send('Bad Request.', 400);
            }
            return;
        }

        if ($this->supportetMethod !== $request->getMethod()) {
            if (ENABLE_DEBUG_MODE) {
                $response =  $this->send('Bad Request. \'' . $request->getMethod() . '\' is a unsupported HTTP method.', 400);
            } else {
                $response =  $this->send('Bad Request.', 400);
            }
            return;
        }
    }

    /**
     * Validates header
     * @return void
     */
    public function validateHeader($request, &$response): void
    {
        // validate header
        if (empty($request->getHeaders())) {
            if (ENABLE_DEBUG_MODE) {
                $response =  $this->send('Bad Request. Could not read headers.', 400);
            } else {
                $response =  $this->send('Bad Request.', 400);
            }
            return;
        }

        // validate Authorization
        if (!$request->hasHeader('authorization')) {
            if (ENABLE_DEBUG_MODE) {
                $response =  $this->send('Bad Request. Missing \'Authorization\' in headers.', 400);
            } else {
                $response =  $this->send('Bad Request.', 400);
            }
            return;
        }
    }

    /**
     * Gets minecraft from header
     * @return ?Minecraft
     */
    public function getMinecraft($request, &$response): ?Minecraft
    {
        // read header
        [$method, $encoded] = \explode(' ', $request->getHeaderLine('authorization'), 2);
        if ($method !== 'Basic') {
            if (ENABLE_DEBUG_MODE) {
                $response =  $this->send('Bad Request. \'Authorization\' not supported.', 400);
            } else {
                $response =  $this->send('Bad Request.', 400);
            }
            return null;
        }
        try {
            $decoded = Base64::decode($encoded);
        } catch (RangeException $e) {
            if (ENABLE_DEBUG_MODE) {
                $response =  $this->send('Bad Request. ' . $e->getMessage(), 400);
            } else {
                $response =  $this->send('Bad Request.', 400);
            }
            return null;
        } catch (TypeError $e) {
            if (ENABLE_DEBUG_MODE) {
                $response =  $this->send('Bad Request. ' . $e->getMessage(), 400);
            } else {
                $response =  $this->send('Bad Request.', 400);
            }
            return null;
        }
        $decodedArr = \explode(':', $decoded, 2);
        if (!$decodedArr) {
            if (ENABLE_DEBUG_MODE) {
                $response =  $this->send('Bad Request. \'Authorization\' string wrong formatted.', 400);
            } else {
                $response =  $this->send('Bad Request.', 400);
            }
            return null;
        }

        $minecraftList = new MinecraftList();
        $minecraftList->getConditionBuilder()->add('user = ?', [$decodedArr[0]]);
        $minecraftList->readObjects();
        try {
            /** @var Minecraft */
            $minecraft = $minecraftList->getSingleObject();
        } catch (BadMethodCallException $e) {
            // handled by !isset
        }

        if (!isset($minecraft)) {
            if (ENABLE_DEBUG_MODE) {
                $response = $this->send('Unauthorized. Unknown user or password.', 401);
            } else {
                $response = $this->send('Unauthorized.', 401);
            }
            return null;
        }

        if (!$minecraft->check($decodedArr[1])) {
            if (ENABLE_DEBUG_MODE) {
                $response = $this->send('Unauthorized. Unknown user or password.', 401);
            } else {
                $response = $this->send('Unauthorized.', 401);
            }
            return null;
        }

        return $minecraft;
    }

    /**
     * Checks the modules of this action.
     * @return void
     */
    public function checkModules($minecraft, &$response): void
    {
        if (isset($this->availableMinecraftIDs)) {
            if (!in_array($minecraft->getObjectID(), explode('\n', StringUtil::unifyNewlines($this->availableMinecraftIDs)))) {
                if (ENABLE_DEBUG_MODE) {
                    $response = $this->send('Bad Request. Unknown \'Minecraft-Id\'.', 400);
                } else {
                    $response = $this->send('Bad Request.', 400);
                }
                return;
            }
        }

        // check modules
        foreach ($this->neededModules as $module) {
            if (!\defined($module) || !\constant($module)) {
                if (ENABLE_DEBUG_MODE) {
                    $response = $this->send('Bad Request. Module not set \'' . $module . '\'.', 400);
                } else {
                    $response = $this->send('Bad Request.', 400);
                }
                return;
            }
        }
    }

    /**
     * Reads the given parameters.
     * @return void
     */
    public function readParameters($request, &$parameters, &$response): void
    {
        // Nothing to read by GET
    }

    /**
     * Validates the given parameters.
     * @return void
     */
    public function validateParameters($parameters, &$response): void
    {
        // nothing to validate
    }

    /**
     * Executes this action.
     * @return JsonResponse
     */
    abstract public function execute($parameters): JsonResponse;

    /**
     * Creates the JSON-Response
     * @param string $status Status-Message
     * @param int $statusCode Status-Code (between {@link JsonResponse::MIN_STATUS_CODE_VALUE} and {@link JsonResponse::MAX_STATUS_CODE_VALUE})
     * @param array $data JSON-Data
     * @param array $headers Headers
     * @param int $encodingOptions {@link JsonResponse::DEFAULT_JSON_FLAGS}
     * @throws Exception\InvalidArgumentException if unable to encode the $data to JSON or not valid $statusCode.
     */
    protected function send(string $status = 'OK', int $statusCode = 200, array $data = [], array $headers = [], int $encodingOptions = JsonResponse::DEFAULT_JSON_FLAGS): JsonResponse
    {
        if (!array_key_exists('status', $data)) {
            $data['status'] = $status;
        }
        if (!array_key_exists('statusCode', $data)) {
            $data['statusCode'] = $statusCode;
        }
        if (!array_key_exists('status', $headers) && HeaderSecurity::isValid($status)) {
            $headers['status-message'] = [$status];
        }
        return new JsonResponse($data, $statusCode, $headers, $encodingOptions);
    }
}
