<?php

namespace wcf\action;

use BadMethodCallException;
use Laminas\Diactoros\HeaderSecurity;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\ServerRequestFactory;
use ParagonIE\ConstantTime\Base64;
use Psr\Http\Message\ServerRequestInterface;
use RangeException;
use TypeError;
use wcf\data\minecraft\Minecraft;
use wcf\data\minecraft\MinecraftList;
use wcf\system\event\EventHandler;
use wcf\system\exception\IllegalLinkException;
use wcf\system\exception\PermissionDeniedException;
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
abstract class AbstractMinecraftGETAction extends AbstractAction
{
    private string $floodgate = 'de.xxschrarndxx.wsc.minecraft-api.floodgate';

    /**
     * List of available minecraftIDs
     * @var string
     */
    protected $availableMinecraftIDs;

    /**
     * Supported HTTP Method
     * @var string
     */
    protected $supportetMethod = 'GET';

    /**
     * Minecraft the request came from.
     * @var Minecraft
     */
    protected $minecraft;

    /**
     * Request headers
     * @var ServerRequestInterface
     */
    protected ServerRequestInterface $request;

    /**
     * @inheritDoc
     */
    public function __run(): ?JsonResponse
    {
        if (!RouteHandler::getInstance()->secureConnection()) {
            return $this->send('SSL Certificate Required', 496);
        }

        // Flood control
        if (MINECRAFT_FLOODGATE_MAXREQUESTS > 0) {
            FloodControl::getInstance()->registerContent($this->floodgate);

            $secs = MINECRAFT_FLOODGATE_RESETTIME * 60;
            $time = \ceil(TIME_NOW / $secs) * $secs;
            $data = FloodControl::getInstance()->countContent($this->floodgate, new \DateInterval('PT' . MINECRAFT_FLOODGATE_RESETTIME . 'M'), $time);
            if ($data['count'] > MINECRAFT_FLOODGATE_MAXREQUESTS) {
                return $this->send('Too Many Requests.', 429, [], ['retryAfter' => $time - TIME_NOW]);
            }
        }

        // Read header
        $this->request = ServerRequestFactory::fromGlobals();

        if (!isset($this->request)) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Bad Request. Could not read request.', 400);
            } else {
                return $this->send('Bad Request.', 400);
            }
        }

        if ($this->supportetMethod !== $this->request->getMethod()) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Bad Request. \'' . $this->request->getMethod() . '\' is a unsupported HTTP method.', 400);
            } else {
                return $this->send('Bad Request.', 400);
            }
        }

        $result = $this->readHeaders();
        if ($result !== null) {
            return $result;
        }

        // check permissions
        try {
            $this->checkPermissions();
        } catch (PermissionDeniedException $e) {
            return $this->send($e->getMessage(), 401);
        }

        if (isset($this->availableMinecraftIDs)) {
            if (!in_array($this->minecraft->getObjectID(), explode('\n', StringUtil::unifyNewlines($this->availableMinecraftIDs)))) {
                if (ENABLE_DEBUG_MODE) {
                    return $this->send('Bad Request. Unknown \'Minecraft-Id\'.', 400);
                } else {
                    return $this->send('Bad Request.', 400);
                }
            }
        }

        $result = $this->readParameters();
        if ($result !== null) {
            return $result;
        }
        $result = $this->execute();
        if ($result === null) {
            return $this->send('Internal Error.', 500);
        }
        return $result;
    }

    /**
     * Reads header
     * @return ?JsonResponse
     */
    public function readHeaders(): ?JsonResponse
    {
        // validate header
        if (empty($this->request->getHeaders())) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Bad Request. Could not read headers.', 400);
            } else {
                return $this->send('Bad Request.', 400);
            }
        }

        // validate Authorization
        if (!$this->request->hasHeader('authorization')) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Bad Request. Missing \'Authorization\' in headers.', 400);
            } else {
                return $this->send('Bad Request.', 400);
            }
        }
        [$method, $encoded] = \explode(' ', $this->request->getHeaderLine('authorization'), 2);
        if ($method !== 'Basic') {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Bad Request. \'Authorization\' not supported.', 400);
            } else {
                return $this->send('Bad Request.', 400);
            }
        }
        try {
            $decoded = Base64::decode($encoded);
        } catch (RangeException $e) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Bad Request. ' . $e->getMessage(), 400);
            } else {
                return $this->send('Bad Request.', 400);
            }
        } catch (TypeError $e) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Bad Request. ' . $e->getMessage(), 400);
            } else {
                return $this->send('Bad Request.', 400);
            }
        }
        $decodedArr = \explode(':', $decoded, 2);
        if (!$decodedArr) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Bad Request. \'Authorization\' string wrong formatted.', 400);
            } else {
                return $this->send('Bad Request.', 400);
            }
        }
        $this->user = $decodedArr[0];
        $this->password = $decodedArr[1];

        return null;
    }

    /**
     * @inheritDoc
     */
    public function checkPermissions()
    {
        $minecraftList = new MinecraftList();
        $minecraftList->getConditionBuilder()->add('user = ?', [$this->user]);
        $minecraftList->readObjects();
        try {
            /** @var Minecraft */
            $this->minecraft = $minecraftList->getSingleObject();
        } catch (BadMethodCallException $e) {
            // handled by !isset 
        }

        if (!isset($this->minecraft)) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Unauthorized. Unknown user or password.', 401);
            } else {
                return $this->send('Unauthorized.', 401);
            }
        }

        if (!$this->minecraft->check($this->password)) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Unauthorized. Unknown user or password.', 401);
            } else {
                return $this->send('Unauthorized.', 401);
            }
        }

        unset($this->user);
        unset($this->password);

        // call checkPermissions event
        EventHandler::getInstance()->fireAction($this, 'checkPermissions');
    }

    /**
     * @inheritDoc
     * @return ?JsonResponse
     */
    public function readParameters(): ?JsonResponse
    {
        parent::readParameters();
        return null;
    }


    /**
     * Executes this action.
     * @return ?JsonResponse
     */
    public function execute(): ?JsonResponse
    {
        // check modules
        try {
            $this->checkModules();
        } catch (IllegalLinkException $e) {
            return $this->send($e->getMessage(), 404);
        }

        // call execute event
        EventHandler::getInstance()->fireAction($this, 'execute');
        return null;
    }

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
