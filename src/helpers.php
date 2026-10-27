<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

/**
 * Редирект с использованием именованного роута.
 */
function redirectTo(
    Response $response,
    Request $request,
    string $routeName,
    array $params = []
): Response {
    $routeParser = RouteContext::fromRequest($request)->getRouteParser();
    $url = $routeParser->urlFor($routeName, $params);

    return $response->withHeader('Location', $url)->withStatus(302);
}
