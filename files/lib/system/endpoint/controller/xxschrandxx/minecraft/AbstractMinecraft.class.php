<?php

namespace wcf\system\endpoint\controller\xxschrandxx\minecraft;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\TextResponse;
use Override;
use ParagonIE\ConstantTime\Base64;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RangeException;
use TypeError;
use wcf\data\minecraft\Minecraft;
use wcf\system\endpoint\GetRequest;
use wcf\system\endpoint\IController;
use wcf\system\event\EventHandler;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\exception\SystemException;
use wcf\system\exception\UserInputException;
use wcf\system\flood\FloodControl;
use wcf\system\request\RouteHandler;
use wcf\util\StringUtil;

#[GetRequest('/xxschrandxx/minecraft/{id:\d+}')]
abstract class AbstractMinecraft implements IController
{
    private string $floodgate = 'de.xxschrarndxx.wsc.minecraft-api.floodgate';

    /**
     * @var ServerRequestInterface
     */
    public $request;

    /**
     * List of available minecraftIDs
     * @var string
     */
    public $availableMinecraftIDs;

    /**
     * Minecraft ID for this request
     * @var int
     */
    public $minecraftID;

    /**
     * Minecraft for this request
     * @var Minecraft
     */
    public $minecraft;

    /**
     * Needed modules to execute this action
     * @var string[]
     */
    public $neededModules = [];

    /**
     * @var ?ResponseInterface
     */
    public $response = null;

    #[Override]
    public function __invoke(ServerRequestInterface $request, array $variables): ResponseInterface
    {
        $this->request = $request;

        // load EventHandler
        $eventHandler = EventHandler::getInstance();

        // validate Request
        $this->response = $this->prepare();
        $eventHandler->fireAction($this, 'prepare');
        if ($this->response instanceof ResponseInterface) {
            return $this->response;
        }

        // validateVariables parameters
        $this->validateVariables($variables);
        $eventHandler->fireAction($this, 'execute');

        // gets Minecraft
        $this->getMinecraft();
        $eventHandler->fireAction($this, 'getMinecraft');

        // check weather request is authenticated
        $this->checkPermissions($request);
        $eventHandler->fireAction($this, 'checkPermissions');

        // execute
        $this->response = $this->execute();
        $eventHandler->fireAction($this, 'execute');
        if ($this->response instanceof ResponseInterface) {
            return $this->response;
        }

        // set final response
        if (isset($this->response) && $this->response instanceof ResponseInterface) {
            return $this->response;
        } else if (ENABLE_DEBUG_MODE) {
            throw new SystemException('Internal Error. No valid Response.', 500);
        } else {
            throw new SystemException('Internal Error.', 500);
        }
    }

    /**
     * Validates request.
     * Checks modules, ssl and floodgate.
     * Should never be skipped
     * @param $request
     * @param $variables
     * @return ?ResponseInterface
     */
    public function prepare(): ?ResponseInterface
    {
        // Check Modules
        foreach ($this->neededModules as $module) {
            if (!\defined($module) || !\constant($module)) {
                if (ENABLE_DEBUG_MODE) {
                    return new TextResponse('Bad Request. Module not set \'' . $module . '\'.', 400);
                } else {
                    return new TextResponse('Bad Request.', 400);
                }
            }
        }

        // Check secureConnection
        if (!ENABLE_DEVELOPER_TOOLS && !RouteHandler::getInstance()->secureConnection()) {
            return new EmptyResponse(496);
        }

        // Flood control
        if (MINECRAFT_FLOODGATE_MAXREQUESTS > 0) {
            FloodControl::getInstance()->registerContent($this->floodgate);

            $secs = MINECRAFT_FLOODGATE_RESETTIME * 60;
            $time = \ceil(TIME_NOW / $secs) * $secs;
            $data = FloodControl::getInstance()->countContent($this->floodgate, new \DateInterval('PT' . MINECRAFT_FLOODGATE_RESETTIME . 'M'), $time);
            if ($data['count'] > MINECRAFT_FLOODGATE_MAXREQUESTS) {
                return new EmptyResponse(429, ['retryAfter' => $time - TIME_NOW]);
            }
        }
        return null;
    }

