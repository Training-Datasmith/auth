<?php

/**
 * Query result for PHP 8.1+. Return types match the internal interfaces so
 * deprecations are not raised when warnings are exceptions.
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

	public function offsetExists(mixed $offset): bool
	{
		return isset($this->rows[$offset]);
	}

	public function offsetGet(mixed $offset): mixed
	{
		return $this->rowAt($offset);
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new \FuelException('Database results are read-only');
	}

	public function offsetUnset(mixed $offset): void
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
