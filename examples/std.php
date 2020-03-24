<?php
//$file = '/var/www/std.log';
//global $STDOUT;
//if (is_resource(STDOUT) && get_resource_type(STDOUT) === 'stream') {
//    echo "close\n";
//    fclose(STDOUT);
//}
//$STDOUT = fopen($file, "a");


function resetStdout(string $stdoutFile)
{
//    if (!$fp = fopen($stdoutFile, 'a')) {
//        throw new \RuntimeException("Open reset stdout file:$stdoutFile failed.");
//    }

    if (is_resource(STDOUT) && get_resource_type(STDOUT) === 'stream') {
        fclose(STDOUT);
    }

    global $STDOUT;

    if (!$STDOUT = fopen($stdoutFile, 'a')) {
        throw new RuntimeException("Open reset stdout file:$stdoutFile failed.");
    }
}

resetStdout('/var/www/std.log');
echo "hello\n";