<?php

/**
 * A base interface for form elements to implement.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Form\Element;

use Latte\Engine;
use StoryBB\Form\Element\Inputtable;
use StoryBB\Form\Element\Traits;

class Buttons extends Traits\Base implements Inputtable
{
	use Traits\Labelable;
	use Traits\Chooseable;

	public function render(Engine $templater, array $rawdata): string
	{
		$rendercontext = [
			'name' => $this->name,
			'buttons' => $this->choices,
		];

		return $templater->renderToString('form/element/buttons.latte', $rendercontext);
	}
}
