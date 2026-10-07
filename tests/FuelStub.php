<?php
/**
 * Minimal FuelPHP stand-ins so the Auth package can be exercised without the framework.
 */

class FuelException extends \Exception {}

class Fuel
{
	public static $is_cli = true;

	public static function clean_path($path)
	{
		if (defined('APPPATH'))
		{
			return str_replace(APPPATH, 'APPPATH'.DIRECTORY_SEPARATOR, $path);
		}

		return $path;
	}
}

class Errorhandler
{
	public static $notices = array();

	public static function notice($message)
	{
		static::$notices[] = $message;
	}

	public static function reset()
	{
		static::$notices = array();
	}
}

class Config
{
	protected static $items = array();
	protected static $loaded = array();

	public static function reset()
	{
		static::$items = array();
		static::$loaded = array();
	}

	public static function load($file, $group = null, $reload = false)
	{
		$group_name = $group === true ? $file : $group;

		if ( ! $reload and isset(static::$loaded[$file]))
		{
			return $group_name ? static::get($group_name, array()) : static::$items;
		}

		$path = null;
		$candidates = array();
		if (defined('APPPATH'))
		{
			$candidates[] = APPPATH.'config'.DIRECTORY_SEPARATOR.$file.'.php';
		}
		$candidates[] = dirname(__DIR__).DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.$file.'.php';

		foreach ($candidates as $candidate)
		{
			if (is_file($candidate))
			{
				$path = $candidate;
				break;
			}
		}

		if ($path === null)
		{
			return false;
		}

		$data = include $path;
		if ( ! is_array($data))
		{
			$data = array();
		}

		if ($group_name)
		{
			static::$items[$group_name] = $data;
		}
		else
		{
			static::$items = array_merge(static::$items, $data);
		}

		static::$loaded[$file] = true;

		return $group_name ? static::$items[$group_name] : $data;
	}

	public static function get($item, $default = null)
	{
		$cursor = static::$items;
		foreach (explode('.', (string) $item) as $part)
		{
			if (is_array($cursor) and array_key_exists($part, $cursor))
			{
				$cursor = $cursor[$part];
			}
			else
			{
				return $default;
			}
		}

		return $cursor;
	}

	public static function set($item, $value)
	{
		$parts = explode('.', (string) $item);
		$cursor =& static::$items;
		while (count($parts) > 1)
		{
			$part = array_shift($parts);
			if ( ! isset($cursor[$part]) or ! is_array($cursor[$part]))
			{
				$cursor[$part] = array();
			}
			$cursor =& $cursor[$part];
		}
		$cursor[$parts[0]] = $value;
	}
}

class Session
{
	public static $data = array();
	public static $rotations = 0;
	protected static $instance;

	public static function reset()
	{
		static::$data = array();
		static::$rotations = 0;
		static::$instance = null;
	}

	public static function get($key, $default = null)
	{
		return array_key_exists($key, static::$data) ? static::$data[$key] : $default;
	}

	public static function set($key, $value)
	{
		static::$data[$key] = $value;
	}

	public static function delete($key)
	{
		unset(static::$data[$key]);
	}

	public static function instance()
	{
		if (static::$instance === null)
		{
			static::$instance = new static;
		}

		return static::$instance;
	}

	public function rotate()
	{
		static::$rotations++;
	}

	public static function forge(array $config)
	{
		return new CookieSession($config);
	}
}

class CookieSession
{
	public $config;
	public $data = array();
	public $destroyed = false;

	public function __construct(array $config)
	{
		$this->config = $config;
	}

	public function get($key, $default = null)
	{
		return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
	}

	public function set($key, $value)
	{
		$this->data[$key] = $value;
	}

	public function destroy()
	{
		$this->data = array();
		$this->destroyed = true;
	}
}

class Input
{
	public static $post = array();
	public static $get = array();

	public static function reset()
	{
		static::$post = array();
		static::$get = array();
	}

	public static function post($key = null, $default = null)
	{
		if ($key === null)
		{
			return static::$post;
		}

		return array_key_exists($key, static::$post) ? static::$post[$key] : $default;
	}

	public static function get($key = null, $default = null)
	{
		if ($key === null)
		{
			return static::$get;
		}

		return array_key_exists($key, static::$get) ? static::$get[$key] : $default;
	}
}

class Str
{
	public static function ucwords($str)
	{
		return ucwords(str_replace('_', ' ', strtolower((string) $str)));
	}

