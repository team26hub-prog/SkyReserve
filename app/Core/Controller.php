<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function redirect(string $path): never
    {
        header('Location: ' . $path, true, 303);
        exit;
    }

    protected function requireGuest(): void
    {
        if ($user = Auth::user()) {
            $this->redirect(Auth::home($user));
        }
    }

    protected function requireRole(string $role): array
    {
        $user = Auth::user();
        if (!$user) {
            $this->redirect($role === 'admin' ? '/admin/login' : '/login');
        }
        if ($user['role'] !== $role) {
            http_response_code(403);
            $this->render('errors/forbidden', ['title' => 'Access denied']);
            exit;
        }
        return $user;
    }

    protected function requireCsrf(): void
    {
        if (!Session::validCsrf($_POST['_token'] ?? null)) {
            http_response_code(403);
            $this->render('errors/forbidden', ['title' => 'Request expired', 'message' => 'This form has expired. Reload the page and try again.']);
            exit;
        }
    }

    protected function render(string $view, array $data = []): void
    {
        // View names are internal identifiers, never paths supplied by a visitor.
        if (!preg_match('/\A[a-zA-Z0-9_\/-]+\z/', $view)) {
            throw new \InvalidArgumentException('Invalid view name.');
        }

        $viewFile = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('View not found.');
        }

        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $data['user'] = Auth::user();
        $data['flash'] = Session::pullFlash();
        $csrf = Session::csrfToken();
        require BASE_PATH . '/app/Views/layouts/base.php';
    }
}
