<?php
// Abigail - fork from marcj/php-rest-service
// License: MIT
// (c) 2021 Star Inc. (https://starinc.xyz)

namespace Test\Controller;

class MyRoutes
{
    /**
     * @return string
     */
    public function get(): string
    {
        return "root GET";
    }

    /**
     * @return string
     */
    public function post(): string
    {
        return "root POST";
    }

    /**
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function postLogin(string $username, string $password): bool
    {
        return $username == 'peter' && $password == 'pwd';
    }

    /**
     * @param string $server
     * @url stats/([0-9]+)
     * @url stats
     * @return string
     */
    public function getStats($server = '1'): string
    {
        return sprintf('Stats for %s', $server);
    }


    public function getMethodWithoutPhpDoc(): string
    {
        return 'hi';
    }

    /**
     * @url test/test
     *
     * @return string
     */
    public function getTest(): string
    {
        return 'test';
    }
}
