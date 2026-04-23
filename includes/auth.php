<?php

/**
 * Ensure the user is logged in.
 * Starts the session if not already started.
 * Redirects to login.php if no active session exists.
 *
 * @return void
 */
function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Ensure the user is logged in AND has the executive role.
 * Calls requireLogin() first, then checks the role.
 * Redirects to events.php if the user is not an executive.
 *
 * @return void
 */
function requireExec(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'executive') {
        header('Location: events.php');
        exit;
    }
}

/**
 * Return an array of the current logged-in user's data.
 * Reads from $_SESSION. Returns safe defaults if not set.
 * @return array  Associative array with keys: id, name, role.
 */
function currentUser(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return [
        'id'   => $_SESSION['user_id']  ?? null,
        'name' => $_SESSION['name']     ?? '',
        'role' => $_SESSION['role']     ?? 'member',
    ];
}

/**
 * Check whether the current user has the executive role.
 * @return bool  True if the user is an executive, false otherwise.
 */
function isExec(): bool {
    return currentUser()['role'] === 'executive';
}