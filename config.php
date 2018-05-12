<?php
$config = [
	'user' => '',
	'pass' => '',
];

if (file_exists(__DIR__ . '/config-local.php')) {
	$localConfig = require __DIR__ . '/config-local.php';
	$config = array_merge($config, $localConfig);
}

return $config;
