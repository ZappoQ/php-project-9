<?php

namespace App;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Views\Twig;
use Throwable;

class ErrorHandler
{
    private Twig $twig;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(Twig $twig, ResponseFactoryInterface $responseFactory)
    {
        $this->twig = $twig;
        $this->responseFactory = $responseFactory;
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        $code = $this->getCode($exception);
        $response = $this->responseFactory->createResponse();

        if ($code === 404) {
            return $this->twig->render($response->withStatus(404), 'errors/404.html.twig');
        }

        return $this->twig->render($response->withStatus(500), 'errors/500.html.twig');
    }

    private function getCode(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof HttpNotFoundException => 404,
            $exception instanceof HttpForbiddenException => 403,
            $exception instanceof HttpUnauthorizedException => 401,
            $exception instanceof HttpBadRequestException => 400,
            $exception instanceof HttpMethodNotAllowedException => 405,
            default => 500,
        };
    }
}