	public static function random($type = 'alnum', $length = 8)
	{
		$pools = array(
			'alnum' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
			'sha1'  => '0123456789abcdef',
		);
		$pool = isset($pools[$type]) ? $pools[$type] : $pools['alnum'];
		$out = '';
		$max = strlen($pool) - 1;
		for ($i = 0; $i < $length; $i++)
		{
			$out .= $pool[random_int(0, $max)];
		}

		return $out;
	}

	public static function is_json($value)
	{
		if ( ! is_string($value) or $value === '')
		{
			return false;
		}

		json_decode($value);

		return json_last_error() === JSON_ERROR_NONE;
	}
}

class Inflector
{
	public static function get_namespace($class)
	{
		$class = (string) $class;
		if (($pos = strripos($class, '\\')) !== false)
		{
			return substr($class, 0, $pos + 1);
		}

		return '';
	}

	public static function denamespace($class)
	{
		$class = (string) $class;
		if (($pos = strripos($class, '\\')) !== false)
		{
			return substr($class, $pos + 1);
		}

		return $class;
	}
}

class Arr
{
	public static function get($array, $key, $default = null)
	{
		if ( ! is_array($array))
		{
			return $default;
		}

		if ($key === null)
		{
			return $array;
		}

		if (array_key_exists($key, $array))
		{
			return $array[$key];
		}

		foreach (explode('.', (string) $key) as $part)
		{
			if (is_array($array) and array_key_exists($part, $array))
			{
				$array = $array[$part];
			}
			else
			{
				return $default;
			}
		}

		return $array;
	}

	public static function merge(array $base, array $extra)
	{
		foreach ($extra as $key => $value)
		{
			if (is_int($key))
			{
				$base[] = $value;
			}
			elseif (isset($base[$key]) and is_array($base[$key]) and is_array($value))
			{
				$base[$key] = static::merge($base[$key], $value);
			}
			else
			{
				$base[$key] = $value;
			}
		}

		return $base;
	}
}

class Date
{
	public static $now = null;
	protected $timestamp;

	public static function forge($timestamp = null)
	{
		$date = new static;
		if ($timestamp === null)
		{
			$timestamp = static::$now !== null ? static::$now : time();
		}
		$date->timestamp = $timestamp;

		return $date;
	}

	public function get_timestamp()
	{
		return $this->timestamp;
	}
}

class Cli
{
	public static $output = array();
	public static $options = array();

	public static function reset()
	{
		static::$output = array();
		static::$options = array();
	}

	public static function write($text, $color = null)
	{
		static::$output[] = array($text, $color);
	}

	public static function option($key, $default = null)
	{
		return array_key_exists($key, static::$options) ? static::$options[$key] : $default;
	}
}

class DBUtil
{
	public static $tables = array();

	public static function reset()
	{
		static::$tables = array();
	}

	public static function table_exists($table)
	{
		return in_array($table, static::$tables, true);
	}

	public static function field_exists($table, $field)
	{
		return false;
	}
}

class DbExpr
{
	public $value;

	public function __construct($value)
	{
		$this->value = $value;
	}

	public function __toString()
	{
		return (string) $this->value;
	}
}

class DB
{
	public static $tables = array();
	public static $sequences = array();
	public static $last_connection = null;

	public static function reset()
	{
		static::$tables = array();
		static::$sequences = array();
		static::$last_connection = null;
	}

	public static function expr($value)
	{
		return new DbExpr($value);
	}

	public static function select_array($columns = null)
	{
		return new DbQuery('select', $columns);
	}

	public static function select($columns = null)
	{
		return new DbQuery('select', $columns);
	}

	public static function insert($table)
	{
		$query = new DbQuery('insert');
		$query->table = $table;

		return $query;
	}

	public static function update($table)
	{
		$query = new DbQuery('update');
		$query->table = $table;

		return $query;
	}

	public static function delete($table)
	{
		$query = new DbQuery('delete');
		$query->table = $table;

		return $query;
	}
}

class DbQuery
{
	public $type;
	public $columns;
	public $table;
	public $wheres = array();
	public $values = array();
	public $limit = null;
	public $as_object = false;

	public function __construct($type, $columns = null)
	{
		$this->type = $type;
		$this->columns = $columns;
	}

	public function from($table)
	{
		$this->table = $table;

		return $this;
	}

