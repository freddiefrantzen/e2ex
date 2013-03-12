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


require_once __DIR__ .'/GlobalFunctionMocker.php';
require_once __DIR__ . '/../../vendor/autoload.php';


function exceptionHandlerFunction(\Exception $exception)
{
    throw $exception;
}


class ConverterTest extends \PHPUnit_Framework_TestCase
{
    public function exceptionHandler(\Exception $exception)
    {
        throw $exception;
    }


    public static function staticExceptionHandler(\Exception $exception)
    {
        throw $exception;
    }


    public function setUp()
    {      
        GlobalFunctionMocker::$set_error_handler          = true;
        GlobalFunctionMocker::$register_shutdown_function = true;
        GlobalFunctionMocker::$error_get_last             = true;
        GlobalFunctionMocker::$set_exception_handler      = true;
        GlobalFunctionMocker::$restore_exception_handler  = true;
        GlobalFunctionMocker::$debug_backtrace            = false;

        GlobalFunctionMocker::$lastError = null;
        GlobalFunctionMocker::$exceptionHandler = array(
            'E2EX\ConverterTest',
            'exceptionHandler',
        );
    }


    public function test_Bitmask_Should_Match_Current_Reporting_Level_If_Not_Set_In_Constructor()
    {
        $converter = Converter::register();
        $prop = new \ReflectionProperty('E2EX\Converter', 'bitmask');
        $prop->setAccessible(true);
        $this->assertEquals(error_reporting(), $prop->getValue($converter));
    }


    public function test_Setting_Bitmask_In_Constructor_Should_Update_Bitmask_Value()
    {
        $converter = Converter::register(E_ERROR | E_PARSE | E_USER_ERROR | E_RECOVERABLE_ERROR);
        $prop = new \ReflectionProperty('E2EX\Converter', 'bitmask');
        $prop->setAccessible(true);
        $this->assertEquals(4357, $prop->getValue($converter));
    }


    /**
     * @expectedException InvalidArgumentException
     */
    public function test_Passing_Wrong_Type_For_Bitmask_Arg_Should_Raise_Exception()
    {
        $converter = Converter::register('some sting');
    }


    public function test_Setting_LogExceptions_In_Constructor_Should_Update_LogExceptions()
    {
        $converter = Converter::register(E_ALL, false);
        $prop = new \ReflectionProperty('E2EX\Converter', 'logExceptions');
        $prop->setAccessible(true);
        $this->assertFalse($prop->getValue($converter));
    }


    public function test_Setting_StackTraceLimit_In_Constructor_Should_Update_StackTraceLimit()
    {
        $converter = Converter::register(E_ALL, true, 10);
        $prop = new \ReflectionProperty('E2EX\Converter', 'stackTraceLimit');
        $prop->setAccessible(true);
        $this->assertEquals(10, $prop->getValue($converter));
    }


    /**
     * @expectedException InvalidArgumentException
     */
    public function test_Passing_Wrong_Type_For_LogExceptions_Arg_Should_Raise_Exception()
    {
        $converter = Converter::register(E_ALL, true, new \stdClass());
    }


    public function test_Satrtup_Warning_Should_Be_Added_To_ErrorStack()
    {
        $lastError = array(
            'type'    => E_CORE_WARNING,
            'message' => 'Extension not accessible',
            'file'    => 'Unknown',
            'line'    => 4,
        );

        GlobalFunctionMocker::$lastError = $lastError;

        $converter = Converter::register(E_ALL);

        $prop = new \ReflectionProperty('E2EX\Converter', 'errorStack');
        $prop->setAccessible(true);
        $lastError['message'] = "PHP Startup Warning: {$lastError['message']} in Unknown on line 4";
        $errorStack = $prop->getValue($converter);

        if (empty($errorStack)) {
                $this->fail('The errorStack is empty');
            }

        if ( isset($errorStack[0]['stack']) ) {
            unset($errorStack[0]['stack']);
        }

        $this->assertEquals($lastError, $errorStack[0]);
    }


