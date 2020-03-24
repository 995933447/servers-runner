<?php
//pcntl_async_signals(true);
declare(ticks=1);
//$pid = pcntl_fork();

//if ($pid > 0) {
//    cli_set_process_title('parent');
//    pcntl_signal(SIGUSR1, function ($sigNo) {
//        echo "$sigNo\n";
//    });
//    pcntl_wait($status);
//} else {
//    sleep(60);
//}

//$fn = function ($sig) {
//    echo "receive $sig\n";
//};
//
//pcntl_signal(SIGUSR1, $fn);
//pcntl_signal(SIGUSR2, $fn);
//pcntl_signal(SIGINT, $fn);
//
//while (1) {
//    pcntl_signal_dispatch();
//}

var_dump(feof(STDIN));

fclose(STDIN);

var_dump(is_resource(STDIN), get_resource_type(STDIN));
//var_dump(feof(STDIN));