E2EX - Semantic PHP Error handling
==================================

A simple library for converting PHP Errors to Exceptions and (optionally) for logging PHP Notices, Warnings and Errors with a PSR-3 compatible logger, such as [monolog](https://github.com/Seldaek/monolog). Enables the unification of application and PHP logs and provides a means to harmonise Error and Exception handling.


Installation with Composer
--------------------------

Add the following line to your [composer.json](http://getcomposer.org/doc/00-intro.md#declaring-dependencies) file:
    
    require: "e2ex/e2ex": "0.8.*"

Install:

    $ composer.phar update e2ex/e2ex

E2EX should play nice with any PSR-0 autoloader.


Basic Usage
-----------

Set E2EX to handle all Errors, Warnings and Notices:

```php
E2EX\Converter::register(E_ALL);
```

Alternatively, you can pass in a [bitmask](http://php.net/manual/en/errorfunc.constants.php) to set which types should be handled.

```php
E2EX\Converter::register(E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE | E_USER_DEPRECIATED);
```

Calling register() with no arguments will result in the bitmask being set to the current return value of [error_reporting()](http://php.net/manual/en/function.error-reporting.php).

Any error types that aren't set to be handled by E2EX will be handled by PHP's built-in error handler, (so long as they fall within the error_reporting range). 


Handling Exceptions
-------------------

Non-fatal Errors will raise an `E2EX\ErrorException`. These can be handled inside try/catch blocks or with a registered [exception handler](http://php.net/manual/en/function.set-exception-handler.php), which can be a static/instance class method, or a global/namespaced function. Non-fatal error types include `E_USER_ERROR` and `E_RECOVERABLE_ERROR`. 

Fatal (non-recoverable) Errors will raise an `E2EX\FatalErrorException`. These can only be handled inside your registered exception handler. Fatal error types include `E_ERROR`, `E_PARSE` and `E_COMPILE_ERROR`.

Both of the Exception types extend PHPs native [\ErrorException](http://php.net/manual/en/class.errorexception.php).


PSR-3 Logging
------------

To enable [PSR-3](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md) logging, all that's required is to pass in a PSR-3 compatible logger instance:

```php
E2EX\Converter::seLogger($logger);
```

- `E_NOTICE`, `E_USER_NOTICE` and `E_STRICT` will be logged at the `notice` level. 

- `E_WARNING`, `E_CORE_WARNING`, `E_DEPRECATED`, `E_USER_DEPRECATED` , `E_COMPILE_WARNING` and `E_USER_WARNING`  will be logged at the `warning` level.
 
- `E_USER_ERROR` and `E_RECOVERABLE_ERROR` will be logged at the `error` level.
 
- `E_ERROR`, `E_COMPILE_ERROR` and `E_PARSE` will be logged at the `critical` level.

If you only want to log Notices and Warnings, pass in boolean `false` as the second argument when you register the handler.

```php
E2EX\Convertor::register(E_ALL, false); // ErrorExceptions and FatalErrorExceptions will not be passed to the Logger
```

A context array will be added to each log entry containing the PHP error type (as an integer), the filename, the line number and (optionally) the stack trace, as an indexed array of associative arrays. By default, the stack trace will be limited to a depth of 5. You can vary this by passing a 3rd paramater to the `register()` method, like so:

```php
E2EX\Convertor::register(E_ALL, false, 10);
```

If you don't want to log stack traces, simply pass in 0 as the 3rd argument.

Note that once PSR-3 logging has been enabled, non-fatal Errors, Notices and Warnings set to be handled by E2EX will not be handled by PHP's built in error handler, and so will not be logged to PHP's error_log. 


Caveats
-------

  - Fatal startup errors (`E_CORE_ERROR`) will not be handled.

  - If more than one startup warning (`E_CORE_WARNING` and/or `E_DEPRECATED`) occurs, only the last one will be passed to the logger.  

  - `E_ERROR`, `E_COMPILE_ERROR` and `E_PARSE` errors will not be converted to Exceptions if they occur in the same file as the handler is registered in. 
  
  - `E_ERROR`, `E_PARSE`, `E_CORE_ERROR`, `E_CORE_WARNING`, `E_COMPILE_ERROR`, `E_COMPILE_WARNING`, and most of `E_STRICT` will not be passed to the logger if raised in the same file the handler is registered in. They will however be logged with PHP's built-in error handler if error_log is set in php.ini.


Running the Tests
-----------------

[Install dev dependencies](http://getcomposer.org/doc/04-schema.md#require-dev) (if not already installed).

Run tests:

    $ phpunit tests


License
-------

MIT