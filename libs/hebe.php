<?php

define('DS', DIRECTORY_SEPARATOR);
require __DIR__ . '/../libs/hebeconfig.php';
require __DIR__ . '/../libs/hebeplatform.php';
require __DIR__ . '/../libs/hebeprojects.php';
require __DIR__ . '/../helpers/json.php';
require __DIR__ . '/../helpers/array.php';
require __DIR__ . '/../helpers/colors.php';

class Hebe
{
	public static $config, $config_data, $projects, $projects_data;
	private static $path;

	public static function message($message, $type = 'php://stdout')
    {
		$std_err = fopen($type, 'wb');
		fwrite($std_err, $message . "\n");
		fclose($std_err);
	}

	public static function error($message)
    {
		self::message("ERROR: " . $message, 'php://stderr');
		exit(1);
	}

	public function __construct()
    {
		self::$config = new HebeConfig();
		self::$config_data = self::$config->data;

		self::$projects = new HebeProjects(self::$config);
		self::$projects_data = self::$projects->data;
	}

	public static function load($config_path = null)
    {
        if (!$config_path) {
            static::$path = '';
        }
	}

	public static function requirements()
    {
		$errors = array();
		if (!function_exists("exec")) {
		    $errors[] = "exec() function appears to be disabled but required.";
        }

		if (!count($errors)) {
		    return true;
        }

		Hebe::error(implode("\n", $errors));
	}
}
