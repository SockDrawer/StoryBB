<?php

/**
 * All adhoc tasks must implement this.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Task;

abstract class Adhoc
{

	/**
	 * @var array Holds the details for the task
	 */
	protected $_details;

	/**
	 * The constructor.
	 * @param array $details The details for the task
	 */
	public function __construct($details)
	{
		$this->_details = $details;
	}

	/**
	 * The function to actually execute a task
	 * @return mixed
	 */
	abstract public function execute();
}
