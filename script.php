<?php

use Payment\CommissionTask\CalculateCommissionFee;

define('DIR', __DIR__ . '/');
require DIR . 'vendor/autoload.php';
$calculateCommissionFee = new CalculateCommissionFee;

$calculateCommissionFee->inputDataInFile($argv[1]);
