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
    if (is_resource(STDOUT) && get_resource_type(STDOUT) === 'stream') {
        fclose(STDOUT);
    }

    global $STDOUT;

    $STDOUT = null;

    if (!$STDOUT = fopen($stdoutFile, 'a')) {
        throw new RuntimeException("Open reset stdout file:$stdoutFile failed.");
    }
}

resetStdout('/var/www/std2.log');
echo "hello\n";
resetStdout('/var/www/std2.log');
echo "world\n";