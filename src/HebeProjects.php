<?php

namespace Hebe;

use JsonException;
use RuntimeException;

class HebeProjects
{
    /** @var array */
    public $data = [];

    /** @var HebeConfig */
	private $config;
    /** @var string */
	private $projects_path;
    /** @var string */
	private $projects_file;

	public function __construct(HebeConfig $config)
    {
		if (!Hebe::requirements()) {
		    return;
        }

		$this->projects_path = exec('echo $HOME') . '/.hebe';
		$this->projects_file = $this->projects_path . '/projects';

		$this->config = $config;

		$this->create_projects_file();
		$this->load_projects_file();
	}

    /**
     * @param string $manifest
     * @param string|false $platform
     * @param string|false $projectName
     * @return array
     * @throws RuntimeException
     */
	public function load_manifest(string $manifest, $platform = false, $projectName = false): array
    {
		$manifest = $this->_clean_path($manifest, 'right');
		if (is_dir($manifest)) {
		    $manifest .= DS . $this->config->get('manifest');
        }

		if (!preg_match("/" . $this->config->get('manifest') . "$/", $manifest)) {
            throw new RuntimeException("Error: The passed in manifest ($manifest) appears to be invalid. Expected manifest is `" . $this->config->get('manifest') . "`.");
        }

        try {
            $data = file_exists($manifest) ? file_get_contents($manifest) : false;
            if (false === $data) {
                throw new RuntimeException('File not found or cannot be read');
            }
            $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

        } catch (JsonException|RuntimeException $e) {
		    throw new RuntimeException("Failed to decode the manifest file `{$manifest}`: " . $e->getMessage());
        }

		if ($platform !== false) {
			if (array_keys($data)[0] === 0) {
				// Multiple projects.
				foreach ($data as $project) {
					if (isset($project['project']) && strtolower($project['project']) === strtolower($projectName)) {
						$data = $project;
						break;
					}
				}
			}

			$key = $this->array_key_exists_nc($platform, $data['platforms']);
			if ($key) {
			    $data = $data['platforms'][$key];
            }
		}

		return $data;
	}

	private function create_projects_file(): void
    {
		if (!is_dir($this->projects_path) && !mkdir($this->projects_path) && !is_dir($this->projects_path)) {
			Hebe::error("Failed to create folder `" . $this->projects_path . "`");
		}

		if (!file_exists($this->projects_file)) {
            $handle = @fopen($this->projects_file, 'ab+');
            if ($handle) {
                @fclose($handle);
            } else {
                Hebe::error("Failed to create the projects file " . $this->projects_file);
            }
		}
	}

    /**
     * @return void
     */
	public function load_projects_file(): void
    {
        try {
            $data = file_exists($this->projects_file) ? file_get_contents($this->projects_file) : false;
            if (false === $data) {
                throw new RuntimeException('File not found or cannot be read');
            }

            $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException|RuntimeException $e) {
            Hebe::error("Failed to decode the projects file `" . $this->projects_file . "`" . $e->getMessage());

            return;
        }

		if (count($data)) {
            $this->data = $data;
		} else {
            $this->data = [];
            $this->save_projects_file();
        }
	}

    /**
     * @return void
     * @throws JsonException
     */
	public function save_projects_file(): void
    {
		$data = json_encode($this->data, JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);

		if (!@file_put_contents($this->projects_file, $data)) {
			Hebe::error("Unable to save the projects changes into `" . $this->projects_file . "`");
		}
	}

	public function register(array $options = ["arguments" => [], "name" => "", "force" => false, "silent" => false]): void
    {
		$locations = $options['arguments'];
		$name = !empty($options['name']) ? $options['name'] : false;
		$force = $options['force'];
		$silent = $options['silent'] ?? false;

		foreach($locations as $location) {
            try {
                $location = $this->_fix_path($location);
                $manifest = $this->load_manifest($location);
            } catch (RuntimeException $e) {
                Hebe::error($e->getMessage());

                return;
            }

            // it's a single project, convert to array
            if (array_keys($manifest)[0] !== 0) {
                $manifest = [$manifest];
            }

            foreach ($manifest as $manifesto) {
                $project = $manifesto['project'] ?? false;
                $requires = $manifesto['requires'] ?? [];

                if ($name !== false) {
                    $project = $name;
                }

                $platforms = array_keys($manifesto['platforms']);

                $added = [];
                $skipped = [];
                $newproject = false;
                if (!isset($this->data[$project])) {
                    $this->data[$project] = [];
                    $newproject = true;
                }

                foreach($platforms as $platform) {
                    if (!isset($this->data[$project][$platform]) || $force) {
                        $this->data[$project][$platform]= rtrim(preg_replace("/".$this->config->get('manifest')."$/", "", $location), '/');
                        $added[] = ucfirst($platform);
                    } else {
                        $skipped[] = ucfirst($platform);
                    }
                }

                $message = "";
                if (count($added)) {
                    $message .= "\n   └ added: " . implode(", ", $added);
                }
                if (count($skipped)) {
                    $message .= "\n   └ skipped: " . implode(", ", $skipped);
                }
                if (!count($added) && !count($skipped)) {
                    $message .= "\n   └ no platforms";
                }

                $status = ($newproject ? "created." : (count($added) ? "updated." : "skipped."));

                if (!$silent) {
                    Hebe::message("\nProject `" . $project . "` " . $status . $message ."\n");
                }

                if (isset($requires)) {
                    $requirements = [];
                    foreach($requires as $require_project => $require_data) {
                        if (!isset($this->data[$require_project])) {
                            $requirements[] = $require_project;
                        }
                    }

                    if (!$silent && count($requirements)) {
                        Hebe::message("Warning: Project `" . $project . "` requires the projects `" . implode(", ", $requirements) . "` to be registered.");
                        Hebe::message("         Please run ./hebe register <projects working path>\n");
                    }
                }
            }
        }

		$this->save_projects_file();
	}

