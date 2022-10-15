<?php

namespace wcf\action;

use Laminas\Diactoros\Response\JsonResponse;
use SystemException;
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
     * Returns decoded Request-JSON
     * @return array
     */
    public function getJSON()
    {
        return $this->json;
    }

    /**
     * Returns request data
     * @return string|int
     */
    public function getData(string $name)
    {
        return $this->getJSON()[$name];
    }

    /**
     * Reads header
     * @return ?JsonResponse
     */
    public function readHeaders(): ?JsonResponse
    {
        $response = parent::readHeaders();

        // validate Content-Type
        if (!$this->request->hasHeader('content-type')) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Bad Request. Missing \'Content-Type\' in headers.', 400);
            } else {
                return $this->send('Bad Request.', 400);
            }
        }
        if ($this->request->getHeaderLine('content-type') !== 'application/json') {
            if (ENABLE_DEBUG_MODE) {
                return $this->send('Bad Request. Wrong \'Content-Type\'.', 400);
            } else {
                return $this->send('Bad Request.', 400);
            }
        }

        return $response;
    }

    /**
     * Reads the given parameters.
     * @return ?JsonResponse
     */
    public function readParameters(): ?JsonResponse
    {
        $response = parent::readParameters();

        try {
            $this->json = JSON::decode($this->request->getBody()->getContents());
        } catch (SystemException $e) {
            if (ENABLE_DEBUG_MODE) {
                return $this->send($e->getMessage(), 400);
            } else {
                return $this->send('Bad Request.', 400);
            }
        }

        return $response;
    }
}
