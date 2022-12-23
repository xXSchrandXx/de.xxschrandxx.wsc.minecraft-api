<?php

namespace wcf\action;

use Laminas\Diactoros\Response\JsonResponse;
use TypeError;
use wcf\util\JSON;

/**
 * Abstract Minecraft action class
 *
 * @author   xXSchrandXx
 * @license  Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
 * @package  WoltLabSuite\Core\Action
 */
abstract class AbstractMinecraftAction extends AbstractMinecraftGETAction
{
    /**
     * @inheritDoc
     */
    protected $supportetMethod = 'POST';

    /**
     * decoded JSON request body
     * @var array
     */
    protected $json;

    /**
     * Returns decoded Request-JSON
     * @return array
     */
    public function getJSON()
    {
        return $this->json;
    }

    /**
     * Returns weather the data exists
     * @return bool
     */
    public function hasData(string $name)
    {
        try {
            return array_key_exists($name, $this->json);
        } catch (TypeError $e) {
            // Catch 'array_key_exists(): Argument #2 ($array) must be of type array, null given'
            return false;
        }
    }

    /**
     * Returns request data
     * @return mixed
     */
    public function getData(string $name)
    {
        return $this->json[$name];
    }

    /**
     * Reads header
     */
    public function readHeaders()
    {
        parent::readHeaders();

        // validate Content-Type
        if (!$this->request->hasHeader('content-type')) {
            if (ENABLE_DEBUG_MODE) {
                throw $this->exception('Bad Request. Missing \'Content-Type\' in headers.', 400);
            } else {
                throw $this->exception('Bad Request.', 400);
            }
        }
        if ($this->request->getHeaderLine('content-type') !== 'application/json') {
            if (ENABLE_DEBUG_MODE) {
                throw $this->exception('Bad Request. Wrong \'Content-Type\'.', 400);
            } else {
                throw $this->exception('Bad Request.', 400);
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function readParameters()
    {
        parent::readParameters();

        $this->json = JSON::decode((string) $this->request->getBody());
    }
}
