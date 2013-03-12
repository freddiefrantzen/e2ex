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

use Psr\Log\LoggerInterface;

class Converter {

    const NOTICE  = 100;
    const WARNING = 200;
    const ERROR   = 300;
    const FATAL   = 400;


    /**
     * The various PHP error types.
     *  
     * @var array
     */
    private $types = array (
        E_ERROR             => array('Fatal Runtime Error',     self::FATAL),            
        E_WARNING           => array('Runtime Warning',         self::WARNING),
        E_PARSE             => array('Fatal Parse',             self::FATAL), 
        E_NOTICE            => array('Runtime Notice',          self::NOTICE),
        E_CORE_ERROR        => array('Fatal PHP Startup Error', self::FATAL),
        E_CORE_WARNING      => array('PHP Startup Warning',     self::WARNING),
        E_COMPILE_ERROR     => array('Fatal Compilation Error', self::FATAL),
        E_COMPILE_WARNING   => array('Compilation Warning',     self::WARNING),
        E_USER_ERROR        => array('User-generated Error',    self::ERROR),
        E_USER_WARNING      => array('User-generated Warning',  self::WARNING),
        E_USER_NOTICE       => array('User-generated Notice',   self::NOTICE),
        E_STRICT            => array('Runtime Notice (Strict)', self::NOTICE),
        E_RECOVERABLE_ERROR => array('Catchable Fatal Error',   self::ERROR),
        E_DEPRECATED        => array('Deprecated Warning',      self::WARNING),
        E_USER_DEPRECATED   => array('User Deprecated Warning', self::WARNING),
    );


    /**
     * Determines which error types should be handled by E2EX. Defaults to the 
     * bitmask returned from error_reporting(). Error types not set to be 
     * handled will be handled instead by PHP's built-in error handler, 
     * (so long as they fall within the error_reporting range).
     *  
     * @var int
     */
    private $bitmask = null;


    /**
     * The amount of memory to reserve for the shutdown function, gets initiated 
     * to 10240 bytes.
     * 
     * @var string
     */
    private $reservedMemory;


    /**
     * Stack of PHP Notices, Warnings and Errors generated during the current 
     * execution context.
     * 
     * @var array
     */
    private $errorStack = array();


    /**
     * Whether to log errors that raise Exceptions.
     * 
     * @var bool
     */
    private $logExceptions = true;


    /**
     * The maximum depth of stack trace to include in log entries. If set to 0 
     * the stack trace will be omitted entirely.
     * 
     * @var int
     */
    private $stackTraceLimit = 5;


    /**
     * The Logger
     * 
     * @var LoggerInterface
     */
    private static $logger;


    /**
     * Registers handlers for fatal and non-fatal errors and checks for startup
     * warnings.
     *
     * @param  int  $bitmask  Determines which error types should be converted 
     * to Exceptions. Defaults to the bitmask returned from error_reporting().
     * @param  bool $logExceptions  Whether to log errors that raise Exceptions
     * @param  int  $stackTraceLimit  The maximum depth of stack trace to 
     * include in log entries. If set to 0 the stack trace will be omitted entirely.
     * @return E2EX\Converter
     * @throws InvalidArgumentException if supplied argument is of wrong type
     */
    public static function register($bitmask = null, $logExceptions = true, $stackTraceLimit = 5)
    {
        $handler = new static();
        
        if ($bitmask === null) {
            $handler->bitmask = error_reporting();
        } elseif (is_int($bitmask)) {
            $handler->bitmask = $bitmask;
        } else {
            throw new \InvalidArgumentException('bitmask must be an integer');
        }

        if ($logExceptions === false) {
            $handler->logExceptions = false;
        }

        if (is_int($stackTraceLimit)) {
            $handler->stackTraceLimit = $stackTraceLimit;
        } else {
            throw new \InvalidArgumentException('stackTraceLimit must be an integer');
        }

        $error = error_get_last(); 

        if ($error !== null) {
            foreach($error as $field => $value ) { 
                $$field = $value; 
            }
            $level = $handler->types[$type][1];
            if ( ($handler->bitmask & $type) == $type 
                 && ($level === self::NOTICE || $level === self::WARNING) ) {
                    $message = $handler->formatMessage($message, $type, $file, $line);
                    $handler->pushToStack($message, $type, $file, $line);
            }
        }
        
        $handler->reservedMemory = str_repeat('x', 10240);

        ini_set('display_errors', 0);
        set_error_handler(array($handler, 'errorHandler'), $handler->bitmask);
        register_shutdown_function(array($handler, 'shutdownFunction'));

        return $handler;
    } 


