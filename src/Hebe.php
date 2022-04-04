<?php

namespace Hebe;

class Hebe
{
    /** @var HebeConfig */
	public static $config;
    /** @var \stdClass */
    public static $config_data;
    /** @var HebeProjects */
    public static $projects;
    /** @var array */
    public static $projects_data;

	public static function message(string $message, string $type = 'php://stdout'): void
    {
		$std_err = fopen($type, 'wb');
		fwrite($std_err, $message . "\n");
		fclose($std_err);
	}

	public static function error(string $message): void
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

	public static function requirements(): bool
    {
		if (!function_exists("exec")) {
            self::error("exec() function appears to be disabled but required.");

            return false;
        }

        return true;
	}
}
