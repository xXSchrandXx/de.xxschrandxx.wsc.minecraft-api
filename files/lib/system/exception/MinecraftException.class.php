<?php

namespace wcf\system\exception;

use Laminas\Diactoros\HeaderSecurity;
use Laminas\Diactoros\Response\JsonResponse;
use RuntimeException;
use Throwable;

class MinecraftException extends RuntimeException
{
    /**
     * @var ?JsonResponse
     */
    protected $response;

    /**
     * @inheritDoc
     * @param ?JSonResponse $response
     */
    public function __construct(string $message = "", int $code = 0, ?JsonResponse $response = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    public function getResponse()
    {
        if (!isset($this->response)) {
            $message = '';
            if (isset($this->message)) {
                $message = $this->message;
            }
            $code = 0;
            if (isset($this->code)) {
                $code = $this->code;
            }
            $data = [
                'status' => $message,
                'statusCode' => $code
            ];
            if (!array_key_exists('status', $data)) {
                $data['status'] = $message;
            }
            if (!array_key_exists('statusCode', $data)) {
                $data['statusCode'] = $code;
            }
            if (ENABLE_DEBUG_MODE) {
                $data['debug'] = $this;
            }
            if (HeaderSecurity::isValid($message)) {
                $headers['status-message'] = [$message];
            }
            $this->response = new JsonResponse($data, $code);
        }
        return $this->response;
    }
}
