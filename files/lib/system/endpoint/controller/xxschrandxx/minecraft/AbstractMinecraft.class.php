<?php

namespace wcf\system\endpoint\controller\xxschrandxx\minecraft;

use BadMethodCallException;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\TextResponse;
use Override;
use ParagonIE\ConstantTime\Base64;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RangeException;
use TypeError;
use wcf\data\minecraft\Minecraft;
use wcf\data\minecraft\MinecraftList;
use wcf\system\endpoint\GetRequest;
use wcf\system\endpoint\IController;
use wcf\system\event\EventHandler;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\exception\SystemException;
use wcf\system\exception\UserInputException;
use wcf\system\flood\FloodControl;
use wcf\system\request\RouteHandler;
use wcf\util\StringUtil;

/** /xxschrandxx/minecraft **/
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
     * Minecraft for this request
     * @var Minecraft
     */
    public $minecraft;

    /**
     * Needed modules to execute this action
     * @var string[]
     */
    public $neededModules = [];

    /** @var array */
    public $variables;

    /** @var ?ResponseInterface */
    public $response;

    #[Override]
    public function __invoke(ServerRequestInterface $request, array $variables): ResponseInterface
    {
        $this->request = $request;
        $this->variables = $variables;

        // load EventHandler
        $eventHandler = EventHandler::getInstance();

        // validate Request
        $this->response = $this->prepare();
        $eventHandler->fireAction($this, 'prepare');
        if ($this->response instanceof ResponseInterface) {
            return $this->response;
        }

        // gets Minecraft
        $this->getMinecraft();
        $eventHandler->fireAction($this, 'getMinecraft');

        // validate
        $this->validate();
        $eventHandler->fireAction($this, 'execute');

        // execute
        $this->execute();
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
     * @throws UserInputException
     * @return void
     */
    public function getMinecraft(): void
    {
        // validate request has Headers
        if (empty($this->request->getHeaders())) {
            if (ENABLE_DEBUG_MODE) {
                throw new PermissionDeniedException('Bad Request. Could not read headers.');
            } else {
                throw new PermissionDeniedException();
            }
        }

        // validate request has Authorization Header
        if (!$this->request->hasHeader('authorization')) {
            if (ENABLE_DEBUG_MODE) {
                throw new PermissionDeniedException('Bad Request. Missing \'Authorization\' in headers.');
            } else {
                throw new PermissionDeniedException();
            }
        }

        // read header
        [$method, $encoded] = \explode(' ', $this->request->getHeaderLine('authorization'), 2);
        // validate Authentication Method
        if ($method !== 'Basic') {
            if (ENABLE_DEBUG_MODE) {
                throw new PermissionDeniedException('Bad Request. \'Authorization\' not supported.');
            } else {
                throw new PermissionDeniedException();
            }
        }
        // Try to decode Authentication
        try {
            $decoded = Base64::decode($encoded);
        } catch (RangeException $e) {
            if (ENABLE_DEBUG_MODE) {
                throw new PermissionDeniedException('Bad Request. ' . $e->getMessage());
            } else {
                throw new PermissionDeniedException();
            }
        } catch (TypeError $e) {
            if (ENABLE_DEBUG_MODE) {
                throw new PermissionDeniedException('Bad Request. ' . $e->getMessage());
            } else {
                throw new PermissionDeniedException();
            }
        }

        // split to user and password
        $decodedArr = \explode(':', $decoded, 2);
        // validate that user and password are given
        if (!$decodedArr) {
            if (ENABLE_DEBUG_MODE) {
                throw new PermissionDeniedException('Bad Request. \'Authorization\' string wrong formatted.');
            } else {
                throw new PermissionDeniedException();
            }
        }

        // search for Minecraft-Entry with given user
        $minecraftList = new MinecraftList();
        $minecraftList->getConditionBuilder()->add('user = ?', [$decodedArr[0]]);
        $minecraftList->readObjects();
        try {
            /** @var Minecraft */
            $this->minecraft = $minecraftList->getSingleObject();
        } catch (BadMethodCallException $e) {
            // handled by !isset
        }

        // validate Minecraft-Entry exists
        if (!isset($this->minecraft)) {
            if (ENABLE_DEBUG_MODE) {
                throw new PermissionDeniedException('Unauthorized. Unknown user or password.');
            } else {
                throw new PermissionDeniedException();
            }
        }

        // check if Minecraft-Entry is allowed to be used for given action
        if (isset($this->availableMinecraftIDs)) {
            if (!in_array($this->minecraft->getObjectID(), explode("\n", StringUtil::unifyNewlines($this->availableMinecraftIDs)))) {
                if (ENABLE_DEBUG_MODE) {
                    throw new PermissionDeniedException('Unauthorized. Invalid minecraft ID');
                } else {
                    throw new PermissionDeniedException();
                }
            }
        }

        // check password
        if (!$this->minecraft->check($decodedArr[1])) {
            if (ENABLE_DEBUG_MODE) {
                throw new PermissionDeniedException('Unauthorized. Unknown user or password.');
            } else {
                throw new PermissionDeniedException();
            }
        }
    }

    /**
     * Validates this action.
     * @throws UserInputException
     * @return void
     */
    public function validate(): void
    {
        // Has no parameters to read
    }

    /**
     * Executes this action.
     * @return void
     */
    abstract public function execute(): void;
}