	public function unregister(array $options = ["arguments" => [], "silent" => false]): void
    {
		$projects = $options['arguments'] ?? false;
		$silent = $options['silent'] ?? false;

		foreach($projects as $project) {
			$key = $this->array_key_exists_nc($project, $this->data);

			if (is_string($key)) {
			    unset($this->data[$key]);
            }

			$status = !$key ? "not found." : "removed.";

			if (!$silent) {
			    Hebe::message("\nProject `" . (!$key ? $project : $key) . "` " . $status ."\n");
            }
		}

		$this->save_projects_file();
	}

	public function list_project(array $options = ["arguments" => [], "filter" => false]): void
    {
		$filters = $options['filter'] ?? false;
		$arguments = $options['arguments'] ?? false;

		$projects = "";
		$message = "\n";

		if (!count($filters) && !count($arguments)) {
		    $projects = $this->data;
        }
		if (count($arguments)) {
			$projects = [];
			foreach($arguments as $argument) {
				if (isset($this->data[$argument])) {
				    $projects[$argument] = $this->data[$argument];
                }
			}
		}
		if (count($filters)) {
			$projects = [];
			foreach($filters as $filter) {
				foreach($this->data as $project_name => $project_data) {
					if (preg_match("/{$filter}/i", $project_name)) {
					    $projects[$project_name] = $project_data;
                    }
				}
			}
		}

		$count = count($projects);

		$message .= "Found " . $count . " projects.";
		if (count($filters)) {
		    $message .= "\n[filter: " . implode(' ', $filters) . ']';
        }

		if ($count > 0){
			foreach ($projects as $name => $project) {
				$message .= "\n\n". $name . ":";
				foreach($project as $platform => $working_path) {
					$message .= "\n   └ " . ucfirst($platform) . ':  ' . str_replace(exec('echo $HOME'), "~", $working_path);
				}
			}
		}

		Hebe::message($message . "\n");

	}

