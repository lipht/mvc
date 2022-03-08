<?php
namespace Lipht\Mvc;

use Closure;
use Lipht\Exception;
use Lipht\InvalidArgumentException;
use Lipht\Module;
use Throwable;

class Middleware {
    /**
     * @return Closure
     */
    public static function result() {
        $status = function($number) {
            $messages = [
                '200' => 'OK',
                '400' => 'Bad Request',
                '500' => 'Internal Server Error',
            ];

            Header::send("HTTP/1.1 $number {$messages[$number]}");
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

            if (is_a($result, Template::class)) {
                $mime = $result->getMime();
                Header::send("Content-Type: {$mime}; charset=utf-8");
                echo $result->render();
                return $result;
            }

            if (is_array($result) || is_object($result)) {
                Header::send('Content-Type: application/json; charset=utf-8');
                echo json_encode($result);
                return $result;
            }

            if (!is_null($result) && file_exists($result)) {
                call_user_func(function($resultViewFilename, $args) {
                    include($resultViewFilename);
                }, $result, $args);
                return $result;
            }

            echo $result;
            return $result;
        };
    }

    /**
     * @param Module $module
     * @return Closure
     */
    public static function module(Module $module) {
        return function($callback, $args) use($module) {
            return $module->inject($callback, [$args]);
        };
    }

    /**
     * @param string $origin
     * @return Closure
     */
    public static function cors($origin = '*') {
        return function($callback, $args) use($origin) {
            Header::send("Access-Control-Allow-Origin: $origin");
            return call_user_func($callback, $args);
        };
    }
}
