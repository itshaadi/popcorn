<?php
$app->notFound(function () use ($app) {
    $req = $app->request();
    $mediaType = $app->request->getMediaType();
     
    $isAPI = (bool) preg_match('|^/v.*$|', $app->request->getPath());
 
 
    if ('application/json' === $mediaType || true === $isAPI) {
 
        $app->response->headers->set(
            'Content-Type',
            'application/json;charset=utf-8'
        );

		echo json_encode(
			array(
				'not_found' => 'Not found',
				'message' => 'The resource you requested could not be found.'
			)
		);
 
    } else {
        echo '<html>
        <head><title>404 Page Not Found</title></head>
        <body><h1>404 Page Not Found</h1><p>The page you are 
        looking for could not be found.</p></body></html>';
    }
});