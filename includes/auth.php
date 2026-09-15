<?php

require_once __DIR__ . '/functions.php';

/**
 * Require that a user is logged in with a specific role.
 */
function require_role(string $role): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['role']) || $_SESSION['role'] !== $role) {
        if ($role === 'admin') {
            header('Location: ' . app_url('admin/'));
            exit;
        }

        header('Location: ' . app_url('login.php'));
        exit;
    }
}

/**
 * Convenience alias for admin pages.
 */
function ensure_role(string $role): void
{
    require_role($role);
}

/**
 * Specialized admin authentication helper.
 */
function require_admin(): void
{
    require_role('admin');
}

/**
 * Specialized patient authentication helper.
 */
function require_patient(): void
{
    require_role('patient');
}