	public function link_project(array $options = ["projects" => [], "destinations" => [], "platform" => [], "name" => "", "force" => false, "silent" => false]): void
    {
		$destinations = $options['destinations'] ?? false;
		$projects = $options['projects'] ?? false;
		$platform_option = $options['platform'] !== '' ? $options['platform']: false;
		$rename = $options['name'] ?? false;
		$force  = $options['force'] ?? false;
		$silent = $options['silent'] ?? false;

		foreach($options as $key => $options_data) {
			if ($key === 'destinations') {
			    continue;
            }

			if (is_array($options_data)) {
				foreach($options_data as $index => $option) {
					$dest = $this->_clean_path($this->_fix_path($option), 'right');
					$name = $this->array_key_exists_nc($option, $this->data);

					if (!file_exists($dest)) {
					    continue;
                    }
					if ($name && in_array(dirname($dest), $this->data[$name], true)) {
					    continue;
                    }
					if ($name && file_exists(exec('echo `pwd`') . '/'. $name)) {
					    continue;
                    }
					if (!in_array($dest, $destinations, true)) {
					    $destinations[] = $dest;
                    }
					unset(${$key}[$index]);
				}
			} else {
				$dest = $this->_clean_path($this->_fix_path($dest), 'right');
				if (!file_exists($dest)) {
				    continue;
                }
				if (!in_array($dest, $destinations, true)) {
				    $destinations[] = $dest;
                }
			}
		}

		if (!count($projects)) {
		    Hebe::error("There is no project set to be linked. Please read the documentation (./command help link).");
        }

		if (!count($destinations)) {
		    $destinations[] = $this->_fix_path('.');
        }

		foreach($destinations as $dest) {
			$dest = $this->_clean_path($this->_fix_path($dest), 'right');

			if (file_exists($dest)) {
				if (!$silent) {
				    Hebe::message("\nLinking projects into `{$dest}`");
                }
				foreach($projects as $project) {
					$name = $project;
					$project = $this->array_key_exists_nc($name, $this->data);

					if (!$project) {
						Hebe::message("\n   └ " . $name . ": Project not found");
					}
					else {
						$name = $project;
						$project = $this->data[$name];

						$expected_platform = HebePlatform::getInfo($this->_clean_path($dest, 'right'));
						$platform = (!$platform_option) ? $expected_platform : $platform_option;

						$platform = $this->resolvePlatform($project, $platform);
						$platform = $this->array_key_exists_nc($platform, $project);

						if (!$platform) {
						    $platform = $this->array_key_exists_nc("custom", $project);
                        }

						if (!$platform) {
							$platform = (!$platform_option) ? $expected_platform : $platform_option;
							Hebe::message("   └ {$name}: No platform `{$platform}` found in the manifest.");
						} else {
							$working_path = $project[$platform];
							$manifest = $this->load_manifest($working_path);
							$requires = $manifest['requires'] ?? array();

							$manifest = $this->load_manifest($working_path, $platform, $name);
							$nodes = $manifest['nodes'];

							if (!$silent) {
							    Hebe::message("   └ " . $name . " [".$platform."]");
                            }

							if (!$nodes) {
								Hebe::message("      └ No nodes found.\n");
								continue;
							}


							$requirements = [];
							if (isset($requires)) {
								foreach($requires as $require_project => $require_data) {
									if (!isset($this->data[$require_project])) {
										$requirements[] = $require_project;
									}
								}
							}

							if (!$force && count($requirements)) {
								Hebe::message("      └ skipped: unregistered requirements: " . implode(", ", $requirements));
							} else {
								foreach($nodes as $node => $paths) {
									$failed_nodes = ["count" => 0, "msg" => []];
									foreach($paths as $path) {
										if (isset($path['project'])) {
											if (!$this->array_key_exists_nc($path['project'], $this->data)) {
												$failed_nodes["msg"][] = "            └ node failed: sub project not registered -> {$path['project']}";
												$failed_nodes["count"]++;
												continue;
											}

											$this->link_project([
											    "destinations" => [$dest],
												"projects"     => [$path['project']],
												"platform"     => $platform,
												"name"         => '',
												"force"        => $force,
												"silent"       => true
											]);

											continue;
										}

										$source = $this->_clean_path($working_path, 'right') . DS . $this->_clean_path($path['source']);
										$destination = $this->_clean_path($dest, 'right') . DS . $this->_clean_path($path['destination']);

										if (!$path['destination']) {
											if ($platform === 'custom' && $rename) {
											    $destination .= $rename;
                                            } else {
											    $destination .= $node;
                                            }
										}

										if ($this->config->get('backup_existing_when_linking') && file_exists($destination)) {
                                            exec('rm -rf ' . $destination . '.backup');
                                            exec('mv ' . $destination . ' ' . $destination . '.backup');
                                        }

										if (!file_exists($destination)) {
										    exec('mkdir -p ' . $destination);
                                        }
										exec('rm -rf ' . $destination);

										if (!file_exists($source)) {
											$failed_nodes["msg"][] = "            └ node failed: source not found -> " . $path['source'];
											$failed_nodes["count"]++;
										} else {
											if (!symlink($source, $destination)) {
												$failed_nodes["msg"][] = "            └ node failed: link error -> " . $path['source'];
												$failed_nodes["count"]++;
											}
										}
									}

									if (!$silent) {
									    Hebe::message("      └ " . $node . ": " . ($failed_nodes["count"] === count($paths) ? "failed" : "ok"));
                                    }
									if (count($failed_nodes["msg"])) {
									    Hebe::message(implode("\n", $failed_nodes["msg"]));
                                    }
								}
							}

							if (!$silent) {
							    Hebe::message("");
                            }
						}
					}
				}
			}
		}

		if (!$silent) {
		    Hebe::message("");
        }
	}

	public function edit_project(array $options = ["arguments" => [], "platforms" => [], "force" => false]): void
    {
		$projects = $options['arguments'] ?? false;
		$platforms = $options['platforms'] ?? false;

		$list = array();

		foreach ($projects as $project) {
			$project_name = $this->array_key_exists_nc($project, $this->data);
			if (!$project_name) {
			    Hebe::message("Project `{$project}` not found.");
            } else {
				if (!count($platforms)) {
				    foreach($this->data[$project_name] as $working_path) {
				        $list[] = $working_path;
                    }
                } else {
					foreach($platforms as $platform) {
						$platform_name = $this->array_key_exists_nc($platform, $this->data[$project_name]);

						if (!$platform_name) {
						    Hebe::message("No platform `{$platform}` found in the manifest of project `{$project}`.");
                        } else {
							$list[] = $this->data[$project_name][$platform];
						}
					}
				}
			}
		}

		if (count($list)) {
		    exec($this->config->get('editor') . ' ' . implode(" ", $list));
        }
	}

