<?php
/**
 * Start the PHP session using settings from conn/config.php.
 * Include this instead of calling session_start() directly.
 */
require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
