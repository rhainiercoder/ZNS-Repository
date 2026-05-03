<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(): array {
    if (empty($_SESSION["user"]) || empty($_SESSION["user"]["id"])) {
        header("Location: /qm/login.php");
        exit;
    }
    return $_SESSION["user"];
}

function require_role(array $roles): array {
    $user = require_login();

    $role = $user["role"] ?? "";
    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        die("Forbidden");
    }

    return $user;
}