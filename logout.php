<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/router.php';
logoutUser();
go('login.php?message=' . urlencode('You have been logged out successfully.'));