    /**
     * Gets minecraft
     * This method should not be modified
     * @param $request
     * @param $variables
     * @throws UserInputException
     * @return void
     */
    public function getMinecraft(): void
    {
        // check if Minecraft-Entry is allowed to be used for given action
        if (isset($this->availableMinecraftIDs)) {
            if (!in_array($this->minecraftID, explode("\n", StringUtil::unifyNewlines($this->availableMinecraftIDs)))) {
                throw new UserInputException('id', 'unknown');
            }
        }

        // search for Minecraft-Entry with given user
        $this->minecraft = new Minecraft($this->minecraftID);

        // validate Minecraft-Entry exists
        if (!$this->minecraft->getObjectID()) {
            throw new UserInputException('id', 'unknown');
        }
    }

    /**
     * Checks weather request is permitted
     * This method should not be modified
     * @throws SystemException
     * @throws PermissionDeniedException
     * @return void
     */
    public function checkPermissions(ServerRequestInterface $request): void
    {
        // validate request has Headers
        if (empty($request->getHeaders())) {
            if (ENABLE_DEBUG_MODE) {
                throw new SystemException('Bad Request. Could not read headers.', 400);
            } else {
                throw new SystemException('Bad Request.', 400);
            }
        }

        // validate request has Authorization Header
        if (!$request->hasHeader('authorization')) {
            if (ENABLE_DEBUG_MODE) {
                throw new SystemException('Bad Request. Missing \'Authorization\' in headers.', 400);
            } else {
                throw new SystemException('Bad Request.', 400);
            }
        }

        // read header
        [$method, $encoded] = \explode(' ', $request->getHeaderLine('authorization'), 2);
        // validate Authentication Method
        if ($method !== 'Basic') {
            if (ENABLE_DEBUG_MODE) {
                throw new SystemException('Bad Request. \'Authorization\' not supported.', 400);
            } else {
                throw new SystemException('Bad Request.', 400);
            }
        }
        // Try to decode Authentication
        try {
            $decoded = Base64::decode($encoded);
        } catch (RangeException $e) {
            if (ENABLE_DEBUG_MODE) {
                throw new SystemException('Bad Request. ' . $e->getMessage(), 400, '', $e);
            } else {
                throw new SystemException('Bad Request.', 400);
            }
        } catch (TypeError $e) {
            if (ENABLE_DEBUG_MODE) {
                throw new SystemException('Bad Request. ' . $e->getMessage(), 400);
            } else {
                throw new SystemException('Bad Request.', 400);
            }
        }

        // split to user and password
        $decodedArr = \explode(':', $decoded, 2);
        // validate that user and password are given
        if (!$decodedArr) {
            if (ENABLE_DEBUG_MODE) {
                throw new SystemException('Bad Request. \'Authorization\' string wrong formatted.', 400);
            } else {
                throw new SystemException('Bad Request.', 400);
            }
        }

        // check user and password
        if (!hash_equals($this->minecraft->getUser(), $decodedArr[0]) || !$this->minecraft->check($decodedArr[1])) {
            if (ENABLE_DEBUG_MODE) {
                throw new PermissionDeniedException('Unauthorized. Unknown user or password.', 401);
            } else {
                throw new PermissionDeniedException('Unauthorized.', 401);
            }
        }
    }

    /**
     * Reads the given parameters.
     * Has no parameters to read with HTTP-GET method.
     * @param array &$parameters modifiable parameters
     * @throws UserInputException
     * @return void
     */
    public function validateVariables(array $variables): void
    {
        if (!array_key_exists('id', $variables))
            throw new UserInputException('id');
        $this->minecraftID = $variables['id'];
    }

    /**
     * Executes this action.
     * @return ?ResponseInterface
     */
    abstract public function execute(): ?ResponseInterface;
}
