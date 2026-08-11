<?php

declare(strict_types=1);

namespace App\Routing;

use App\Exception\MethodNotAllowedException;
use App\Exception\RouteNotFoundException;
use App\Http\Request;
use App\Http\Response;
use InvalidArgumentException;
use LogicException;

final class Router
{
    /**
     * @var list<array{
     *     method: string,
     *     pattern: string,
     *     handler: callable
     * }>
     */
    private array $routes = [];

    public function get(
        string $path,
        callable $handler
    ): self {
        return $this->add('GET', $path, $handler);
    }

    public function post(
        string $path,
        callable $handler
    ): self {
        return $this->add('POST', $path, $handler);
    }

    public function put(
        string $path,
        callable $handler
    ): self {
        return $this->add('PUT', $path, $handler);
    }

    public function add(
        string $method,
        string $path,
        callable $handler
    ): self {
        $method = strtoupper(trim($method));

        if ($method === '') {
            throw new InvalidArgumentException(
                'Metodo HTTP obrigatorio.'
            );
        }

        $this->routes[] = [
            'method' => $method,
            'pattern' => $this->compilePath($path),
            'handler' => $handler,
        ];

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $matches = [];

            if (
                preg_match(
                    $route['pattern'],
                    $request->path,
                    $matches
                ) !== 1
            ) {
                continue;
            }

            if ($route['method'] !== $request->method) {
                $allowedMethods[] = $route['method'];

                continue;
            }

            $parameters = [];

            foreach ($matches as $name => $value) {
                if (!is_string($name)) {
                    continue;
                }

                $parameters[$name] = rawurldecode(
                    (string) $value
                );
            }

            $response = ($route['handler'])(
                $request,
                $parameters
            );

            if (!$response instanceof Response) {
                throw new LogicException(
                    'O handler da rota deve retornar Response.'
                );
            }

            return $response;
        }

        if ($allowedMethods !== []) {
            $allowedMethods = array_values(
                array_unique($allowedMethods)
            );

            sort($allowedMethods);

            throw new MethodNotAllowedException(
                $allowedMethods
            );
        }

        throw new RouteNotFoundException(
            'Rota nao encontrada.'
        );
    }

    private function compilePath(string $path): string
    {
        if ($path === '' || $path[0] !== '/') {
            throw new InvalidArgumentException(
                'A rota deve comecar com /.'
            );
        }

        if ($path === '/') {
            return '#\A/?\z#D';
        }

        $segments = explode(
            '/',
            trim($path, '/')
        );

        $compiledSegments = [];
        $parameterNames = [];

        foreach ($segments as $segment) {
            if (
                preg_match(
                    '/\A\{([A-Za-z_][A-Za-z0-9_]*)\}\z/',
                    $segment,
                    $matches
                ) === 1
            ) {
                $parameterName = $matches[1];

                if (isset($parameterNames[$parameterName])) {
                    throw new InvalidArgumentException(
                        'Parametro duplicado na rota.'
                    );
                }

                $parameterNames[$parameterName] = true;

                $compiledSegments[] = sprintf(
                    '(?P<%s>[^/]+)',
                    $parameterName
                );

                continue;
            }

            if (
                str_contains($segment, '{')
                || str_contains($segment, '}')
            ) {
                throw new InvalidArgumentException(
                    'Parametro de rota invalido.'
                );
            }

            $compiledSegments[] = preg_quote(
                $segment,
                '#'
            );
        }

        return sprintf(
            '#\A/%s/?\z#D',
            implode('/', $compiledSegments)
        );
    }
}
