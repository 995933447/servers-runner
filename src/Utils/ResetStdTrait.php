<?php
namespace Bobby\ServersRunner\Utils;

trait ResetStdTrait
{
    public function resetStdout(string $stdoutFile)
    {
        if (is_resource(STDOUT) && get_resource_type(STDOUT) === 'stream') {
            fclose(STDOUT);
        }

        global $STDOUT;

        $STDOUT = null;

        if (!$STDOUT = fopen($stdoutFile, 'a')) {
            throw new \RuntimeException("Open reset stdout file:$stdoutFile failed.");
        }
    }

    public function resetStderr(string $stderrFile)
    {
        if (is_resource(STDERR) && get_resource_type(STDERR) === 'stream') {
            fclose(STDERR);
        }

        global $STDERR;

        $STDERR = null;

        if (!$STDERR = fopen($stderrFile, 'a')) {
            throw new \RuntimeException("Open reset stderr file:$stderrFile failed.");
        }
    }

    public function resetStdin(string $stdinFile)
    {
        if (is_resource(STDIN) && get_resource_type(STDIN) === 'stream') {
            fclose(STDIN);
        }

        global $STDIN;

        $STDIN = null;

        if (!$STDIN = fopen($stdinFile, 'a')) {
            throw new \RuntimeException("Open reset stdin file:$stdinFile failed.");
        }
    }
}