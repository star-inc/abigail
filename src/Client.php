<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
// (c) MArc J. Schmidt (https://marcjschmidt.de)
declare(strict_types=1);

namespace Abigail;

class Client
{
    /**
     * List of possible methods.
     * @var array
     */
    public array $methods = array('get', 'post', 'put', 'delete', 'head', 'options', 'patch');

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
        'json' => \Abigail\Encoder\JSON::class,
        'xml' => Abigail\Encoder\XML::class
    );

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
     * Send the actual response.
     *
     * @param string $pHttpCode
     * @param mixed $pMessage
     * @return string
     */
    public function sendResponse(string $pHttpCode, $pMessage): string
    {
        $suppressStatusCode = $_GET['_suppress_status_code'] ?? false;

        if (
            $this->controller->getResponse()->getHttpStatusCodes() &&
            !$suppressStatusCode &&
            php_sapi_name() !== 'cli'
        ) {
            $status = Kernel\Response::STATUS_CODES[intval($pHttpCode)];
            header('HTTP/1.0 ' . ($status ? $pHttpCode . ' ' . $status : $pHttpCode), true, intval($pHttpCode));
        } elseif (php_sapi_name() !== 'cli') {
            header('HTTP/1.0 200 OK');
        }

        $pMessage = array_reverse($pMessage, true);
        $pMessage['status'] = intval($pHttpCode);
        $pMessage = array_reverse($pMessage, true);

        $encoder = $this->getOutputFormatEncoder($this->getOutputFormat());
        $pMessageEncoded = $encoder($pMessage);

        if (php_sapi_name() !== 'cli') {
            echo $pMessageEncoded;
        }

        return $pMessageEncoded;
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
        } elseif (isset($_POST['_method'])) {
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
}
