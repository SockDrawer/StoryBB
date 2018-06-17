<?php

/**
 * This file provides functionality for using SQLite 3 databases for short term caching.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Cache;

/**
 * SQLite Cache API class
 * @package cacheAPI
 */
class Sqlite extends API
{
	/**
	 * @var string The path to the current $cachedir directory.
	 */
	private $cachedir = null;
	private $cacheDB = null;
	private $cacheTime = 0;

	public function __construct()
	{

		parent::__construct();

		// Set our default cachedir.
		$this->setCachedir();

	}

	/**
	 * Returns the name for the cache method performed by this API. Likely to be a brand of sorts.
	 *
	 * @return string The name of the cache backend
	 */
	public function getName()
	{
		return 'SQLite';
	}

	/**
	 * {@inheritDoc}
	 */
	public function connect()
	{

		$database = $this->cachedir . '/' . 'SQLite3Cache.db3';
		$this->cacheDB = new SQLite3($database);
		$this->cacheDB->busyTimeout(1000);
		if (filesize($database) == 0)
		{
			$this->cacheDB->exec('CREATE TABLE cache (key text unique, value blob, ttl int);');
			$this->cacheDB->exec('CREATE INDEX ttls ON cache(ttl);');
		}
		$this->cacheTime = time();

	}

	/**
	 * Checks whether we can use the cache method performed by this API.
	 *
	 * @param boolean $test Test if this is supported or enabled.
	 * @return boolean Whether or not the cache is supported
	 */
	public function isSupported($test = false)
	{
		$supported = class_exists("SQLite3") && is_writable($this->cachedir);

		if ($test)
		{
			return $supported;
		}

		return parent::isSupported() && $supported;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
		$ttl = time() - $ttl;
		$query = 'SELECT value FROM cache WHERE key = \'' . $this->cacheDB->escapeString($key) . '\' AND ttl >= ' . $ttl . ' LIMIT 1';
		$result = $this->cacheDB->query($query);

		$value = null;
		while ($res = $result->fetchArray(SQLITE3_ASSOC))
		{
			$value = $res['value'];
		}

		return !empty($value) ? $value : null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{

		$ttl = $this->cacheTime + $ttl;
		$query = 'REPLACE INTO cache VALUES (\'' . $this->cacheDB->escapeString($key) . '\', \'' . $this->cacheDB->escapeString($value) . '\', ' . $this->cacheDB->escapeString($ttl) . ');';
		$result = $this->cacheDB->exec($query);

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{

		$query = 'DELETE FROM cache;';
		$result = $this->cacheDB->exec($query);

		return $result;

	}

	/**
	 * {@inheritDoc}
	 */
	public function cacheSettings(array &$config_vars)
	{
		global $context, $txt;

		$config_vars[] = $txt['cache_sqlite_settings'];
		$config_vars[] = array('cachedir_sqlite', $txt['cachedir_sqlite'], 'file', 'text', 36, 'cache_sqlite_cachedir');

		if (!isset($context['settings_post_javascript']))
		{
			$context['settings_post_javascript'] = '';
		}

		$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#cachedir_sqlite").prop("disabled", cache_type != "sqlite");
			});';
	}

	/**
	 * Sets the $cachedir or uses the StoryBB default $cachedir..
	 *
	 * @access public
	 *
	 * @param string $dir A valid path
	 *
	 * @return boolean If this was successful or not.
	 */
	public function setCachedir($dir = null)
	{
		global $cachedir_sqlite;

		// If its invalid, use StoryBB's.
		if (is_null($dir) || !is_writable($dir))
		{
			$this->cachedir = $cachedir_sqlite;
		}
		else
		{
			$this->cachedir = $dir;
		}
	}

}
