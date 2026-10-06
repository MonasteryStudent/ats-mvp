<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/database.php';

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

function signInUser(int $userId, string $role): void
{
    startSession();
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $role;
}

function userIsAuthenticated(): bool
{
    $userId = authenticatedUserId();
    $sessionRole = authenticatedUserRole();

    if ($userId === null || $sessionRole === null) {
        return false;
    }

    try {
        $statement = database()->prepare(
            'SELECT rolle, ist_aktiv
             FROM benutzerkonten
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $userId,
        ]);

        $user = $statement->fetch();

        if (
            $user === false
            || (int) $user['ist_aktiv'] !== 1
            || $user['rolle'] !== $sessionRole
        ) {
            signOutUser();
            return false;
        }

        return true;
    } catch (Throwable $exception) {
        error_log(
            'Fehler bei der Sitzungsprüfung: '
            . $exception->getMessage()
        );

        http_response_code(500);
        exit(
            'Der Zugang konnte nicht überprüft werden. '
            . 'Bitte versuche es später erneut.'
        );
    }
}

function startPageForRole(string $role): string
{
    return match ($role) {
        'recruiting' => 'recruiting.php',
        'admin' => 'admin.php',
        default => 'konto.php',
    };
}

function requireAuthentication(): void
{
    if (userIsAuthenticated()) {
        return;
    }

    header('Location: anmelden.php?from=overview');
    exit;
}

function requireRole(string $requiredRole): void
{
    requireAuthentication();

    if (authenticatedUserRole() === $requiredRole) {
        return;
    }

    http_response_code(403);
    exit('Zugriff nicht erlaubt.');
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