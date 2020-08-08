<?php

// warnings as errors during tests
// https://stackoverflow.com/questions/10520390/stop-script-execution-upon-notice-warning

function errHandle($errNo, $errStr, $errFile, $errLine) {
    $msg = "$errStr in $errFile on line $errLine";
    if ($errNo == E_NOTICE || $errNo == E_WARNING) {
        throw new ErrorException($msg, $errNo);
    } else {
        echo $msg;
    }
}

set_error_handler('errHandle');

$test_count = 0;
$data = null;

function test( $result, $expected_result, $error_message )
{
    global $test_count, $data;
    if ($result !== $expected_result)
    {
        // json_encode will nicely format almost anything
        throw new Exception(
            "Test failure with ".json_encode($result).
            " expected ".json_encode($expected_result)."\n".
            $error_message."\n"
        );
    }
    $test_count++;
}

?>