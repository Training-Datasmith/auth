<?php

/**
 * Query result for PHP 7.4. Interface methods stay untyped so this file parses
 * and stays compatible with the untyped Countable, IteratorAggregate, and ArrayAccess contracts.
 */
class DbResult implements \Countable, \IteratorAggregate, \ArrayAccess
{
	protected $rows;
	protected $as_object;

	public function __construct(array $rows, $as_object = false)
	{
		$this->rows = array_values($rows);
		$this->as_object = $as_object;
	}

	public function current()
	{
		return $this->rowAt(0);
	}

	public function count()
	{
		return count($this->rows);
	}

	public function getIterator()
	{
		return new \ArrayIterator($this->rows);
	}

	public function offsetExists($offset)
	{
		return isset($this->rows[$offset]);
	}

	public function offsetGet($offset)
	{
		return $this->rowAt($offset);
	}

	public function offsetSet($offset, $value)
	{
		throw new \FuelException('Database results are read-only');
	}

	public function offsetUnset($offset)
	{
		throw new \FuelException('Database results are read-only');
	}

	protected function rowAt($offset)
	{
		if ( ! isset($this->rows[$offset]))
		{
			return null;
		}

		$row = $this->rows[$offset];

		return $this->as_object ? (object) $row : $row;
	}
}
