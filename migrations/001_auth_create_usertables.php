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

namespace Fuel\Migrations;

include __DIR__.'/../normalizedrivertypes.php';

class Auth_Create_Usertables
{
    public function up()
    {
        // get the drivers defined
        $drivers = normalize_driver_types();

        if (in_array('Simpleauth', $drivers)) {
            // get the tablename
            \Config::load('simpleauth', true);
            $table = \Config::get('simpleauth.table_name', 'users');

            // make sure the correct connection is used
            $this->dbconnection('simpleauth');

            // only do this if it doesn't exist yet
            if (! \DBUtil::table_exists($table)) {
                // table users
                \DBUtil::create_table($table, [
                    'id' => ['type' => 'int', 'constraint' => 11, 'auto_increment' => true],
                    'username' => ['type' => 'varchar', 'constraint' => 50],
                    'password' => ['type' => 'varchar', 'constraint' => 255],
                    'group' => ['type' => 'int', 'constraint' => 11, 'default' => 1],
                    'email' => ['type' => 'varchar', 'constraint' => 255],
                    'last_login' => ['type' => 'varchar', 'constraint' => 25],
                    'login_hash' => ['type' => 'varchar', 'constraint' => 255],
                    'profile_fields' => ['type' => 'text'],
                    'created_at' => ['type' => 'int', 'constraint' => 11, 'default' => 0],
                    'updated_at' => ['type' => 'int', 'constraint' => 11, 'default' => 0],
                ], ['id']);

                // add a unique index on username and email
                \DBUtil::create_index($table, ['username', 'email'], 'username', 'UNIQUE');
            }
        } elseif (in_array('Ormauth', $drivers)) {
            // get the tablename
            \Config::load('ormauth', true);
            $table = \Config::get('ormauth.table_name', 'users');

            // make sure the correct connection is used
            $this->dbconnection('ormauth');

            if (! \DBUtil::table_exists($table)) {
                // get the simpleauth tablename, maybe that exists
                \Config::load('simpleauth', true);
                $simpletable = \Config::get('simpleauth.table_name', 'users');

                if (! \DBUtil::table_exists($simpletable)) {
                    // table users
                    \DBUtil::create_table($table, [
                        'id' => ['type' => 'int', 'constraint' => 11, 'auto_increment' => true],
                        'username' => ['type' => 'varchar', 'constraint' => 50],
                        'password' => ['type' => 'varchar', 'constraint' => 255],
                        'group_id' => ['type' => 'int', 'constraint' => 11, 'default' => 1],
                        'email' => ['type' => 'varchar', 'constraint' => 255],
                        'last_login' => ['type' => 'varchar', 'constraint' => 25],
                        'previous_login' => ['type' => 'varchar', 'constraint' => 25, 'default' => 0],
                        'login_hash' => ['type' => 'varchar', 'constraint' => 255],
                        'user_id' => ['type' => 'int', 'constraint' => 11, 'default' => 0],
                        'created_at' => ['type' => 'int', 'constraint' => 11, 'default' => 0],
                        'updated_at' => ['type' => 'int', 'constraint' => 11, 'default' => 0],
                    ], ['id']);

                    // add a unique index on username and email
                    \DBUtil::create_index($table, ['username', 'email'], 'username', 'UNIQUE');
                } else {
                    \DBUtil::rename_table($simpletable, $table);
                }
            }

            // run a check on required fields, and deal with missing ones. we might be migrating from simpleauth
            if (\DBUtil::field_exists($table, 'group')) {
                \DBUtil::modify_fields($table, [
                    'group' => ['name' => 'group_id', 'type' => 'int', 'constraint' => 11],
                ]);
            }
            if (! \DBUtil::field_exists($table, 'group_id')) {
                \DBUtil::add_fields($table, [
                    'group_id' => ['type' => 'int', 'constraint' => 11, 'default' => 1, 'after' => 'password'],
                ]);
            }
            if (! \DBUtil::field_exists($table, 'previous_login')) {
                \DBUtil::add_fields($table, [
                    'previous_login' => ['type' => 'varchar', 'constraint' => 25, 'default' => 0, 'after' => 'last_login'],
                ]);
            }
            if (! \DBUtil::field_exists($table, 'user_id')) {
                \DBUtil::add_fields($table, [
                    'user_id' => ['type' => 'int', 'constraint' => 11, 'default' => 0, 'after' => 'login_hash'],
                ]);
            }
            if (\DBUtil::field_exists($table, 'created')) {
                \DBUtil::modify_fields($table, [
                    'created' => ['name' => 'created_at', 'type' => 'int', 'constraint' => 11],
                ]);
            }
            if (! \DBUtil::field_exists($table, 'created_at')) {
                \DBUtil::add_fields($table, [
                    'created_at' => ['type' => 'int', 'constraint' => 11, 'default' => 0, 'after' => 'user_id'],
                ]);
            }
            if (\DBUtil::field_exists($table, 'updated')) {
                \DBUtil::modify_fields($table, [
                    'updated' => ['name' => 'updated_at', 'type' => 'int', 'constraint' => 11],
                ]);
            }
            if (! \DBUtil::field_exists($table, 'updated_at')) {
                \DBUtil::add_fields($table, [
                    'updated_at' => ['type' => 'int', 'constraint' => 11, 'default' => 0, 'after' => 'created_at'],
                ]);
            }

            // table users_meta
            \DBUtil::create_table($table.'_metadata', [
                'id' => ['type' => 'int', 'constraint' => 11, 'auto_increment' => true],
                'parent_id' => ['type' => 'int', 'constraint' => 11, 'default' => 0],
                'key' => ['type' => 'varchar', 'constraint' => 20],
                'value' => ['type' => 'varchar', 'constraint' => 100],
                'user_id' => ['type' => 'int', 'constraint' => 11, 'default' => 0],
                'created_at' => ['type' => 'int', 'constraint' => 11, 'default' => 0],
                'updated_at' => ['type' => 'int', 'constraint' => 11, 'default' => 0],
            ], ['id']);

            // convert profile fields to metadata, and drop the column
            if (\DBUtil::field_exists($table, 'profile_fields')) {
                $result = \DB::select('id', 'profile_fields')->from($table)->execute(\Config::get('ormauth.db_connection', null));
                foreach ($result as $row) {
                    $profile_fields = empty($row['profile_fields']) ? [] : unserialize($row['profile_fields']);
                    foreach ($profile_fields as $field => $value) {
                        if (! is_numeric($field)) {
                            \DB::insert($table.'_metadata')->set(
                                [
                                    'parent_id' => $row['id'],
                                    'key' => $field,
                                    'value' => $value,
                                ]
                            )->execute(\Config::get('ormauth.db_connection', null));
                        }
                    }
                }
                \DBUtil::drop_fields($table, [
                    'profile_fields',
                ]);
            }

            // table users_user_role
            \DBUtil::create_table($table.'_user_roles', [
                'user_id' => ['type' => 'int', 'constraint' => 11],
                'role_id' => ['type' => 'int', 'constraint' => 11],
            ], ['user_id', 'role_id']);

            // table users_user_perms
            \DBUtil::create_table($table.'_user_permissions', [
                'user_id' => ['type' => 'int', 'constraint' => 11],
                'perms_id' => ['type' => 'int', 'constraint' => 11],
            ], ['user_id', 'perms_id']);
        }

        // reset any DBUtil connection set
        $this->dbconnection(false);
    }

