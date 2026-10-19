<?php
declare(strict_types=1);

namespace App\Core;

/**
 * ABSTRACT base for every controller.
 * Each controller must say which area of the site it protects. The check runs automatically
 * before any controller method, so a protected page can never be reached by mistake.
 */
abstract class Controller
{
    public function __construct()
    {
        Auth::authorise($this->requiredArea());
    }

    /** One of the RoleInterface::AREA_* values, or null when the page is public. */
    abstract protected function requiredArea(): ?string;

    /** Show a view inside the shared header and footer. */
    protected function view(string $view, array $data = [], string $title = ''): void
    {
        $viewFile = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        $pageTitle   = $title;
        $currentUser = Auth::user();
        extract($data, EXTR_SKIP);

        // Build the whole page first, so an error half-way never shows a broken page.
        ob_start();
        try {
            require BASE_PATH . '/app/Views/layouts/header.php';
            require $viewFile;
            require BASE_PATH . '/app/Views/layouts/footer.php';
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        echo ob_get_clean();
    }

    protected function redirect(string $path): never
    {
        redirect($path);
    }

    protected function isLoggedIn(): bool
    {
        return Auth::check();
    }

    /** Read a text field from the submitted form, with spaces trimmed from both ends. */
    protected function input(string $key, string $default = ''): string
    {
        return trim((string) ($_POST[$key] ?? $default));
    }

    /** Remember validation errors and what the user typed, then go back to the form. */
    protected function backWithErrors(string $path, array $errors, array $old = []): never
    {
        Session::flash('errors', $errors);
        Session::flash('old', $old);
        $this->redirect($path);
    }
}
