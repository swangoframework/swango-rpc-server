<?php
namespace Swango\Rpc\Server;
class Router extends \Swango\HttpServer\Router {
    public static function getInstance(?\Swoole\Http\Request $request = null):  \Swango\HttpServer\Router {
        $ob = \SysContext::get('router');
        if (isset($ob))
            return $ob;

        if (! isset($request))
            throw new \Exception('Need to give \\Swoole\\Http\\Request');

        $v = isset($request->get->v) && is_numeric($request->get->v) && $request->get->v > 1 && $request->get->v < 100 ? (int)$request->get->v : null;
        $inputcontent = $request->rawContent();
        $body = \Json::decodeAsObject($inputcontent);

        if (! isset($body->m) || ! is_string($body->m) || ! isset($body->v) || ! is_int($body->v) || $body->v < 1 ||
             $body->v > 100)
            throw new \ExceptionToResponse\BadRequestException();
        $v = $body->v;
        if ($v === 1)
            $v = null;
        $url = $body->m;
        $request->post = $body->p ?? new \stdClass();

        if (preg_match("/\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\;|\'|\"|\`|\=|\\\|\|/", $url))
            throw new \ExceptionToResponse\BadRequestException();

        $ob = new self($url, 'POST', $v);
        $ob->request = $request;
        $ob->host = $request->header['host'] ?? null;
        \SysContext::set('router', $ob);
        return $ob;
    }
}