	public function set(array $values)
	{
		$this->values = $values;

		return $this;
	}

	public function limit($limit)
	{
		$this->limit = $limit;

		return $this;
	}

	public function as_object()
	{
		$this->as_object = true;

		return $this;
	}

	public function where($field, $op, $value)
	{
		$this->wheres[] = array('AND', $field, $op, $value);

		return $this;
	}

	public function or_where($field, $op, $value)
	{
		$this->wheres[] = array('OR', $field, $op, $value);

		return $this;
	}

	public function where_open()
	{
		return $this;
	}

	public function where_close()
	{
		return $this;
	}

	public function execute($connection = null)
	{
		DB::$last_connection = $connection;
		$table = $this->table;
		if ( ! isset(DB::$tables[$table]))
		{
			DB::$tables[$table] = array();
		}

		if ($this->type === 'insert')
		{
			$row = $this->values;
			if ( ! array_key_exists('id', $row))
			{
				DB::$sequences[$table] = (isset(DB::$sequences[$table]) ? DB::$sequences[$table] : 0) + 1;
				$row['id'] = DB::$sequences[$table];
			}
			else
			{
				DB::$sequences[$table] = max(isset(DB::$sequences[$table]) ? DB::$sequences[$table] : 0, (int) $row['id']);
			}
			DB::$tables[$table][] = $row;

			return array($row['id'], 1);
		}

		if ($this->type === 'update')
		{
			$count = 0;
			foreach (DB::$tables[$table] as $index => $row)
			{
				if ($this->matches($row))
				{
					DB::$tables[$table][$index] = array_merge($row, $this->values);
					$count++;
				}
			}

			return $count;
		}

		if ($this->type === 'delete')
		{
			$kept = array();
			$count = 0;
			foreach (DB::$tables[$table] as $row)
			{
				if ($this->matches($row))
				{
					$count++;
				}
				else
				{
					$kept[] = $row;
				}
			}
			DB::$tables[$table] = $kept;

			return $count;
		}

		$matched = array();
		foreach (DB::$tables[$table] as $row)
		{
			if ($this->matches($row))
			{
				$matched[] = $row;
			}
		}

		if ($this->is_count_query())
		{
			$matched = array(array('count' => count($matched)));
		}
		elseif ( ! $this->wants_all_columns())
		{
			$columns = (array) $this->columns;
			$projected = array();
			foreach ($matched as $row)
			{
				$item = array();
				foreach ($columns as $column)
				{
					if ($column instanceof DbExpr)
					{
						continue;
					}
					$item[$column] = array_key_exists($column, $row) ? $row[$column] : null;
				}
				$projected[] = $item;
			}
			$matched = $projected;
		}

		if ($this->limit !== null)
		{
			$matched = array_slice($matched, 0, $this->limit);
		}

		return new DbResult($matched, $this->as_object);
	}

	protected function wants_all_columns()
	{
		return $this->columns === null or $this->columns === '*' or $this->columns === array('*');
	}

	protected function is_count_query()
	{
		$columns = $this->columns instanceof DbExpr ? (string) $this->columns : $this->columns;

		return is_string($columns) and stripos($columns, 'count(') !== false;
	}

	public function matches(array $row)
	{
		if (empty($this->wheres))
		{
			return true;
		}

		$ok = null;
		foreach ($this->wheres as $index => $where)
		{
			list($glue, $field, $op, $value) = $where;
			$actual = array_key_exists($field, $row) ? $row[$field] : null;
			if ($op === '!=' or $op === '<>')
			{
				$part = $actual != $value;
			}
			else
			{
				$part = $actual == $value;
			}

			if ($ok === null or $index === 0)
			{
				$ok = $part;
			}
			elseif ($glue === 'OR')
			{
				$ok = $ok || $part;
			}
			else
			{
				$ok = $ok && $part;
			}
		}

		return (bool) $ok;
	}
}

class DbResult implements \Countable, \IteratorAggregate
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
		if (empty($this->rows))
		{
			return null;
		}

		$row = $this->rows[0];

		return $this->as_object ? (object) $row : $row;
	}

	public function count(): int
	{
		return count($this->rows);
	}

	public function getIterator(): \Traversable
	{
		return new \ArrayIterator($this->rows);
	}
}

function call_fuel_func_array($callback, array $args)
{
	return call_user_func_array($callback, $args);
}
