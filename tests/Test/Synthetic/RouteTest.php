<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)
// (c) MArc J. Schmidt (https://marcjschmidt.de)

namespace Test\Synthetic;

use Abigail\Kernel\Response;
use Abigail\Server;
use PHPUnit\Framework\TestCase;
use ReflectionException;

class RouteTest extends TestCase
{

    /**
     * @throws ReflectionException
     */
    public function testAllRoutesClosures(): void
    {
        $abigail = Server::create('/')
            ->setClient('Abigail\\InternalClient')
            ->addGetRoute('test', function () {
                return 'getTest';
            })
            ->addPostRoute('test', function () {
                return 'postTest';
            })
            ->addPatchRoute('test', function () {
                return 'patchTest';
            })
            ->addPutRoute('test', function () {
                return 'putTest';
            })
            ->addOptionsRoute('test', function () {
                return 'optionsTest';
            })
            ->addDeleteRoute('test', function () {
                return 'deleteTest';
            })
            ->addHeadRoute('test', function () {
                return 'headTest';
            })
            ->addRoute('all-test', function () {
                return 'allTest';
            });

        foreach (Response::METHODS as $method) {
            $response = $abigail->simulateCall('/test', $method);
            $this->assertEquals(["status" => 200, "data" => "{$method}Test"], json_decode($response, true));

            $response = $abigail->simulateCall('/all-test', $method);
            $this->assertEquals(["status" => 200, "data" => "allTest"], json_decode($response, true));
        }
    }
}
