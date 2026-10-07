<?php

/**
 * Query result for PHP 8.0. Countable and IteratorAggregate accept added return
 * types. ArrayAccess is still untyped on this version, so those methods stay untyped.
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

	public function count(): int
	{
		return count($this->rows);
	}

	public function getIterator(): \Traversable
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
		if ($offset === null)
		{
			$this->rows[] = $value;

			return;
		}

		$this->rows[$offset] = $value;
	}

	public function offsetUnset($offset)
	{
		unset($this->rows[$offset]);
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
