```
<?php
require __DIR__ . "/../vendor/autoload.php";

// 配置master进程
$serversRunnerConfig = new \Bobby\ServersRunner\ServersRunnerConfig();
$serversRunnerConfig->setPidFile('/var/www/servers-runner.pid');
//$serversRunnerConfig->setDaemonize(true);
$serversRunnerConfig->setStdinFile('/var/www/stdin.log');
$serversRunnerConfig->setStdoutFile('/var/www/stdout.log');
$serversRunnerConfig->setStderrFile('/var/www/stderr.log');
$serversRunner = new \Bobby\ServersRunner\ServersRunner($serversRunnerConfig);

$eventLoop = \Bobby\StreamEventLoop\LoopFactory::make();

// 配置http 服务worker进程
$httpServeSocket = new \Bobby\Servers\Socket("0.0.0.0:9501");
$httpServerConfig = new \Bobby\Servers\ServerConfig();
$httpServer = new \Bobby\Servers\Http\Server($httpServeSocket, $httpServerConfig, $eventLoop);

$httpServer->on(\Bobby\Servers\Http\Server::REQUEST_EVENT, function (
    \Bobby\Servers\Http\Server $server, \Bobby\ServerNetworkProtocol\Http\Request $request, \Bobby\Servers\Http\Response $response
) {
    var_dump($request->request);
    $response->end("Hi Http client.\n");
});

$httpServerWorkerConfig = new \Bobby\ServersRunner\ServerWorkerConfig();
$httpServerWorkerConfig->setWorkerNum(10);
$httpServerWorkerConfig->setName('Http server');
$httpServerWorkerConfig->setGroup('root');
$httpServerWorkerConfig->setUser('bp');

$httpServerWorker = $serversRunner->addServerWorker($httpServer, $httpServerWorkerConfig);

$httpServerWorker->on(\Bobby\ServersRunner\ServerWorker::WORKER_START_EVENT, function () {
    echo "Http server worker start\n";
});

$httpServerWorker->on(\Bobby\ServersRunner\ServerWorker::WORKER_STOP_EVENT, function () {
    echo "Http server worker stop.\n";
});

// 配置tcp服务worker进程
$tcpServeSocket = new \Bobby\Servers\Socket('0.0.0.0:9502');
$tcpServerConfig = new \Bobby\Servers\ServerConfig();
$tcpServer = new \Bobby\Servers\Tcp\Server($tcpServeSocket, $tcpServerConfig, $eventLoop);

$tcpServer->on(\Bobby\Servers\Tcp\Server::CONNECT_EVENT, function (
    \Bobby\Servers\Tcp\Server $server, \Bobby\Servers\Contracts\ConnectionContract $connection
) {
    echo 'Socket ' . (int)$connection->exportStream() . ' connected.', PHP_EOL;
});

$tcpServer->on(\Bobby\Servers\Tcp\Server::RECEIVE_EVENT, function (
    \Bobby\Servers\Tcp\Server $server, \Bobby\Servers\Contracts\ConnectionContract $connection, $data
) {
    echo "Receive message:$data", PHP_EOL;
    $server->send($connection, "HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nHi");
    $server->close($connection);
});

$tcpServer->on(\Bobby\Servers\Tcp\Server::CLOSE_EVENT, function (
    \Bobby\Servers\Tcp\Server $server, \Bobby\Servers\Contracts\ConnectionContract $connection
) {
    echo 'Socket ' . (int)$connection->exportStream() . ' is closed.', PHP_EOL;
});

$tcpServer->on(\Bobby\Servers\Tcp\Server::ERROR_EVENT, function (
    \Bobby\Servers\Tcp\Server $server, \Bobby\Servers\Contracts\ConnectionContract $connection, Throwable $exception
) {
    echo $exception->getTraceAsString(), PHP_EOL;
    die;
});

$tcpServerWorkerConfig = new \Bobby\ServersRunner\ServerWorkerConfig();
$tcpServerWorkerConfig->setWorkerNum(5);
$tcpServerWorkerConfig->setName('Tcp server');
$tcpServerWorkerConfig->setGroup('root');
$tcpServerWorkerConfig->setUser('bp');

$tcpServerWorker = $serversRunner->addServerWorker($tcpServer, $tcpServerWorkerConfig);

$tcpServerWorker->on(\Bobby\ServersRunner\ServerWorker::WORKER_START_EVENT, function () {
    echo "TCP server worker start.\n";
});

$tcpServerWorker->on(\Bobby\ServersRunner\ServerWorker::WORKER_STOP_EVENT, function () {
    echo "TCP server worker stop.\n";
});

$serversRunner->on(\Bobby\ServersRunner\ServersRunner::START_EVENT, function () {
    echo "Master process start.\n";
});

$serversRunner->on(\Bobby\ServersRunner\ServersRunner::STOP_EVENT, function () {
    echo "Master process exit.\n";
});

$serversRunner->run();

```