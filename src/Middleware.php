<?php
namespace Lipht\Mvc;

use Lipht\Exception;
use Lipht\InvalidArgumentException;
use Lipht\Module;
use Throwable;

class Middleware {
    public static function result() {
        $status = function($number) {
            $messages = [
                '200' => 'OK',
                '400' => 'Bad Request',
                '500' => 'Internal Server Error',
            ];

            self::header("HTTP/1.1 $number {$messages[$number]}");
        };

        return function($callback, $args) use($status) {
            try {
                $status('200');
                $result = call_user_func($callback, $args);
            } catch (InvalidArgumentException $e) {
                $status('400');
                $result = [
                    'error' => get_class($e),
                    'message' => $e->getMessage(),
                    'extra' => $e->getExtraData(),
                ];
            } catch (Exception $e) {
                $status('500');
                $result = [
                    'error' => get_class($e),
                    'message' => $e->getMessage(),
                    'extra' => $e->getExtraData(),
                ];
            } catch (\InvalidArgumentException $e) {
                $status('400');
                $result = [
                    'error' => get_class($e),
                    'message' => $e->getMessage(),
                ];
            } catch (Throwable $e) {
                $status('500');
                $result = [
                    'error' => get_class($e),
                    'message' => $e->getMessage(),
                ];
            }

            if (is_array($result) || is_object($result)) {
                self::header('Content-Type: application/json; charset=utf-8');
                echo json_encode($result);
                return $result;
            }

            if (file_exists($result)) {
                call_user_func(function($resultViewFilename, $args) {
                    include($resultViewFilename);
                }, $result, $args);
                return $result;
            }

            echo $result;
            return $result;
        };
    }

    public static function module(Module $module) {
        return function($callback, $args) use($module) {
            return $module->inject($callback, [$args]);
        };
    }

    public static function cors($origin = '*') {
        return function($callback, $args) use($origin) {
            header("Access-Control-Allow-Origin: $origin");
            return call_user_func($callback, $args);
        };
    }

    private static function header($string) {
        if (php_sapi_name() === 'cli') {
            return;
        }

        header($string);
    }
}
