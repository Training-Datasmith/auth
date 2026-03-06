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

namespace Auth\Model;

class Auth_Permission extends \Orm\Model
{
    /**
     * @var  string  connection to use
     */
    protected static $_connection;

    /**
     * @var  string  write connection to use
     */
    protected static $_write_connection;

    /**
     * @var  string  table name to overwrite assumption
     */
    protected static $_table_name;

    /**
     * @var array	model properties
     */
    protected static $_properties = [
        'id'              => [],
        'area'            => [
            'label'       => 'auth_model_permission.area',
            'null'        => false,
            'validation'  => ['required', 'max_length' => [25]],
        ],
        'permission'      => [
            'label'       => 'auth_model_permission.permission',
            'null'        => false,
            'validation'  => ['required', 'max_length' => [25]],
        ],
        'description'     => [
            'label'       => 'auth_model_permission.description',
            'null'        => false,
            'validation'  => ['required', 'max_length' => [255]],
        ],
        'actions'         => [
            'data_type'   => 'serialize',
            'default'     => [],
            'null'        => false,
            'form'        => ['type' => false],
        ],
        'user_id'         => [
            'default'     => 0,
            'null'        => false,
            'form'        => ['type' => false],
        ],
        'created_at'      => [
            'default'     => 0,
            'null'        => false,
            'form'        => ['type' => false],
        ],
        'updated_at'      => [
            'default'     => 0,
            'null'        => false,
            'form'        => ['type' => false],
        ],
    ];

    /**
     * @var array	defined observers
     */
    protected static $_observers = [
        'Orm\\Observer_CreatedAt' => [
            'events' => ['before_insert'],
            'property' => 'created_at',
            'mysql_timestamp' => false,
        ],
        'Orm\\Observer_UpdatedAt' => [
            'events' => ['before_update'],
            'property' => 'updated_at',
            'mysql_timestamp' => false,
        ],
        'Orm\\Observer_Typing' => [
            'events' => ['after_load', 'before_save', 'after_save'],
        ],
        'Orm\\Observer_Self' => [
            'events' => ['before_insert', 'before_update'],
            'property' => 'user_id',
        ],
    ];

    /**
     * @var array	many_many relationships
     */
    protected static $_many_many = [
        'users' => [
            'key_from' => 'id',
            'model_to' => 'Model\\Auth_User',
            'key_to' => 'id',
            'table_through' => null,
            'key_through_from' => 'perms_id',
            'key_through_to' => 'user_id',
        ],
        'groups' => [
            'key_from' => 'id',
            'model_to' => 'Model\\Auth_Group',
            'key_to' => 'id',
            'table_through' => null,
            'key_through_from' => 'perms_id',
            'key_through_to' => 'group_id',
        ],
        'roles' => [
            'key_from' => 'id',
            'model_to' => 'Model\\Auth_Role',
            'key_to' => 'id',
            'table_through' => null,
            'key_through_from' => 'perms_id',
            'key_through_to' => 'role_id',
        ],
    ];

    /**
     * init the class
     */
    public static function _init(): void
    {
        // auth config
        \Config::load('ormauth', true);

        // set the connection this model should use
        static::$_connection = \Config::get('ormauth.db_connection');

        // set the write connection this model should use
        static::$_write_connection = \Config::get('ormauth.db_write_connection') ?: static::$_connection;

        // set the models table name
        static::$_table_name = \Config::get('ormauth.table_name', 'users').'_permissions';

        // set the relations through table names
        static::$_many_many['users']['table_through'] = \Config::get('ormauth.table_name', 'users').'_user_permissions';
        static::$_many_many['groups']['table_through'] = \Config::get('ormauth.table_name', 'users').'_group_permissions';
        static::$_many_many['roles']['table_through'] = \Config::get('ormauth.table_name', 'users').'_role_permissions';

        // model language file
        \Lang::load('auth_model_permission', true);
    }

    /**
     * before_insert observer event method
     */
    public function _event_before_insert(): void
    {
        // assign the user id that lasted updated this record
        $this->user_id = 0;
        foreach (\Auth::verified() as $driver) {
            if (($id = $driver->get_user_id()) !== false) {
                $this->user_id = $id[1];
                break;
            }
        }
    }

    /**
     * before_update observer event method
     */
    public function _event_before_update(): void
    {
        $this->_event_before_insert();
    }
}
