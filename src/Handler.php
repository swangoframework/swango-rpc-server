<?php
namespace Swango\Rpc\Server;
use Swango\HttpServer\Controller;
class Handler extends \Swango\HttpServer\Handler {
    public static function start(\Swoole\Http\Request $request, \Swoole\Http\Response $response): array {
        $method = strtoupper($request->server['request_method']);
        if ('HEAD' === $method) {
            $response->status(200);
            return [
                200,
                'ok',
                ''
            ];
        } elseif ('POST' !== $method) {
            $response->status(405);
            return [
                405,
                'Method not allowed',
                '只支持POST方法'
            ];
        }
        if (! isset($request->get)) {
            \SysContext::set('request_get', new \stdClass());
        } else {
            \SysContext::set('request_get', (object)$request->get);
        }
        try {
            $router = Router::getInstance($request);
            $controller = $router->getController($response);
            \cache::select(1);
            $controller->validate()->begin()->jsonResponse();
            return [
                $controller->json_response_code,
                $controller->json_response_enmsg,
                $controller->json_response_cnmsg
            ];
        } catch (\ExceptionToResponse $e) {
            $code = $e->getCode();
            $enmsg = $e->getMessage();
            $cnmsg = $e->getCnMsg();
            $data = $e->getData();
        } catch (\ApiErrorException $e) {
            $code = 500;
            $enmsg = 'Third party service error';
            $cnmsg = $e::supplier . '发生错误，有可能正在维护';
            $data = null;
        } catch (\RuntimeException $e) {
            $code = 500;
            $enmsg = 'Unexpected system error';
            $cnmsg = '此服务维护中，暂时不可用';
            $data = null;
        } catch (\Exception $e) {
            $code = method_exists($e, 'getSwangoCode') ? $e->getSwangoCode() : 500;
            $enmsg = 'Unexpected system error';
            $data = null;
            // 死锁
            if ($e instanceof \Swango\Db\Exception\QueryErrorException && $e->errno === 1213) {
                $cnmsg = '当前使用该服务的人数较多，请稍后重试';
            } elseif (method_exists($e, 'getSwangoCnMsg')) {
                $enmsg = $e->getMessage();
                $cnmsg = $e->getSwangoCnMsg();
            } else {
                $cnmsg = '服务器开小差了，请稍后重试';
            }
        } catch (\Throwable $e) {
            $code = 500;
            $enmsg = 'System fatal error';
            $cnmsg = '服务器出现内部错误，请稍后重试';
            $data = null;
        }
        if (isset($controller)) {
            $controller->jsonResponse($data, $enmsg, $cnmsg, $code);
        }
        \FileLog::logThrowable($e, \Swango\Environment::getDir()->log . 'error/',
            sprintf('%s : %s | %s | %s | %s | ', $request->header['x-forwarded-for'] ?? $request->server['remote_addr'],
                $e->getMessage(), $cnmsg, ($request->header['host'] ?? '') . $request->server['request_uri'] .
                (isset($request->server['query_string']) ? '?' . $request->server['query_string'] : ''),
                \SysContext::has('request_post') ? json_encode(\SysContext::get('request_post')) : ''));
        if (isset($controller)) {
            $controller->rollbackTransaction();
        }
        return [
            $code,
            $enmsg,
            $cnmsg
        ];
    }
    public static function end() {
        if (Router::exists()) {
            Router::getInstance()->detachSwooleRequest();
        }
        $controller = Controller::getInstance(false);
        if (isset($controller)) {
            $controller->detachSwooleObject();
        }
    }
}