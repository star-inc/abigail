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
    protected array $body_data = array();

    /**
     * List of possible methods.
     * @var array
     */
    public array $methods = array('get', 'post', 'put', 'delete', 'head', 'options', 'patch');

    /**
     * Read data from request body.
     *
     * @throws Exception
     */
    public function readDataFromBody()
    {
        $raw_content_type = $_SERVER["CONTENT_TYPE"] ?? "application/x-www-form-urlencoded";
        $raw_content_type_array = explode(";", $raw_content_type);
        if (isset($raw_content_type_array[0])) {
            $raw_content = file_get_contents("php://input");
            switch ($raw_content_type_array[0]) {
                case "application/json" :
                    $this->body_data = json_decode($raw_content, true) ?? [];
                    break;
                case "application/x-www-form-urlencoded" :
                    $this->body_data = self::form_decode($raw_content) ?? [];
                    break;
                default:
                    $this->body_data = [];
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
