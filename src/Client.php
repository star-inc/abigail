<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
declare(strict_types=1);

namespace Abigail;

class Client
{
    /**
     * Current output format.
     *
     * @var string
     */
    private string $outputFormat = 'json';

    /**
     * List of possible output formats.
     *
     * @var array
     */
    private array $outputFormats = array(
        'json' => '\\Abigail\\Encoder\\JSON',
        'xml' => '\\Abigail\\Encoder\\XML'
    );

    /**
     * List of possible methods.
     * @var array
     */
    public array $methods = array('get', 'post', 'put', 'delete', 'head', 'options', 'patch');

    /**
     * Current URL.
     *
     * @var string
     */
    private string $url = "";

    /**
     * @var Server
     *
     */
    private Server $controller;

    /**
     * Custom set http method.
     *
     * @var string
     */
    private string $method = "";


    private static array $statusCodes = array(
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
     * @param Server $pServerController
     */
    public function __construct(Server $pServerController)
    {
        $this->controller = $pServerController;
        if (isset($_SERVER['PATH_INFO'])) {
            $this->setUrl($_SERVER['PATH_INFO']);
        }

        $this->setupFormats();
    }

    /**
     * Sends the actual response.
     *
     * @param string $pHttpCode
     * @param        $pMessage
     * @return mixed
     */
    public function sendResponse(string $pHttpCode, $pMessage)
    {
        $suppressStatusCode = $_GET['_suppress_status_code'] ?? false;
        if ($this->controller->getHttpStatusCodes() &&
            !$suppressStatusCode &&
            php_sapi_name() !== 'cli'
        ) {
            $status = self::$statusCodes[intval($pHttpCode)];
            header('HTTP/1.0 ' . ($status ? $pHttpCode . ' ' . $status : $pHttpCode), true, intval($pHttpCode));
        } elseif (php_sapi_name() !== 'cli') {
            header('HTTP/1.0 200 OK');
        }

        $pMessage = array_reverse($pMessage, true);
        $pMessage['status'] = intval($pHttpCode);
        $pMessage = array_reverse($pMessage, true);

        $method = $this->getOutputFormatEncoder($this->getOutputFormat());

        if (php_sapi_name() !== 'cli') {
            echo $this->$method($pMessage);
            exit;
        } else {
            return $this->$method($pMessage);
        }
    }

    /**
     * @param string $pFormat
     * @return string
     */
    public function getOutputFormatEncoder(string $pFormat): string
    {
        return "{$this->outputFormats[$pFormat]}::export";
    }

    /**
     * @return string
     */
    public function getOutputFormat(): string
    {
        return $this->outputFormat;
    }

    /**
     * Detect the method.
     *
     * @return string
     */
    public function getMethod(): string
    {
        if ($this->method) {
            return $this->method;
        }

        $method = @$_SERVER['REQUEST_METHOD'];
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
        }

        if (isset($_GET['_method'])) {
            $method = $_GET['_method'];
        } else if (isset($_POST['_method'])) {
            $method = $_POST['_method'];
        }

        $method = strtolower($method);

        if (!in_array($method, $this->methods)) {
            $method = 'get';
        }

        return $method;

    }

    /**
     * Sets a custom http method. It does then not check against
     * SERVER['REQUEST_METHOD'], $_GET['_method'], etc. anymore.
     *
     * @param string $pMethod
     * @return Client
     */
    public function setMethod(string $pMethod): Client
    {
        $this->method = $pMethod;

        return $this;
    }

    /**
     * Returns the url.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Set the url.
     *
     * @param string $pUrl
     * @return Client $this
     */
    public function setUrl(string $pUrl): Client
    {
        $this->url = $pUrl;

        return $this;
    }

    /**
     * Setup formats.
     *
     * @return Client
     */
    public function setupFormats(): Client
    {
        // through HTTP_ACCEPT
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], '*/*') === false) {
            foreach (array_keys($this->outputFormats) as $formatCode) {
                if (strpos($_SERVER['HTTP_ACCEPT'], $formatCode) !== false) {
                    $this->outputFormat = $formatCode;
                    break;
                }
            }
        }

        // through uri suffix
        if (preg_match('/\.(\w+)$/i', $this->getUrl(), $matches)) {
            if (isset($this->outputFormats[$matches[1]])) {
                $this->outputFormat = $matches[1];
                $url = $this->getUrl();
                $this->setUrl(substr($url, 0, (strlen($this->outputFormat) * -1) - 1));
            }
        }

        // through _format parameter
        if (isset($_GET['_format'])) {
            if (isset($this->outputFormats[$_GET['_format']])) {
                $this->outputFormat = $_GET['_format'];
            }
        }

        return $this;
    }
}
