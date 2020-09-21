<?php
namespace Swango\Rpc\Server;
class Router extends \Swango\HttpServer\Router {
    public static function getInstance(?\Swoole\Http\Request $request = null): \Swango\HttpServer\Router {
        $ob = \SysContext::get('router');
        if (isset($ob)) {
            return $ob;
        }
        if (! isset($request)) {
            throw new \Exception('Need to give \\Swoole\\Http\\Request');
        }
        $body = \Json::decodeAsObject($request->rawContent());
        if (! isset($body->m) || ! is_string($body->m) || ! isset($body->v) || ! is_int($body->v) || $body->v < 1 ||
            $body->v > 100) {
            throw new \ExceptionToResponse\BadRequestException();
        }
        $v = $body->v;
        if (1 === $v) {
            $v = null;
        }
        \SysContext::set('request_post', $body->p ?? new \stdClass());
        if (preg_match("/\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\;|\'|\"|\`|\=|\\\|\|/",
            $body->m)) {
            throw new \ExceptionToResponse\BadRequestException();
        }
        $ob = new self($body->m, 'POST', $v);
        $ob->request = $request;
        $ob->host = $request->header['host'] ?? null;
        \SysContext::set('router', $ob);
        return $ob;
    }
}