<?php

namespace Hebe;

class HebePlatform
{
    /** @var bool */
	public const HAS = 1;
    /** @var bool */
	public const DOESNT_HAVE = 0;
    /** @var string */
	public const CUSTOM_PLATFORM = 'custom';

    /** @var string[] */
	protected static $fallbacks = [
        'joomla'   => 'joomla41',
        'joomla41'   => 'joomla40',
        'joomla40'   => 'joomla4',
        'joomla4'   => 'joomla310',
        'joomla310' => 'joomla39',
        'joomla39' => 'joomla38',
        'joomla38' => 'joomla37',
        'joomla37' => 'joomla36',
		'joomla36' => 'joomla35',
		'joomla35' => 'joomla34',
		'joomla34' => 'joomla33',
		'joomla33' => 'joomla32',
		'joomla32' => 'joomla31',
		'joomla31' => 'joomla30',
        'joomla30' => 'joomla3',
	];

    /** @var array[] */
	protected static $fingerprints = [
		'grav' => [
		    self::DOESNT_HAVE => [],
		    self::HAS => [
		        '/bin/grav',
		        '/system/src/Grav',
		        '/user/plugins'
		    ]
		 ],
		'joomla' => [
            self::DOESNT_HAVE => [],
			self::HAS => [
				'/administrator/components',
				'/components',
				'/modules',
				'/plugins',
				'/templates'
			]
		],
		'wordpress' => [
			self::DOESNT_HAVE => [],
			self::HAS => [
				'/wp-admin',
				'/wp-content',
				'/wp-includes',
			]
		],
	];

	public static function getInfo(string $path): string
	{
		$current_platform = self::CUSTOM_PLATFORM;
		foreach (self::$fingerprints as $platform => $tests) {
			$matched_platform = true;
			foreach ($tests as $test => $testpaths) {
                $test = (bool)$test;
				foreach ($testpaths as $testpath) {
					if ($test !== file_exists($path . $testpath)) {
						$matched_platform = false;
						break 2;
					}
				}
			}
			if ($matched_platform) {
				$current_platform = $platform;
				break;
			}
		}

		return $current_platform;
	}

	public static function getFallback(string $platform): ?string
	{
        return self::$fallbacks[$platform] ?? null;
    }
}
