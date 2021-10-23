<?php

// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
declare(strict_types=1);

namespace Abigail\Kernel;

use Abigail\Server;
use Exception;

class Response
{
    private Server $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    /**
     * Send exception function/method. Will be fired if a route-method throws a exception.
     * Please die/exit in your function then.
     * Arguments: (exception)
     *
     * @var callable
     */
    protected $sendExceptionFn;

    /**
     * If the lib should send HTTP status codes.
     * Some Client libs does not support this, you can deactivate it via
     * ->setHttpStatusCodes(false);
     *
     * @var boolean
     */
    protected bool $withStatusCode = true;

    /**
     * @var callable
     */
    protected $successResponseWrapper;

    /**
     * @var array|string[]
     */
    public const STATUS_CODES = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found', // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        509 => 'Bandwidth Limit Exceeded'
    );

    /**
     *
     * @return boolean
     */
    public function getHttpStatusCodes(): bool
    {
        return $this->withStatusCode;
    }

    /**
     * If the lib should send HTTP status codes.
     * Some Client libs does not support it.
     *
     * @param boolean $pWithStatusCode
     * @return Server  $this
     */
    public function setHttpStatusCodes(bool $pWithStatusCode): Server
    {
        $this->withStatusCode = $pWithStatusCode;
        return $this->server;
    }

    /**
     * Getter for successResponseWrapper
     * @return callable|null
     */
    public function getSuccessResponseWrapper(): ?callable
    {
        return $this->successResponseWrapper;
    }

    /**
     * The wrapper for response while it is successful.
     * Will fired with arguments: (pData)
     *
     * @param callable $pFn
     * @return Server   $this
     */
    public function setSuccessResponseWrapper(callable $pFn): Server
    {
        $this->successResponseWrapper = $pFn;
        return $this->server;
    }

    /**
     * Getter for checkAccess
     * @return callable|null
     */
    public function getExceptionHandler(): ?callable
    {
        return $this->sendExceptionFn;
    }

    /**
     * Send exception function/method. Will be fired if a route-method throws a exception.
     * Please die/exit in your function then.
     * Arguments: (exception)
     *
     * @param callable $pFn
     * @return Server   $this
     */
    public function setExceptionHandler(callable $pFn): Server
    {
        $this->sendExceptionFn = $pFn;
        return $this->server;
    }

    /**
     * Sends data to the client with 200 http code.
     *
     * @param $pData
     * @return mixed
     */
    public function send($pData)
    {
        if ($this->successResponseWrapper) {
            $pData = call_user_func_array($this->successResponseWrapper, [$pData]);
        }
        return $this->server->getClient()->sendResponse('200', array('data' => $pData));
    }

    /**
     * Sends a 'Bad Request' response to the client.
     *
     * @param $pCode
     * @param $pMessage
     * @return string
     * @throws Exception
     */
    public function sendBadRequest($pCode, $pMessage): string
    {
        if (is_object($pMessage) && $pMessage->xdebug_message) {
            $pMessage = $pMessage->xdebug_message;
        }
        $msg = array('error' => $pCode, 'message' => $pMessage);
        if (!$this->server->getClient()) {
            throw new Exception('client_not_found_in_ServerController');
        }
        return $this->server->getClient()->sendResponse('400', $msg);
    }

    /**
     * Sends a 'Internal Server Error' response to the client.
     * @param $pCode
     * @param $pMessage
     * @return string
     * @throws Exception
     */
    public function sendError($pCode, $pMessage): string
    {
        if (is_object($pMessage) && $pMessage->xdebug_message) {
            $pMessage = $pMessage->xdebug_message;
        }
        $msg = array('error' => $pCode, 'message' => $pMessage);
        if (!$this->server->getClient()) {
            throw new Exception('client_not_found_in_ServerController');
        }
        return $this->server->getClient()->sendResponse('500', $msg);
    }

    /**
     * Sends a exception response to the client.
     * @param $pException
     * @return mixed
     * @throws Exception
     */
    public function sendException($pException)
    {
        if ($this->sendExceptionFn) {
            call_user_func_array($this->sendExceptionFn, array($pException));
        }

        $message = $pException->getMessage();
        if (is_object($message) && $message->xdebug_message) {
            $message = $message->xdebug_message;
        }

        $msg = array('error' => get_class($pException), 'message' => $message);

        if ($this->server->getDebugMode()) {
            $msg['file'] = $pException->getFile();
            $msg['line'] = $pException->getLine();
            $msg['trace'] = $pException->getTraceAsString();
        }

        if (!$this->server->getClient()) {
            throw new Exception('Client not found in ServerController');
        }

        return $this->server->getClient()->sendResponse('500', $msg);
    }
}
