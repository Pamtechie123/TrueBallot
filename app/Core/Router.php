<?php
declare(strict_types=1);

namespace App\Core;

/** Matches the requested URL to a controller method using config/routes.php. */
final class Router
{
    public function __construct(private array $routes)
    {
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path   = $this->normalise($uri);
        $key    = $method . ' ' . $path;

        if (!isset($this->routes[$key])) {
            throw new HttpException(404, 'We could not find the page you asked for.');
        }

        // When a file is bigger than the server allows, PHP throws away the whole form. Say so clearly.
        if ($method === 'POST' && $_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            throw new HttpException(400, 'The file you sent is too large. Photos can be 2 MB at most. Please go back and choose a smaller one.');
        }

        // Every form must carry the secret token from csrf_field().
        if ($method === 'POST' && !Auth::verifyCsrf(is_string($_POST['_token'] ?? null) ? $_POST['_token'] : null)) {
            throw new HttpException(400, 'Your session expired. Please go back, refresh the page and try again.');
        }

        [$controllerClass, $action] = $this->routes[$key];

        $controller = new $controllerClass();   // the access check runs here
        $controller->$action();
    }

    /** Remove the folder prefix and query string so only /login, /admin/voters etc. remain. */
    private function normalise(string $uri): string
    {
        $path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));

        $base  = base_url();
        $roots = [$base];
        if (str_ends_with($base, '/public')) {
            $roots[] = substr($base, 0, -7);   // also accept /TrueBallot/login (no /public)
        }

        foreach ($roots as $root) {
            if ($root !== '' && stripos($path, $root) === 0
                && (strlen($path) === strlen($root) || $path[strlen($root)] === '/')) {
                $path = substr($path, strlen($root));
                break;
            }
        }

        $path = '/' . trim($path, '/');
        return $path === '/index.php' ? '/' : $path;
    }
}
