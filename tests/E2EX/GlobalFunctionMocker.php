<?php

/*
 * This file is part of the E2EX package.
 *
 * (c) Freddie Frantzen <freddie@freddiefrantzen.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace E2EX;


function set_error_handler(callable $error_handler, $error_types = E_ALL) 
{
    if (GlobalFunctionMocker::$set_error_handler === true) {
        return false; 
    } else {
        return call_user_func_array('\set_error_handler', func_get_args());
    }
}


function register_shutdown_function(callable $callback) 
{
    if (GlobalFunctionMocker::$register_shutdown_function === true) {
        return false;
    } else {
        return call_user_func_array('\register_shutdown_function', func_get_args());
    }
}


function error_get_last() 
{
    if (GlobalFunctionMocker::$error_get_last === true) {
        return GlobalFunctionMocker::$lastError;
    } else {
        return call_user_func_array('\error_get_last', func_get_args());
    }
}


function set_exception_handler(callable $exception_handler) 
{
    if (GlobalFunctionMocker::$set_exception_handler === true) {
        return GlobalFunctionMocker::$exceptionHandler;  
    } else {
        return call_user_func_array('\set_exception_handler', func_get_args());
    }
}


function restore_exception_handler() 
{
    if (GlobalFunctionMocker::$restore_exception_handler === true) {
        return false;
    } else {
        return call_user_func_array('\restore_exception_handler', func_get_args());
    }
}


function debug_backtrace($options = DEBUG_BACKTRACE_PROVIDE_OBJECT) 
{
    if (GlobalFunctionMocker::$debug_backtrace === true) {
        return GlobalFunctionMocker::$stackTrace;  
    } else {
        return call_user_func_array('\debug_backtrace', func_get_args());
    }
}


class GlobalFunctionMocker
{
    public static $set_error_handler          = false;
    public static $register_shutdown_function = false;
    public static $error_get_last             = false;
    public static $set_exception_handler      = false;
    public static $restore_exception_handler  = false;
    public static $debug_backtrace            = false;

    public static $lastError                  = null;
    public static $exceptionHandler           = null;
    public static $stackTrace                 = null;
}