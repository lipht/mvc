<?php
namespace Lipht\Mvc;

use Lipht\Module;

class Middleware {
    public static function result() {
        return function($callback, $args) {
            $result = "";

            try {
                $result = call_user_func($callback, $args);
            } catch (\InvalidArgumentException $e) {
                header('HTTP/1.1 400 Bad Request');
                $result = [
                    'error' => get_class($e),
                    'message' => $e->getMessage(),
                ];
            } catch (\Exception $e) {
                header('HTTP/1.1 500 Internal Server Error');
                $result = [
                    'error' => get_class($e),
                    'message' => $e->getMessage(),
                ];
            }

            if (is_array($result) || is_object($result)) {
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
        return function($callback, $args) {
            header("Access-Control-Allow-Origin: $origin");
            return call_user_func($callback, $args);
        };
    }
}
