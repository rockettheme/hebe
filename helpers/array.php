<?php

function array_contains($array, $item)
{
	return in_array($item, $array);
}

function array_include(&$array, $item)
{
	if (!array_contains($array, $item)) {
	    $array[] = $item;
    }

	return $array;
}

function array_erase(&$array, $item)
{
	foreach ($array as $i => $v) {
		if ($array[$i] === $item) {
		    array_splice($array, $i, 1);
        }
	}

	return $array;
}

function array_has($array, $key)
{
	return !empty($array) && array_key_exists($key, $array);
}

function array_has_r($array, $key)
{
	return !empty($array) && array_key_exists_r($key, $array);
}

function array_get($array, $key)
{
	return (!empty($array) && array_key_exists($key, $array)) ? $array[$key] : null;
}

function array_key_exists_nc($key, $search)
{
    if (!is_string($key)) {
        return false;
    }

	if (array_key_exists($key, $search)) {
        return $key;
    }

    if (!(is_string($key) && is_array($search) && count($search))) {
        return false;
    }

    $key = strtolower($key);
    foreach ($search as $k => $v) {
        if (strtolower($k) === $key) {
            return $k;
        }
    }

    return false;
}

function array_key_exists_r($needle, $haystack)
{
    $result = array_key_exists($needle, $haystack);
    if ($result) {
        return $result;
    }

    foreach ($haystack as $v) {
        if (is_array($v)) {
            $result = array_key_exists_r($needle, $v);
        }
        if ($result) {
            return $result;
        }
    }

    return $result;
}
