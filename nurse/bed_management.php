<?php
require_once __DIR__ . '/../config/ip_allowlist.php';
checkIPAllowlist('staff');
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../' . ADMIN_PATH . '/manage_wards.php';
