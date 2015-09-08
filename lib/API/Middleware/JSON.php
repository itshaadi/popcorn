<?php
namespace API\Middleware;

class JSON extends \Slim\Middleware
{
    public function __construct($root = '')
    {
        $this->root = $root;
    }
    
    public function call()
    {
        if (preg_match(
            '|^' . $this->root . '.*|',
            $this->app->request->getResourceUri()
        )) {

            // Force response headers to JSON
            $this->app->response()->header('Content-Type', 'application/json;charset=utf-8');

            $method = strtolower($this->app->request->getMethod());
            $mediaType = $this->app->request->getMediaType();

        }
        $this->next->call();
    }
}
