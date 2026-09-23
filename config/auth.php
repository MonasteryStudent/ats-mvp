<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

function authenticatedUserId(): ?int
{
    startSession();

    $userId = $_SESSION['user_id'] ?? null;

    return is_int($userId) && $userId > 0
        ? $userId
        : null;
}

function authenticatedUserRole(): ?string
{
    startSession();

    $role = $_SESSION['user_role'] ?? null;

    return is_string($role)
        ? $role
        : null;
}

function userIsAuthenticated(): bool
{
    return authenticatedUserId() !== null
        && authenticatedUserRole() !== null;
}

function signInUser(int $userId, string $role): void
{
    startSession();
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $role;
}

function requireAuthentication(): void
{
    if (userIsAuthenticated()) {
        return;
    }

    header('Location: anmelden.php?from=overview');
    exit;
}

function signOutUser(): void
{
    startSession();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $cookieParameters = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookieParameters['path'],
            'domain' => $cookieParameters['domain'],
            'secure' => $cookieParameters['secure'],
            'httponly' => $cookieParameters['httponly'],
            'samesite' => $cookieParameters['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}