<?php
namespace Swango\Rpc;
use Swango\Environment;
class Server extends \Swango\HttpServer {
    public function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response): void {
        ++self::$worker->worker_http_request_counter;
        $count = self::$http_request_counter->add();
        $request_time_float = $request->server['request_time_float'];
        $request_time = (int)$request_time_float;
        $client_ip = $request->server['remote_addr'];
        $client_ip_int = ip2long($client_ip);
        $local_ip_right = ip2long(Environment::getServiceConfig()->local_ip) & 0xFFFF;
        $request_id = sprintf('%08x-%04x-4%03x-%x%03x-%07x%05x', $client_ip_int, $local_ip_right, mt_rand(0, 0xFFF),
            mt_rand(8, 0xB), mt_rand(0, 0xFFF), $request_time >> 4, $count & 0xFFFFF);
        \SysContext::set('request_id', $request_id);
        $response->header('X-Request-ID', $request_id);
        $micro_second = substr(sprintf('%.3f', $request_time_float - $request_time), 2);
        $request_string = date("[H:i:s.$micro_second]", $request_time) . self::$worker_id . "-{$count} " . $client_ip .
            ' ' . $request->server['request_method'] . ' ' . ($request->header['host'] ?? '') .
            $request->server['request_uri'] .
            (isset($request->server['query_string']) ? '?' . $request->server['query_string'] : '');
        if (self::$terminal_server->getRequestLogSwitchStatus(1)) {
            self::$terminal_server->send($request_string, 1);
        }
        $user_id = null;
        try {
            [
                $code,
                $enmsg,
                $cnmsg
            ] = Server\Handler::start($request, $response);
        } catch (\Swoole\ExitException $e) {
            trigger_error("Unexpected exit:{$e->getCode()} {$e->getMessage()}");
        } catch (\Throwable $e) {
            trigger_error("Unexpected throwable:{$e->getCode()} {$e->getMessage()} {$e->getTraceAsString()}");
        }
        Server\Handler::end();
        --self::$worker->worker_http_request_counter;
        $end_time = microtime(true);
        $response_string = sprintf("(%s) %.3fms [$code]$enmsg", \session::getId(),
            ($end_time - $request_time_float) * 1000);
        if ($code !== 200 || $enmsg !== 'ok') {
            $response_string .= ' ' . $cnmsg;
        }
        if (self::$terminal_server->getRequestLogSwitchStatus(2)) {
            self::$terminal_server->send($request_string . ' ==> ' . $response_string, 2);
        }
    }
    protected function onWorkerStart(\Swoole\Server $serv, int $worker_id): void {
        parent::onWorkerStart($serv, $worker_id);
        if ($worker_id === 0) {
            go('\\Swango\\Aliyun\\Slb\\Scene\\MakeLocalServerRunOnBalancer::make');
        }
    }
    public function stop(): bool {
        go('\\Swango\\Aliyun\\Slb\\Scene\\ShutdownLocalServerFromVServerGroup::shutdown');
        return parent::stop();
    }
}