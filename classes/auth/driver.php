<?php

declare(strict_types=1);
/**
 * Fuel is a fast, lightweight, community driven PHP 5.4+ framework.
 *
 * @package    Fuel
 * @version    1.8.2
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2019 Fuel Development Team
 * @link       https://fuelphp.com
 */

namespace Auth;

abstract class Auth_Driver
{
    /**
     * @var	Auth_Driver
     * THIS MUST BE DEFINED IN THE BASE EXTENSION
     */
    // protected static $_instance = null;

    /**
     * @var	array	contains references if multiple were loaded
     * THIS MUST BE DEFINED IN THE BASE EXTENSION
     */
    // protected static $_instances = array();

    public static function forge(array $config = [])
    {
        throw new \AuthException('Driver must have a factory method extension.');
    }

    /**
     * Return a specific driver, or the default instance
     *
     * @param	string	driver id
     * @return	Auth_Driver
     */
    public static function instance($instance = null)
    {
        if ($instance === true) {
            return static::$_instances;
        }
        if ($instance !== null) {
            if (! array_key_exists($instance, static::$_instances)) {
                return false;
            }
            return static::$_instances[$instance];
        }

        return static::$_instance;
    }

    // ------------------------------------------------------------------------

    /**
     * @var	string	instance identifier
     */
    protected $id;

    /**
     * @var	array	given configuration array
     */
    protected array $config = [];

    protected function __construct(array $config)
    {
        $this->id = $config['id'];
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get driver instance ID
     *
     * @return string
     */
    public function get_id()
    {
        return (string) $this->id;
    }

    /**
     * Create or change config value
     *
     * @param	string
     * @param	mixed
     */
    public function set_config($key, $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Retrieve config value
     *
     * @param	string
     * @param	mixed	return when key doesn't exist
     * @return	mixed
     */
    public function get_config($key, $default = null)
    {
        return array_key_exists($key, $this->config) ? $this->config[$key] : $default;
    }

    /**
     * Whether this driver supports guest login
     *
     * @return  bool
     */
    public function guest_login()
    {
        return false;
    }
}

/* end of file driver.php */
