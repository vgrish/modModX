<?php

/**
 * The base class for modModX.
 */
class modModX extends modX
{

	public function __construct($configPath = '', $options = null, $driverOptions = null)
	{
		parent::__construct($configPath, $options, $driverOptions);
	}

}