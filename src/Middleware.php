<?php
namespace Lipht\Mvc;

use Lipht\Module;

class Middleware {
    public static function result() {
        return function($callback, $args) {
            $result = call_user_func($callback, $args);

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
}
