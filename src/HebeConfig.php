<?php

namespace Hebe;

use JsonException;
use RuntimeException;
use stdClass;

class HebeConfig
{
    /** @var string */
	private $config_path;
    /** @var string */
	private $config_file;
    /** @var stdClass */
	public $data;

	public function __construct()
    {
		if (!Hebe::requirements()) {
		    return;
        }

		$this->config_path = exec('echo $HOME') . '/.hebe';
		$this->config_file = $this->config_path . '/config';

		$this->data = new stdClass();

		$this->create_config();
		$this->load_config();
	}

	private function create_config(): void
    {
        $path = $this->config_path;
		if (!is_dir($path) && !mkdir($path) && !is_dir($path)) {
			Hebe::error("Failed to create folder `" . $this->config_path . "`");
		}

        $from = PATH . '/resources/config';
        $file = $this->config_file;
		if (!file_exists($file) && !@copy($from, $file)) {
			Hebe::error("Failed to copy default config file from `{$from}` to {$file}");
		}
	}

    /**
     * @return void
     */
	public function load_config(): void
    {
        try {
            $data = file_exists($this->config_file) ? file_get_contents($this->config_file) : false;
            if (false === $data) {
                throw new RuntimeException('File not found or cannot be read');
            }
            $this->data = json_decode($data, false, 512, JSON_THROW_ON_ERROR);

        } catch (JsonException|RuntimeException $e) {
            Hebe::error("Failed to decode the config file `" . $this->config_file . "`" . $e->getMessage());
        }
	}

    /**
     * @return void
     * @throws JsonException
     */
    public function save_config(): void
    {
		$data = json_encode($this->data, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);

		if (!@file_put_contents($this->config_file, $data)) {
			Hebe::error("Unable to save the configuration changes into `" . $this->config_file . "`");
		}
	}

    /**
     * @param string $option
     * @return mixed|null
     */
	public function get(string $option)
    {
		if (!isset($this->data->{$option})) {
			Hebe::error("Unable to find the option `{$option}`");

			return null;
		}

        return $this->data->{$option};
	}
}
