<?php

class HebeProjects
{
	private static $config;
	private static $projects_path = '';
	private static $projects_file = '';
	public $data = array();

	public function __construct($config)
    {
		if (!Hebe::requirements()) {
		    return;
        }

		self::$projects_path = exec('echo $HOME').'/.hebe';
		self::$projects_file = self::$projects_path.'/projects';

		self::$config = $config;

		$this->create_projects_file();
		$this->load_projects_file();
	}

	public function load_manifest($manifest, $platform = false, $projectName = false)
    {
		$manifest = $this->_clean_path($manifest, 'right');
		if (is_dir($manifest)) {
		    $manifest .= DS . self::$config->get('manifest');
        }

		if (!preg_match("/".self::$config->get('manifest')."$/", $manifest)) {
            throw new Exception("Error: The passed in manifest ($manifest) appears to be invalid. Expected manifest is `".self::$config->get('manifest')."`.");
        }

		$data = file_get_contents($manifest);
		$data = (strlen($data)) ? json_decode($data, true) : array();

		if ($data === null) {
		    throw new Exception("Failed to decode the manifest file `".$manifest."`" . json_error());
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

			$key = array_key_exists_nc($platform, $data['platforms']);
			if ($key) {
			    $data = $data['platforms'][$key];
            }
		}

		return $data;
	}

	private function create_projects_file(){
		if (!is_dir(self::$projects_path) && !@mkdir(self::$projects_path)) {
			Hebe::error("Failed to create folder `".self::$projects_path."`");
		}

		$handle = null;
		if (!file_exists(self::$projects_file) && $handle = !@fopen(self::$projects_file, 'ab+')) {
			Hebe::error("Failed to create the projects file " . self::$projects_file);
		}

		if ($handle) {
            @fclose($handle);
        }
	}

	public function load_projects_file()
    {
		$data = file_get_contents(self::$projects_file);
		$data = (strlen($data)) ? json_decode($data, true) : array();

		if ($data === null) {
		    Hebe::error("Failed to decode the projects file `".self::$projects_file."`" . json_error());
        } else {
		    $this->data = $data;
        }

		if (!count($this->data)) {
			$this->data = array();
			$this->save_projects_file();
		}
	}

	public function save_projects_file()
    {
		$data = stripslashes(json_beautify(json_encode($this->data)));

		if (!@file_put_contents(self::$projects_file, $data)) {
			Hebe::error("Unable to save the projects changes into `".self::$projects_file."`");
		}
	}

