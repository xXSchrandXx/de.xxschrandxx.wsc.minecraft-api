<?php

namespace wcf\action;

use BadMethodCallException;
use Laminas\Diactoros\Response\JsonResponse;
use wcf\data\minecraft\Minecraft;
use wcf\data\minecraft\MinecraftList;
use wcf\system\event\EventHandler;
use wcf\system\exception\IllegalLinkException;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\flood\FloodControl;
use wcf\system\request\RouteHandler;

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
     * @var int[]
     */
    protected array $availableMinecraftIDs;

    /**
     * Authentication Header String
     */
    protected $auth;

    /**
     * Minecraft the request came from.
     * @var Minecraft
     */
    protected Minecraft $minecraft;

    /**
     * Request headers
     * @var false|array
     */
    protected $headers;

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
        $this->headers = getallheaders();

        if (!is_array($this->headers) || empty($this->headers)) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Bad Request. Could not read headers.', 400);
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
            if (!in_array($this->minecraft->getObjectID(), $this->availableMinecraftIDs)) {
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
        // validate Authorization
        if (!array_key_exists('Authorization', $this->headers)) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Unauthorized. Missing \'Authorization\' in headers.', 401);
            } else {
                return $this->send('Unauthorized.', 401);
            }
        }
        $this->auth = \explode(' ', $this->headers['Authorization'], 2);
        if (count($this->auth) != 2) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Unauthorized. \'Authorization\' wrong formatted.', 401);
            } else {
                return $this->send('Unauthorized.', 401);
            }
        }
        if ($this->auth[0] != 'Basic') {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Unauthorized. \'Authorization\' not supported.', 401);
            } else {
                return $this->send('Unauthorized.', 401);
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function checkPermissions()
    {
        $minecraftList = new MinecraftList();
        $minecraftList->getConditionBuilder()->add('auth = ?', [$this->auth[1]]);
        $minecraftList->readObjects();
        try {
            $this->minecraft = $minecraftList->getSingleObject();
        } catch (BadMethodCallException $e) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Unauthorized. Unknown user or password.', 401);
            } else {
                return $this->send('Unauthorized.', 401);
            }
        }

        // call checkPermissions event
        EventHandler::getInstance()->fireAction($this, 'checkPermissions');
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
        if (!array_key_exists('status', $headers)) {
            $headers['status-message'] = [$status];
        }
        return new JsonResponse($data, $statusCode, $headers, $encodingOptions);
    }
}
