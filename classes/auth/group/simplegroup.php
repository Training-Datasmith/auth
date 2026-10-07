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

class Auth_Group_Simplegroup extends \Auth_Group_Driver
{
    protected static $_valid_groups = [];

    public static function _init(): void
    {
        static::$_valid_groups = array_keys(\Config::get('simpleauth.groups', []));
    }

    protected array $config = [
        'drivers' => ['acl' => ['Simpleacl']],
    ];

    public function groups()
    {
        return static::$_valid_groups;
    }

    public function member($group, $user = null)
    {
        if ($user === null) {
            $groups = \Auth::instance()->get_groups();
        } else {
            $groups = \Auth::instance($user[0])->get_groups();
        }

        if (! $groups || ! in_array((int) $group, static::$_valid_groups)) {
            return false;
        }

        return in_array([$this->id, $group], $groups);
    }

    public function get_name($group = null)
    {
        if ($group === null) {
            if (! $login = \Auth::instance() or ! is_array($groups = $login->get_groups())) {
                return false;
            }
            $group = $groups[0][1] ?? null;
        }

        return \Config::get('simpleauth.groups.'.$group.'.name', null);
    }

    public function get_roles($group = null)
    {
        // When group is empty, attempt to get groups from a current login
        if ($group === null) {
            if (! $login = \Auth::instance()
                or ! is_array($groups = $login->get_groups())
                or ! isset($groups[0][1])) {
                return [];
            }
            $group = $groups[0][1];
        } elseif (! in_array((int) $group, static::$_valid_groups)) {
            return [];
        }

        $groups = \Config::get('simpleauth.groups');
        return $groups[(int) $group]['roles'] ?? [];
    }
}

/* end of file simplegroup.php */
