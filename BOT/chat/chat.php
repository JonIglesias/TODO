<?php
// Loader: incluye núcleo, admin y frontend del chat
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/chat-core.php';
require_once __DIR__ . '/chat-admin.php';
require_once __DIR__ . '/chat-frontend.php';
