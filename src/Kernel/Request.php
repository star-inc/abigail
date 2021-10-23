<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
declare(strict_types=1);

namespace Abigail\Kernel;

use Exception;

class Request
{
    /**
     * The data fetch from request body.
     *
     * @var array
     */
    protected array $bodyData = array();

    /**
     * @param string $name
     * @return string|null
     */
    public function getData(string $name): ?string
    {
        return $this->bodyData[$name] ?? null;
    }

    /**
     * Read data from request body.
     *
     * @throws Exception
     */
    public function readDataFromBody()
    {
        $rawContentType = $_SERVER["CONTENT_TYPE"] ?? "application/x-www-form-urlencoded";
        $rawContentTypeArray = explode(";", $rawContentType);
        if (isset($rawContentTypeArray[0])) {
            $rawContent = file_get_contents("php://input");
            switch ($rawContentTypeArray[0]) {
                case "application/json" :
                    $this->bodyData = json_decode($rawContent, true) ?? array();
                    break;
                case "application/x-www-form-urlencoded" :
                    $this->bodyData = self::form_decode($rawContent) ?? array();
                    break;
                default:
                    $this->bodyData = array();
            }
        } else {
            throw new Exception("No the header content-type configured.");
        }
    }

    /**
     * @param $raw_content
     * @return mixed
     */
    private static function form_decode($raw_content)
    {
        parse_str($raw_content, $result);
        return $result;
    }
}
