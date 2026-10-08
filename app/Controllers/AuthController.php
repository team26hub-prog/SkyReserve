<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Models\User;
use PDOException;

final class AuthController extends Controller
{
    public function registerForm(): void
    {
        $this->requireGuest();
        $this->render('auth/register', ['title' => 'Create your account']);
    }

    public function register(): void
    {
        $this->requireGuest();
        $this->requireCsrf();
        $name = trim($this->input('name'));
        $email = strtolower(trim($this->input('email')));
        $phone = trim($this->input('phone'));
        $password = $this->input('password');
        $confirmation = $this->input('password_confirmation');
        $errors = [];

        if ($name === '' || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name) > 120 || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            $errors['name'] = 'Enter a name of 1–120 characters.';
        }
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        // PASSWORD_DEFAULT currently uses bcrypt, which accepts at most 72 bytes.
        if (mb_strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
            $errors['password'] = 'Use at least 8 characters and at most 72 bytes.';
        }
        if ($password !== $confirmation) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }
        if ($phone !== '' && (strlen($phone) > 30 || !preg_match('/\A[0-9+().\- ]+\z/', $phone))) {
            $errors['phone'] = 'Enter a valid phone number of up to 30 characters.';
        }

        $users = new User();
        if (!$errors && $users->findByEmail($email)) {
            $errors['email'] = 'An account with this email already exists.';
        }
        if (!$errors) {
            try {
                $users->createCustomer($name, $email, $password, $phone !== '' ? $phone : null);
            } catch (PDOException $exception) {
                // The unique index also handles simultaneous registrations.
                if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                    throw $exception;
                }
                $errors['email'] = 'An account with this email already exists.';
            }
        }
        if ($errors) {
            http_response_code(422);
            $this->render('auth/register', [
                'title' => 'Create your account',
                'errors' => $errors,
                'old' => ['name' => $name, 'email' => $email, 'phone' => $phone],
            ]);
            return;
        }
        Session::flash('Account created. You can now log in.', true);
        $this->redirect('/login');
    }

    public function customerLoginForm(): void
    {
        $this->loginForm('customer');
    }

    public function adminLoginForm(): void
    {
        $this->loginForm('admin');
    }

    public function customerLogin(): void
    {
        $this->login('customer');
    }

    public function adminLogin(): void
    {
        $this->login('admin');
    }

    public function customerLogout(): void
    {
        $this->logout('customer');
    }

    public function adminLogout(): void
    {
        $this->logout('admin');
    }

    private function loginForm(string $role): void
    {
        $this->requireGuest();
        $this->render('auth/login', ['title' => $role === 'admin' ? 'Admin login' : 'Customer login', 'role' => $role]);
    }

    private function login(string $role): void
    {
        $this->requireGuest();
        $this->requireCsrf();
        $email = strtolower(trim($this->input('email')));
        $password = $this->input('password');
        $user = null;
        if (strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL)
            && $password !== '' && strlen($password) <= 72 && !str_contains($password, "\0")) {
            $user = (new User())->findByEmail($email);
        }
        if (!$user || !password_verify($password, $user['password_hash'])
            || $user['role'] !== $role || $user['status'] !== 'active') {
            http_response_code(422);
            $this->render('auth/login', [
                'title' => $role === 'admin' ? 'Admin login' : 'Customer login',
                'role' => $role,
                'error' => 'Invalid email or password for this login.',
                'email' => $email,
            ]);
            return;
        }
        Auth::login($user);
        Session::flash('Welcome back. You are now logged in.', true);
        $this->redirect(Auth::home($user));
    }

    private function logout(string $role): void
    {
        $this->requireRole($role);
        $this->requireCsrf();
        Auth::logout();
        // Start a new anonymous session for the one-time logout notice.
        session_id('');
        Session::start();
        Session::flash('You have been logged out successfully.', true);
        $this->redirect($role === 'admin' ? '/admin/login' : '/login');
    }

    private function input(string $key): string
    {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
    }
}