	public function sync_projects(array $options = ["arguments" => [], "update" => false]): void
    {
		$projects = $options['arguments'] ?? false;
		$update = $options['update'] ?? false;

		$data = $this->data;

        $errors = [];
		if (count($projects)) {
			$data = [];

			foreach($projects as $project) {
				$name = $project;
				$project = $this->array_key_exists_nc($name, $this->data);

				if (!$project) {
				    $errors[] = $name;
                } else {
				    $data[$project] = $this->data[$project];
                }
			}

		}

		Hebe::message("\nResyncing " . count($data) . " projects...");
		if (count($errors)) {
		    Hebe::message("Some have not been found: " . implode(", ", $errors));
        }

		if ($update) {
			Hebe::message("\nNote that with the update option, every project will get git/svn updated first. \nThis might take some time based on how many projects you have, speed connection, etc...");
		}

		Hebe::message("\n");

		foreach($data as $project => $nodes) {
			if (strtolower($project) === 'hebe') {
			    continue;
            }

			Hebe::message("Syncing project `{$project}`");
			foreach($nodes as $platform => $node) {
				if (!file_exists($node)) {
					unset($this->data[$project][$platform]);

					$status = "(removed) nodes location not found.";
				} else {
					$update_status = "";
					if ($update){
						if (file_exists($node . DS . '.svn')) {
							exec("svn update ". $node);
							$update_status .= 'svn ';
						}
						else if (file_exists($node . DS . '.git')) {
							exec("cd $node && git pull");
							$update_status .= 'git ';
						}
						$update_status .= "updated and ";
					}

					$options = ["arguments" => [$node], "name" => $project, "force" => true, "silent" => true];
					$this->register($options);

					$status = "(synched) nodes have been {$update_status}re-registered.";
				}

				Hebe::message("      └ [".$platform."]: ". $status);
			}

			Hebe::message("\n");
		}

		$this->save_projects_file();
	}

	public function getAlias(array $project = null, string $platform = null): ?string
    {
		if (!$project || !$platform) {
		    return null;
        }

		foreach($project as $node) {
			$manifest = $this->load_manifest($node);
			$aliases = $manifest['aliases'] ?? false;
			if (!$aliases) {
			    continue;
            }

			$alias = $aliases[0];
			$platform = $this->array_key_exists_nc($platform, $alias) ?: '';

			if (isset($alias[$platform])) {
			    return $alias[$platform];
            }
		}

		return null;
	}

	protected function resolvePlatform(array $project, string $platform): ?string
	{
		// if there is an alias for the platform use that
		$alias = $this->getAlias($project, $platform);
		if ($alias) {
			$platform = $alias;
		}
		if (($this->array_key_exists_nc($platform, $project)) === false) {
			$fallback_platform = HebePlatform::getFallback($platform);
			if ($fallback_platform !== null) {
				$working_platform = $this->resolvePlatform($project, $fallback_platform);
			} else {
				$working_platform = $platform;
			}
		} else {
			$working_platform = $platform;
		}

		return $working_platform;
	}

	private function _clean_path(string $manifest, string $position = 'both'): string
    {
		switch($position) {
			case 'left':
				$manifest = ltrim($manifest, "/");
				break;
			case 'right':
				$manifest = rtrim($manifest, "/");
				break;
			case 'both':
            default:
				$manifest = rtrim(ltrim($manifest, "/"), "/");
				break;
		}

		return $manifest;
	}

	private function _fix_path(string $path): ?string
    {
		$first_chr = $path[0] ?? '';
		if ($path === '.') {
		    return exec('echo `pwd`');
        }
		if ($first_chr !== '~' && $first_chr === '/') {
		    return $path;
        }
		if ($first_chr !== '/' && $first_chr !== '~') {
		    return $this->_clean_path(exec('echo `pwd`'), 'right') . DS . $path;
        }
		if ($first_chr === '~') {
		    return  preg_replace("/^~/", exec('echo $HOME'), $path);
        }

		return null;
	}

    /**
     * @param string $key
     * @param array $search
     * @return string|false
     */
    private function array_key_exists_nc(string $key, array $search)
    {
        if (array_key_exists($key, $search)) {
            return $key;
        }

        $key = strtolower($key);
        foreach ($search as $k => $v) {
            if (strtolower($k) === $key) {
                return (string)$k;
            }
        }

        return false;
    }
}
