<?php

declare(strict_types=1);
/**
 * Fuel is a fast, lightweight, community driven PHP 5.4+ framework.
 *
 * @package    Fuel
 * @version    1.8.2
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010-2026 Fuel Development Team
 * @link       https://fuelphp.com
 */

namespace Auth;

abstract class Auth_Acl_Driver extends \Auth_Driver
{
    /**
     * @var	Auth_Driver	default instance
     */
    protected static $_instance;

    /**
     * @var	array	contains references if multiple were loaded
     */
    protected static $_instances = [];

    public static function forge(array $config = [])
    {
        // default driver id to driver name when not given
        ! array_key_exists('id', $config) && $config['id'] = $config['driver'];

        $class = \Inflector::get_namespace($config['driver']).'Auth_Acl_'.\Str::ucwords(\Inflector::denamespace($config['driver']));
        $driver = new $class($config);
        static::$_instances[$driver->get_id()] = $driver;
        is_null(static::$_instance) and static::$_instance = $driver;

        foreach ($driver->get_config('drivers', []) as $type => $drivers) {
            foreach ($drivers as $d => $custom) {
                $custom = is_int($d)
                    ? ['driver' => $custom]
                    : array_merge($custom, ['driver' => $d]);
                $class = 'Auth_'.\Str::ucwords($type).'_Driver';
                $class::forge($custom);
            }
        }

        return $driver;
    }

    /**
     * Parses a conditions string into it's array equivalent
     *
     * @rights	mixed	conditions array or string
     * @return	array	conditions array formatted as array(area, rights)
     *
     */
    public static function _parse_conditions($rights)
    {
        // assime it's already a rights array
        if (is_array($rights)) {
            return $rights;
        }
        // no clue what this is?
        if (! is_string($rights)) {
            throw new \InvalidArgumentException('Given rights where not formatted proppery. Formatting should be like area.right or area.[right, other_right]. Received: '.$rights);
        }

        // no clue what this is?
        if (!str_contains((string) $rights, '.')) {
            $rights .= '.';
        }

        [$area, $rights] = explode('.', $rights);

        if (str_starts_with($rights, '[') and str_ends_with($rights, ']')) {
            $rights = preg_split('#( *)?,( *)?#', trim(substr($rights, 1, -1)));
        }

        return [$area, $rights];
    }

    // ------------------------------------------------------------------------

	/**
	 * Check access rights, must match any of the given conditions
	 *
	 * @param	array	array of conditions as passed to has_access()
	 * @param	mixed	user or group identifier in the form of array(driver_id, id)
	 * @return	bool
	 */
	public function has_any_access($conditions, Array $entity)
	{
		foreach ($conditions as $condition)
		{
			// return true on the first hit
			if ($this->has_access($condition, $entity))
			{
				return true;
			}
		}

		// none were a hit
		return false;
	}

	/**
	 * Check access rights, must match all of the given conditions
	 *
	 * @param	array	array of conditions as passed to has_access()
	 * @param	mixed	user or group identifier in the form of array(driver_id, id)
	 * @return	bool
	 */
	public function has_all_access($conditions, Array $entity)
	{
		foreach ($conditions as $condition)
		{
			// return false on the first miss
			if ( ! $this->has_access($condition, $entity))
			{
				return false;
			}
		}

		// none were a miss
		return true;
	}

	// ------------------------------------------------------------------------

	/**
	 * Check access rights
	 *
	 * @param	mixed	condition to check for access
	 * @param	mixed	user or group identifier in the form of array(driver_id, id)
	 * @return	bool
	 */
	abstract public function has_access($condition, Array $entity);
}

/* end of file driver.php */