    public function down()
    {
        // get the drivers defined
        $drivers = normalize_driver_types();

        if (in_array('Simpleauth', $drivers)) {
            // get the tablename
            \Config::load('simpleauth', true);
            $table = \Config::get('simpleauth.table_name', 'users');

            // make sure the correct connection is used
            $this->dbconnection('simpleauth');

            // drop the admin_users table
            \DBUtil::drop_table($table);
        } elseif (in_array('Ormauth', $drivers)) {
            // get the tablename
            \Config::load('ormauth', true);
            $table = \Config::get('ormauth.table_name', 'users');

            // make sure the correct connection is used
            $this->dbconnection('ormauth');

            // drop the admin_users table
            \DBUtil::drop_table($table);

            // drop the admin_users_meta table
            \DBUtil::drop_table($table.'_metadata');

            // drop the admin_users_user_role table
            \DBUtil::drop_table($table.'_user_roles');

            // drop the admin_users_user_perms table
            \DBUtil::drop_table($table.'_user_permissions');
        }

        // reset any DBUtil connection set
        $this->dbconnection(false);
    }

    /**
     * check if we need to override the db connection for auth tables
     */
    protected function dbconnection($type = null)
    {
        static $connection;

        switch ($type) {
            // switch to the override connection
            case 'simpleauth':
            case 'ormauth':
                if ($connection = \Config::get($type.'.db_connection', null)) {
                    \DBUtil::set_connection($connection);
                }
                break;

                // switch back to the configured migration connection, or the default one
            case false:
                if ($connection) {
                    \DBUtil::set_connection(\Config::get('migrations.connection', null));
                }
                break;

            default:
                // noop
        }
    }
}