	public function register($options = array("arguments" => array(), "name" => "", "force" => false, "silent" => false))
    {
		$locations = $options['arguments'];
		$name = !empty($options['name']) ? $options['name'] : false;
		$force = $options['force'];
		$silent = isset($options['silent']) ? $options['silent'] : false;

		foreach($locations as $location) {
			$location = $this->_fix_path($location);
			$manifest = $this->load_manifest($location);

			if ($manifest !== null) {
				// it's a single project, convert to array
				if (array_keys($manifest)[0] !== 0) {
					$manifest = array($manifest);
				}

				foreach ($manifest as $manifesto) {

					$project = isset($manifesto['project']) ? $manifesto['project'] : false;
					$requires = isset($manifesto['requires']) ? $manifesto['requires'] : array();

					if ($name !== false) {
					    $project = $name;
                    }

					$platforms = array_keys($manifesto['platforms']);

					$added = array();
					$skipped = array();
					$newproject = false;

					if (!isset($this->data[$project])) {
						$this->data[$project] = array();
						$newproject = true;
					}

					foreach($platforms as $platform) {
						if (!isset($this->data[$project][$platform]) || $force) {
							$this->data[$project][$platform]= rtrim(preg_replace("/".self::$config->get('manifest')."$/", "", $location), '/');
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
						$requirements = array();
						foreach($requires as $require_project => $require_data) {
							if (!isset($this->data[$require_project])) {
								$requirements[] = $require_project;
							}
						}

						if (count($requirements)) {
							if (!$silent) {
								Hebe::message("Warning: Project `".$project."` requires the projects `".implode(", ", $requirements)."` to be registered.");
								Hebe::message("         Please run ./hebe register <projects working path>\n");
							}
						}
					}
				}
			}
		}

		$this->save_projects_file();
	}

	public function unregister($options = array("arguments" => array(), "silent" => false))
    {
		$projects = isset($options['arguments']) ? $options['arguments'] : false;
		$silent = isset($options['silent']) ? $options['silent'] : false;

		foreach($projects as $project) {
			$key = array_key_exists_nc($project, $this->data);

			if (is_string($key)) {
			    unset($this->data[$key]);
            }

			$status = (!$key) ? "not found." : "removed.";

			if (!$silent) {
			    Hebe::message("\nProject `" . (!$key ? $project : $key) . "` " . $status ."\n");
            }
		}

		$this->save_projects_file();
	}

	public function list_project($options = array("arguments" => array(), "filter" => false)) {
		$filters = isset($options['filter']) ? $options['filter'] : false;
		$arguments = isset($options['arguments']) ? $options['arguments'] : false;

		$projects = "";
		$message = "\n";

		if (!count($filters) && !count($arguments)) {
		    $projects = $this->data;
        }
		if (count($arguments)) {
			$projects = array();
			foreach($arguments as $argument) {
				if (isset($this->data[$argument])) {
				    $projects[$argument] = $this->data[$argument];
                }
			}
		}
		if (count($filters)) {
			$projects = array();
			foreach($filters as $filter) {
				foreach($this->data as $project_name => $project_data) {
					if (preg_match("/".$filter."/i", $project_name)) {
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

	public function link_project($options = array("projects" => array(), "destinations" => array(), "platform" => array(), "name" => "", "force" => false, "silent" => false))
    {
		$destinations = isset($options['destinations']) ? $options['destinations'] : false;
		$projects = isset($options['projects']) ? $options['projects'] : false;
		$platform_option = (strlen($options['platform'])) ? $options['platform']: false;
		$rename = isset($options['name']) ? $options['name'] : false;
		$force  = isset($options['force']) ? $options['force'] : false;
		$silent = isset($options['silent']) ? $options['silent'] : false;

		foreach($options as $key => $options_data) {
			if ($key === 'destinations') {
			    continue;
            }
			if (is_array($options_data)) {
				foreach($options_data as $index => $option) {
					$dest = $this->_clean_path($this->_fix_path($option), 'right');
					$name = array_key_exists_nc($option, $this->data);

					if (!file_exists($dest)) {
					    continue;
                    }
					if ($name && array_contains($this->data[$name], dirname($dest))) {
					    continue;
                    }
					if ($name && file_exists(exec('echo `pwd`') . '/'. $name)) {
					    continue;
                    }
					if (!in_array($dest, $destinations)) {
					    $destinations[] = $dest;
                    }
					unset(${$key}[$index]);
				}
			} else {
				$dest = $this->_clean_path($this->_fix_path($dest), 'right');
				if (!file_exists($dest)) {
				    continue;
                }
				if (!in_array($dest, $destinations)) {
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

		$errors = array('destinations' => array(), 'projects' => array("name" => array(), "paths" => array()));
		$success = array('destinations' => array(), 'projects' => array("name" => array(), "paths" => array()));

		foreach($destinations as $dest) {
			$dest = $this->_clean_path($this->_fix_path($dest), 'right');

			if (!file_exists($dest)) {
				$errors['destinations'][] = $dest;
			} else {
				$success['destinations'][] = $dest;

				if (!$silent) {
				    Hebe::message("\nLinking projects into `".$dest."`");
                }
				foreach($projects as $project) {
					$name = $project;
					$project = array_key_exists_nc($name, $this->data);

					if (!$project) {
						$errors['projects']['name'][$name] = $name;
						Hebe::message("\n   └ " . $name . ": Project not found");
					}
					else {
						$name = $project;
						$project = $this->data[$name];
						$success['projects']['name'][$name] = $name;

						$expected_platform = HebePlatform::getInfo($this->_clean_path($dest, 'right'));
						$platform = (!$platform_option) ? $expected_platform : $platform_option;

						$platform = $this->resolvePlatform($project, $platform);
						$platform = array_key_exists_nc($platform, $project);

						if (!$platform) {
						    $platform = array_key_exists_nc("custom", $project);
                        }

						if (!$platform) {
							$platform = (!$platform_option) ? $expected_platform : $platform_option;
							Hebe::message("   └ " . $name . ": No platform `".$platform."` found in the manifest.");
						} else {
							$working_path = $project[$platform];
							$manifest = $this->load_manifest($working_path);
							$requires = isset($manifest['requires']) ? $manifest['requires'] : array();

							$manifest = $this->load_manifest($working_path, $platform, $name);
							$nodes = $manifest['nodes'];

							if (!$silent) {
							    Hebe::message("   └ " . $name . " [".$platform."]");
                            }

							if (!$nodes) {
								Hebe::message("      └ No nodes found.\n");
								continue;
							}


							$requirements = array();
							if (isset($requires)) {
								foreach($requires as $require_project => $require_data) {
									if (!isset($this->data[$require_project])) {
										$requirements[] = $require_project;
									}
								}
							}

							if (count($requirements) && !$force) {
								Hebe::message("      └ skipped: unregistered requirements: " . implode(", ", $requirements));
							} else {
								foreach($nodes as $node => $paths) {
									$failed_nodes = array("count" => 0, "msg" => array());
									foreach($paths as $path) {

										if (isset($path['project'])) {
											if (!array_key_exists_nc($path['project'], $this->data)) {
												$failed_nodes["msg"][] = "            └ node failed: sub project not registered -> " . $path['project'];
												$failed_nodes["count"]++;
												continue;
											}

											$this->link_project(array(
											    "destinations" => array($dest),
												"projects"     => array($path['project']),
												"platform"     => $platform,
												"name"         => "",
												"force"        => $force,
												"silent"       => true
											));

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

										if (self::$config->get('backup_existing_when_linking')) {
											if (file_exists($destination)) {
												exec('rm -rf '.$destination.'.backup');
												exec('mv '.$destination.' '.$destination.'.backup');
											}
										}

										if (!file_exists($destination)) {
										    exec('mkdir -p '.$destination);
                                        }
										exec('rm -rf '.$destination);

										if (!file_exists($source)) {
											$failed_nodes["msg"][] = "            └ node failed: source not found -> " . $path['source'];
											$failed_nodes["count"]++;
										} else {
											if (symlink($source, $destination)) {
												$success['projects']['paths'][$destination] = $destination;
											} else {
												$errors['projects']['paths'][$destination] = $destination;
												$failed_nodes["msg"][] = "            └ node failed: link error -> " . $path['source'];
												$failed_nodes["count"]++;
											}
										}
									}

									if (!$silent) {
									    Hebe::message("      └ " . $node . ": " . ($failed_nodes["count"] == count($paths) ? "failed" : "ok"));
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

	public function edit_project($options = array("arguments" => array(), "platforms" => array(), "force" => false))
    {
		$projects = isset($options['arguments']) ? $options['arguments'] : false;
		$platforms = isset($options['platforms']) ? $options['platforms'] : false;

		$list = array();

		foreach ($projects as $project) {
			$project_name = array_key_exists_nc($project, $this->data);
			if (!$project_name) {
			    Hebe::message("Project `".$project."` not found.");
            } else {
				if (!count($platforms)) {
				    foreach($this->data[$project_name] as $working_path) {
				        $list[] = $working_path;
                    }
                } else {
					foreach($platforms as $platform) {
						$platform_name = array_key_exists_nc($platform, $this->data[$project_name]);

						if (!$platform_name) {
						    Hebe::message("No platform `".$platform."` found in the manifest of project `".$project."`.");
                        } else {
							$list[] = $this->data[$project_name][$platform];
						}
					}
				}
			}
		}

		if (count($list)) {
		    exec(self::$config->get('editor') . ' ' . implode(" ", $list));
        }
	}

	public function sync_projects($options = array("arguments" => array(), "update" => false)) {
		$projects = isset($options['arguments']) ? $options['arguments'] : false;
		$clean = isset($options['clean']) ? $options['clean'] : false;
		$update = isset($options['update']) ? $options['update'] : false;

		$data = $this->data;

		if (count($projects)) {
			$errors = array();
			$data = array();

			foreach($projects as $project) {
				$name = $project;
				$project = array_key_exists_nc($name, $this->data);

				if (!$project) {
				    $errors[] = $name;
                } else {
				    $data[$project] = $this->data[$project];
                }
			}

		}

		Hebe::message("\nResyncing ".count($data)." projects...");
		if (count($errors)) {
		    Hebe::message("Some have not been found: ".implode(", ", $errors));
        }

		if ($update) {
			Hebe::message("\nNote that with the update option, every project will get git/svn updated first. \nThis might take some time based on how many projects you have, speed connection, etc...");
		}

		Hebe::message("\n");

		foreach($data as $project => $nodes) {
			if (strtolower($project) === 'hebe') {
			    continue;
            }

			Hebe::message("Syncing project `".$project."`");
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

					$options = array("arguments" => array($node), "name" => $project, "force" => true, "silent" => true);
					$this->register($options);

					$status = "(synched) nodes have been ".$update_status."re-registered.";
				}

				Hebe::message("      └ [".$platform."]: ". $status);
			}

			Hebe::message("\n");
		}

		$this->save_projects_file();
	}

	public function getAlias($project = null, $platform = null)
    {
		if (!$project || !$platform) {
		    return false;
        }

		foreach($project as $node) {
			$manifest = $this->load_manifest($node);
			$aliases = isset($manifest['aliases']) ? $manifest['aliases'] : false;
			if (!$aliases) {
			    continue;
            }

			$alias = $aliases[0];
			$platform = array_key_exists_nc($platform, $alias);

			if (isset($alias[$platform])) {
			    return $alias[$platform];
            }
		}

		return null;
	}

	protected function resolvePlatform($project, $platform)
	{
		// if there is an alias for the platform use that
		$alias = $this->getAlias($project, $platform);
		if ($alias) {
			$platform = $alias;
		}
		if (($working_platform = array_key_exists_nc($platform, $project)) === false) {
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

	private function _clean_path($manifest, $position = 'both')
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

	private function _fix_path($path)
    {
		$first_chr = substr($path, 0, 1);
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
}
