<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
include '../includes/nav.php';

session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>