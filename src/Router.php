<?php

namespace App;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler, bool $auth = false): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'path'    => $path,
            'handler' => $handler,
            'auth'    => $auth,
        ];
    }

    public function get(string $path, callable|array $handler, bool $auth = false): void
    {
        $this->add('GET', $path, $handler, $auth);
    }

    public function post(string $path, callable|array $handler, bool $auth = false): void
    {
        $this->add('POST', $path, $handler, $auth);
    }

    public function put(string $path, callable|array $handler, bool $auth = false): void
    {
        $this->add('PUT', $path, $handler, $auth);
    }

    public function delete(string $path, callable|array $handler, bool $auth = false): void
    {
        $this->add('DELETE', $path, $handler, $auth);
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            $pattern = $this->toPattern($route['path']);
            if (!preg_match($pattern, $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $user = null;

            if ($route['auth']) {
                $user = \App\Auth::requireUser();
            }

            $handler = $route['handler'];
            if (is_array($handler)) {
                [$class, $action] = $handler;
                $controller = new $class();
                $controller->$action($params, $user);
            } else {
                $handler($params, $user);
            }
            return;
        }

        Response::notFound('Endpoint tidak ditemukan');
    }

    private function toPattern(string $path): string
    {
        $quoted = preg_quote($path, '#');
        $pattern = preg_replace('#\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\}#', '(?P<$1>[^/]+)', $quoted);
        return '#^' . $pattern . '$#';
    }
}