    /**
     * Set the Logger to use. 
     * 
     * @param Psr\Log\LoggerInterface $logger The Logger
     */
    public static function setLogger(LoggerInterface $logger)
    {
        self::$logger = $logger;
    }


    /**
     * The handler for non-fatal errors. This will only be called for error 
     * types turned on in the bitmask. Any errors that aren't handled here will
     * be handled by PHP's built-in error handler. Errors that are handled here
     * won't get passed on to PHP's built-in handler unless no Logger has been
     * set.
     * 
     * @param  int    $type    Type of error raised
     * @param  string $message Error message
     * @param  string $file    Filename the error was raised in
     * @param  int    $line    Line number the error was raised at
     * @param  array  $context The active symbol table at the point the error occurred
     * @return bool
     * @throws E2EX\ErrorException If the error type corresponds to a non-fatal error and is set to be handled
     */
    public function errorHandler($type, $message, $file, $line, $context)
    {
        if ( array_key_exists($type, $this->types) ) {
            
            $message = $this->formatMessage($message, $type, $file, $line);

            if ($this->types[$type][1] !== self::ERROR || $this->logExceptions === true) {
                $this->pushToStack($message, $type, $file, $line);
            }
            
            if ($this->types[$type][1] === self::ERROR) {
                throw new ErrorException($message, 0, $type, $file, $line);
            }

            if (!isset($this->logger)) {
                return false; // allow PHP to log the notice or warning
            }

            return true; 
        }

        return false; 
    }


    /**
     * The handler for fatal errors, registered as a shutdown hook.
     *
     * If a Fatal error is detected, A FatalErrorException will be passed to the 
     * current Exception handler, if one exists. The error stack will be flushed 
     * to the Logger, if one has been set.
     *
     * @return null
     */
    public function shutdownFunction()
    {
        unset($this->reservedMemory);

        $error = error_get_last();
        if ($error === null || $this->bitmask === 0) {
            $this->flushErrors();
            return;
        }

        foreach($error as $field => $value ) { 
            $$field = $value; 
        }

        if (!array_key_exists($type, $this->types) 
            || $this->types[$type][1] !== self::FATAL
            || ($this->bitmask & $type) != $type) {
            $this->flushErrors();
            return;
        }        

        $message = $this->formatMessage($message, $type, $file, $line);

        if ( $this->logExceptions === true ) {
            $this->pushToStack($message, $type, $file, $line);
        }

        $this->flushErrors();
        
        $exception = new FatalErrorException($message, 0, $type, $file, $line);

        $ehInfo = set_exception_handler(function() {});
        restore_exception_handler();

        if (is_array($ehInfo) && is_callable(array($ehInfo[0], $ehInfo[1]))) {
            $class  = $ehInfo[0];
            $method = $ehInfo[1];
            $reflectionMethod = new \ReflectionMethod($class, $method);
            if ($reflectionMethod->isStatic()) {
                $class::$method($exception);
            } else {
                $exceptionHandler = new $class();
                $exceptionHandler->{$method}($exception);
            }
        } elseif (is_callable($ehInfo)) {
            call_user_func($ehInfo, $exception);
        } else {
            return;
        } 
    }


    protected function formatMessage($message, $type, $file, $line) 
    {
        return "{$this->types[$type][0]}: $message in $file on line $line";
    }


    protected function pushToStack($message, $type, $file, $line)
    {
        $record = array(
                    'message' => $message, 
                    'type'    => $type,
                    'file'    => $file, 
                    'line'    => $line,
                  );

        if ($this->stackTraceLimit > 0) {
            $record['stack'] = array_slice(debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS), 2, $this->stackTraceLimit);
        }

        $this->errorStack[] = $record;
    }


    protected function flushErrors()
    {
        if (isset(self::$logger) && count($this->errorStack) > 0) {
            
            foreach($this->errorStack as $record) {

                switch($this->types[$record['type']][1]) {
                    case self::NOTICE:
                        $method = 'notice';
                    break;
                    case self::WARNING:
                        $method = 'warning';
                    break;
                    case self::ERROR:
                        $method = 'error';
                    break;
                    case self::FATAL:
                        $method = 'critical';
                    break;
                }
                
                if(!isset($method)) {
                    continue;
                }
                
                $message = array_shift($record);
                self::$logger->{$method}($message, $record);      
            }
        }   
    }

}