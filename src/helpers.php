<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

/**
 * Редирект через urlFor + withHeader (Slim 4).
 */
function redirectTo(
    ResponseInterface $response,
    ServerRequestInterface $request,
    string $routeName,
    array $params = []
): ResponseInterface {
    $routeParser = RouteContext::fromRequest($request)->getRouteParser();
    $url = $routeParser->urlFor($routeName, $params);

    return $response->withHeader('Location', $url)->withStatus(302);
}
