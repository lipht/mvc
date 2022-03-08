<?php
namespace Lipht\Mvc;

class Header {
    const CLI_ENVIRONMENT = 'cli';

    /** @var string[] $cliHeaders */
    private static $cliHeaders = [];

    /**
     * @param string $header
     * @param bool $replace
     * @param int|null $http_response_code
     */
    public static function send($header, $replace = true, $http_response_code = 0) {
        if (PHP_SAPI === self::CLI_ENVIRONMENT) {
            self::$cliHeaders[] = $header;
            return;
        }

        header($header, $replace, $http_response_code);
    }

    /**
     * @return string[]
     */
    public static function getCliHeaders(): array
    {
        return self::$cliHeaders;
    }
}