    public function test_Error_Handler_Should_Convert_Error_To_ErrorException_And_Add_To_ErrorStack()
    {   
        $converter = Converter::register(E_ALL);

        try {
            $converter->errorHandler(E_USER_ERROR, 'Ooops :(', '/path/to/offending/file', 101, array());
        } catch (ErrorException $exception) {
            $expected = array(
                'type'    => E_USER_ERROR,
                'message' => 'User-generated Error: Ooops :( in /path/to/offending/file on line 101',
                'file'    => '/path/to/offending/file',
                'line'    => 101,
            );
            $actual = array(
                'type'    => $exception->getSeverity(),
                'message' => $exception->getMessage(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
            );

            $this->assertSame($expected, $actual);

            $prop = new \ReflectionProperty('E2EX\Converter', 'errorStack');
            $prop->setAccessible(true);

            $errorStack = $prop->getValue($converter);

            if (empty($errorStack)) {
                $this->fail('The errorStack is empty');
            }

            if ( isset($errorStack[0]['stack']) ) {
                unset($errorStack[0]['stack']);
            }
            
            $this->assertEquals($expected, $errorStack[0]);

            return;
        }

        $this->fail('An ErrorException has not been raised.');

    }


    public function test_Error_Handler_Should_Add_Notice_To_ErrorStack()
    {
        $prop = new \ReflectionProperty('E2EX\Converter', 'errorStack');
        $prop->setAccessible(true);

        $converter = Converter::register(E_ALL);

        $this->assertEmpty($prop->getValue($converter));

        try {
            $this->assertFalse($converter->errorHandler(E_NOTICE, 'Something not quite right here', '/path/to/file', 200, array()));

            $expected = array(
                'type'    => E_NOTICE,
                'message' => 'Runtime Notice: Something not quite right here in /path/to/file on line 200',
                'file'    => '/path/to/file',
                'line'    => 200,
            );

            $errorStack = $prop->getValue($converter);

            if (empty($errorStack)) {
                $this->fail('The errorStack is empty');
            }

            if ( isset($errorStack[0]['stack']) ) {
                unset($errorStack[0]['stack']);
            }

            $this->assertEquals($expected, $errorStack[0]);

        } catch (ErrorException $notExpected) {
            $this->fail('An ErrorException has been raised.');
        }
    }


    public function test_ErrorException_Should_Not_Be_Added_To_ErrorStack_If_LogExceptions_Disabled()
    {
        $prop = new \ReflectionProperty('E2EX\Converter', 'errorStack');
        $prop->setAccessible(true);

        $converter = Converter::register(E_ALL, false);
        try {
            $converter->errorHandler(E_RECOVERABLE_ERROR, '', '', 101, array());
        } catch (ErrorException $expected) {
            $this->assertEmpty($prop->getValue($converter));
            return;
        }
        
        $this->fail('An ErrorException has not been raised.');
    }


    public function test_Error_Handler_Should_Ignore_Unknown_Error()
    {
        $converter = Converter::register(E_ALL);
    
         try {
            $this->assertFalse($converter->errorHandler(5, 'Unknown Error Type', '', '101', array()));
        } catch (ErrorException $notExpected) {
            $this->fail('An ErrorException has been raised.');
        }

        $prop = new \ReflectionProperty('E2EX\Converter', 'errorStack');
        $prop->setAccessible(true);
        $this->assertEmpty($prop->getValue($converter));
    }


    public function test_Shutdown_Function_Should_Return_If_No_Error()
    {
        $converter = Converter::register(E_ALL);

        try {
            $this->assertNull($converter->shutdownFunction());
        } catch (FatalErrorException $notExpected) {
            $this->fail('A FatalErrorException has been raised.');
        }
    }


    public function test_Shutdown_Function_Should_Pass_Fatal_Error_To_Exception_Handler_And_Add_Error_To_ErrorStack()
    {
        $converter = Converter::register(E_ALL);

        GlobalFunctionMocker::$lastError = array(
            'type'    => E_ERROR,
            'message' => 'Oops! Something bad happened',
            'file'    => '/path/to/offending/file',
            'line'    => 2,
        );

        try {
            $converter->shutdownFunction();
        } catch (FatalErrorException $exception) {
            $expected = array(
                'type'    => E_ERROR,
                'message' => 'Fatal Runtime Error: Oops! Something bad happened in /path/to/offending/file on line 2',
                'file'    => '/path/to/offending/file',
                'line'    => 2,
            );
            $actual = array(
                'type'    => $exception->getSeverity(),
                'message' => $exception->getMessage(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
            );
            $this->assertSame($expected, $actual);

            $prop = new \ReflectionProperty('E2EX\Converter', 'errorStack');
            $prop->setAccessible(true);

            $errorStack = $prop->getValue($converter);

            if (empty($errorStack)) {
                $this->fail('The errorStack is empty');
            }

            if ( isset($errorStack[0]['stack']) ) {
                unset($errorStack[0]['stack']);
            }

            $this->assertEquals($expected, $errorStack[0]);

            return;
        }
 
        $this->fail('A FatalErrorException has not been raised.');
    }


    public function test_FatalErrorException_Should_Not_Be_Added_To_ErrorStack_If_LogExceptions_Disabled()
    {
        $prop = new \ReflectionProperty('E2EX\Converter', 'errorStack');
        $prop->setAccessible(true);

        GlobalFunctionMocker::$lastError = array(
            'type'    => E_ERROR,
            'message' => '',
            'file'    => '',
            'line'    => 2,
        );

        $converter = Converter::register(E_ALL, false);

        try {
            $converter->shutdownFunction();
        } catch (FatalErrorException $exception) {
            $this->assertEmpty($prop->getValue($converter));
            return;
        }
 
        $this->fail('A FatalErrorException has not been raised.');
    }


    public function test_Shutdown_Function_Should_Respect_Bitmask()
    {
        $converter = Converter::register(E_ERROR);

        GlobalFunctionMocker::$lastError = array(
            'type'    => E_PARSE,
            'message' => '',
            'file'    => '',
            'line'    => 2,
        );

        try {
            $this->assertNull($converter->shutdownFunction());
        } catch (FatalErrorException $notExpected) {
            $this->fail('A FatalErrorException has been raised.');
        }
    }


    public function test_Shutdown_Function_Should_Ignore_NonFatal_Error()
    {
        $converter = Converter::register(E_ALL);

        GlobalFunctionMocker::$lastError = array(
            'type'    => E_USER_ERROR,
            'message' => '',
            'file'    => '',
            'line'    => 2,
        );

        try {
            $this->assertNull($converter->shutdownFunction());
        } catch (FatalErrorException $notExpected) {
            $this->fail('A FatalErrorException has been raised.');
        }
    }


    public function test_Shutdown_Function_Should_Ignore_Unknown_Error()
    {
        $converter = Converter::register(E_ALL);

        GlobalFunctionMocker::$lastError = array(
            'type'    => 5,
            'message' => '',
            'file'    => '',
            'line'    => 2,
        );

        try {
            $this->assertNull($converter->shutdownFunction());
        } catch (FatalErrorException $notExpected) {
            $this->fail('A FatalErrorException has been raised.');
        }
    }


    public function test_Shutdown_Function_Should_Work_With_Static_Handler()
    {
        GlobalFunctionMocker::$exceptionHandler = array(
            'E2EX\ConverterTest',
            'staticExceptionHandler',
        );

        $converter = Converter::register(E_ALL);

        GlobalFunctionMocker::$lastError = array(
            'type'    => E_PARSE,
            'message' => '',
            'file'    => '',
            'line'    => 2,
        );

        try {
            $converter->shutdownFunction();
        } catch (FatalErrorException $expected) {
            return;
        }
 
        $this->fail('A FatalErrorException has not been raised.');
    }


    public function test_Shutdown_Function_Should_Work_With_Function_Handler()
    {
        GlobalFunctionMocker::$exceptionHandler = 'E2EX\exceptionHandlerFunction';

        $converter = Converter::register(E_ALL);

        GlobalFunctionMocker::$lastError = array(
            'type'    => E_PARSE,
            'message' => '',
            'file'    => '',
            'line'    => 2,
        );

        try {
            $converter->shutdownFunction();
        } catch (FatalErrorException $expected) {
            return;
        }
 
        $this->fail('A FatalErrorException has not been raised.');
    }


    public function test_Shutdown_Function_Should_Return_If_No_Handler_Defined()
    {
        GlobalFunctionMocker::$exceptionHandler = null;

        $converter = Converter::register(E_ALL);

        GlobalFunctionMocker::$lastError = array(
            'type'    => E_PARSE,
            'message' => '',
            'file'    => '',
            'line'    => 2,
        );

        try {
            $this->assertNull($converter->shutdownFunction());
        } catch (FatalErrorException $notExpected) {
            $this->fail('A FatalErrorException has been raised.');
        }
    }


    public function test_ErrorStack_Should_Be_Converted_To_Log_Entries_If_Logger_Set()
    {        
        $errorStack = array(
            array(
                'message' => 'Runtime Notice: PHP is complaining about something in /path/to/somefile on line 20',
                'type'    => E_NOTICE,
                'file'    => '/path/to/somefile',
                'line'    => 20,
            ),
            array(
                'message' => 'User-generated Warning: This is a user generated warning in /path/to/somefile on line 2',
                'type'    => E_USER_WARNING,
                'file'    => '/path/to/somefile',
                'line'    => 2,
            ),
            array(
                'message' => 'Deprecated Warning: Time to refactor in /path/to/somefile on line 17',
                'type'    => E_DEPRECATED,
                'file'    => '/path/to/somefile',
                'line'    => 17,
            ),
            array(
                'message' => 'User-generated Error: This is a user generated error in /path/to/somefile on line 17',
                'type'    => E_USER_ERROR,
                'file'    => '/path/to/somefile',
                'line'    => 17,
            ),
            array(
                'message' => 'Fatal Runtime Error: The end of the road, my friend in /path/to/somefile on line 17',
                'type'    => E_ERROR,
                'file'    => '/path/to/somefile',
                'line'    => 17,
            ),
        );

        $converter = Converter::register(E_ALL);

        $prop = new \ReflectionProperty('E2EX\Converter', 'errorStack');
        $prop->setAccessible(true);
        $prop->setValue($converter, $errorStack);
        
        $mockLogger = $this->getMock('Monolog\Logger', array('notice', 'warning', 'error', 'critical'), array('test'));

        $mockLogger->expects($this->at(0))
                   ->method('notice')
                   ->with($this->equalTo($errorStack[0]['message']), $this->isType('array'));;

        $mockLogger->expects($this->at(1))
                   ->method('warning')
                   ->with($this->equalTo($errorStack[1]['message']), $this->isType('array'));

        $mockLogger->expects($this->at(2))
                   ->method('warning')
                   ->with($this->equalTo($errorStack[2]['message']), $this->isType('array'));

        $mockLogger->expects($this->at(3))
                   ->method('error')
                   ->with($this->equalTo($errorStack[3]['message']), $this->isType('array'));

        $mockLogger->expects($this->at(4))
                   ->method('critical')
                   ->with($this->equalTo($errorStack[4]['message']), $this->isType('array'));

        $converter->setLogger($mockLogger);
        $converter->shutdownFunction();
    }


    public function test_A_Stack_Trace_Should_Be_Added_To_Records_Pushed_To_ErrorStack()
    {
        GlobalFunctionMocker::$debug_backtrace = true;
        GlobalFunctionMocker::$stackTrace = array(
            array(
                'file'     => '/E2EX/src/E2EX/Converter.php',
                'line'     => 292,
                'function' => 'pushToStack',
                'class'    => 'E2EX\Converter',
                'type'     => '->',
                'args'     => array(),
            ),
            array(
                'file'     => '/E2EX/src/E2EX/Converter.php',
                'line'     => 187,
                'function' => 'errorHandler',
                'class'    => 'E2EX\Converter',
                'type'     => '->',
                'args'     => array(),
            ),
            array(
                'file'     => '/path/to/file/that/triggerd/error.php',
                'line'     => 101,
                'function' => 'thisFunctionTriggeredTheError',
                'class'    => 'MyClass',
                'type'     => '->',
                'args'     => array(),
            ),
            array(
                'function' => 'someOtherFunction',
                'class'    => 'AnotherClass',
                'type'     => '::',
                'args'     => array(),
            ),
            array(
                'file'     => '/path/to/somefile.php',
                'line'     => 22,
                'function' => 'theTraceStringShouldBeTrunketedHere',
                'class'    => 'SomeOtherClass',
                'type'     => '->',
                'args'     => array(),
            ),
        );        

        $converter = Converter::register(E_ALL, true, 2);
        $converter->errorHandler(E_NOTICE, 'PHP is complaining about something', '/path/to/file/that/triggerd/error.php', 101, array());

        $prop = new \ReflectionProperty('E2EX\Converter', 'errorStack');
        $prop->setAccessible(true);

        $errorStack = $prop->getValue($converter);

        if (empty($errorStack)) {
            $this->fail('The errorStack is empty');
        }

        if ( !isset($errorStack[0]['stack']) ) {
            $this->fail('No stack trace present in error record');
        }

        $expected = array_slice(GlobalFunctionMocker::$stackTrace, 2, 2);

        $this->assertSame($expected, $errorStack[0]['stack']);
    }

}