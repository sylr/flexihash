<?php

/**
 * A simple consistent hashing implementation with pluggable hash algorithms.
 *
 * @author Paul Annesley
 * @package Flexihash
 * @licence http://www.opensource.org/licenses/mit-license.php
 */
class Flexihash
{

	/**
	 * The number of positions to hash each target to.
	 *
	 * @var int
	 */
	private $_replicas = 64;

	/**
	 * The hash algorithm, encapsulated in a Flexihash_Hasher implementation.
	 * @var object Flexihash_Hasher
	 */
	private $_hasher;

	/**
	 * Internal counter for current number of targets.
	 * @var int
	 */
	private $_targetCount = 0;

	/**
	 * Internal map of positions (hash outputs) to targets
	 * @var array { position => target, ... }
	 */
	private $_positionToTarget = array();

	/**
	 * Internal map of targets to lists of positions that target is hashed to.
	 * @var array { target => [ position, position, ... ], ... }
	 */
	private $_targetToPositions = array();

	/**
	 * Sorted array of positions
	 * @var array [ positions, positions, ...]
	 */
	private $_positions = null;

	/**
	 * Internal counter for positions
	 * @var int
	 */
	private $_positionCount = 0;

	/**
	 * Constructor
	 * @param object $hasher Flexihash_Hasher
	 * @param int $replicas Amount of positions to hash each target to.
	 */
	public function __construct(Flexihash_Hasher $hasher = null, $replicas = null)
	{
		$this->_hasher = $hasher ? $hasher : new Flexihash_Crc32Hasher();
		if (!empty($replicas)) $this->_replicas = $replicas;
	}

	/**
	 * Add a target.
	 * @param string $target
         * @param float $weight
	 * @chainable
	 */
	public function addTarget($target, $weight=1)
	{
		if (isset($this->_targetToPositions[$target]))
		{
			throw new Flexihash_Exception("Target '$target' already exists.");
		}

		$this->_targetToPositions[$target] = array();

		// hash the target into multiple positions
		for ($i = 0; $i < round($this->_replicas*$weight); $i++)
		{
			$position = $this->_hasher->hash($target . $i);
			$this->_positionToTarget[$position] = $target; // lookup
			$this->_targetToPositions[$target] []= $position; // target removal
		}

		$this->_positions = null;
		$this->_targetCount++;

		return $this;
	}

	/**
	 * Add a list of targets.
	 * @param array $targets
         * @param float $weight
	 * @chainable
	 */
	public function addTargets($targets, $weight=1)
	{
		foreach ($targets as $target)
		{
			$this->addTarget($target,$weight);
		}

		return $this;
	}

	/**
	 * Remove a target.
	 * @param string $target
	 * @chainable
	 */
	public function removeTarget($target)
	{
		if (!isset($this->_targetToPositions[$target]))
		{
			throw new Flexihash_Exception("Target '$target' does not exist.");
		}

		foreach ($this->_targetToPositions[$target] as $position)
		{
			unset($this->_positionToTarget[$position]);
		}

		unset($this->_targetToPositions[$target]);

		$this->_positions = null;
		$this->_targetCount--;

		return $this;
	}

	/**
	 * A list of all potential targets
	 * @return array
	 */
	public function getAllTargets()
	{
		return array_keys($this->_targetToPositions);
	}

	/**
	 * Looks up the target for the given resource.
	 * @param string $resource
	 * @param int $replicas Number of wanted replicas, if greater than 1, the method will randomly
	 *                      return one of the $replicas number of target selected for the resource.
	 * @return string
	 */
	public function lookup($resource, $replicas = 1)
	{
		$targets = $this->lookupList($resource, $replicas);
		if (empty($targets)) throw new Flexihash_Exception('No targets exist');
		return $targets[array_rand($targets)];
	}

	/**
	 * Get a list of targets for the resource, in order of precedence.
	 * Up to $requestedCount targets are returned, less if there are fewer in total.
	 *
	 * @param string $resource
	 * @param int $requestedCount The length of the list to return
	 * @return array List of targets
	 */
	public function lookupList($resource, $requestedCount)
	{
		if (!$requestedCount)
			throw new Flexihash_Exception('Invalid count requested');

		switch ($this->_targetCount)
		{
			// handle no targets
			case 0: return array();
			// optimize single target
			case 1: return array_unique(array_values($this->_positionToTarget));
		}

		// hash resource to a position
		$resourcePosition = $this->_hasher->hash($resource);

		$this->compile();
		$results   = array();
		$positions = $this->_positions;
		$high      = $this->_positionCount - 1;
		$low       = 0;
		$notfound  = false;

		// inary search of the first position greater than resource position
		while ($high >= $low || $notfound = true)
		{
			$probe = (int)floor(($high + $low) / 2);

			if (false === $notfound && $positions[$probe] <= $resourcePosition)
			{
				$low = $probe + 1;
			}
			elseif (0 === $probe || $positions[$probe - 1] < $resourcePosition || true === $notfound)
			{
				if ($notfound)
				{
					// if not found is true, it means binary search failed to find any position greater
					// than ressource position, in this case, the last position is the bigest lower
					// position and first position is the next one after cycle
					$probe = 0;
				}

				$results[] = $this->_positionToTarget[$positions[$probe]];

				if ($requestedCount > 1)
				{
					for ($i = $requestedCount - 1; $i > 0; $i--)
					{
						if (++$probe > $this->_positionCount - 1)
						{
							$probe = 0; // cycle
						}
						$results[] = $this->_positionToTarget[$positions[$probe]];
					}
				}

				break;
			}
			else
			{
				$high = $probe - 1;
			}
		}

		return array_unique($results);
	}

	public function __toString()
	{
		return sprintf(
			'%s{targets:[%s]}',
			get_class($this),
			implode(',', $this->getAllTargets())
		);
	}

	/**
	 * Sorts the internal positions and pre-count them
	 */
	public function compile()
	{
		if (null === $this->_positions)
		{
			ksort($this->_positionToTarget);
			$this->_positions = array_keys($this->_positionToTarget);
			$this->_positionCount = count($this->_positions);
		}
	}

}

