<?php

/**
 * Smart redirect ke print_invoice.php
 */
require_once(__DIR__ . '/../../config.php');
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die('Invalid sale ID');
$_GET['id'] = $id;
include(__DIR__ . '/print_invoice.php');
exit;
