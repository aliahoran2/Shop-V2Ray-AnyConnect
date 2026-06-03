<?php
require_once dirname(__DIR__) . '/includes/core.php';

session_destroy();

header('Location: login.php');
exit;
