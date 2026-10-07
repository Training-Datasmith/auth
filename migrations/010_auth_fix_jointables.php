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

class Auth_Fix_Jointables
{
    public function up()
    {
        // get the drivers defined
        $drivers = normalize_driver_types();

        if (in_array('Ormauth', $drivers)) {
            // get the tablename
            \Config::load('ormauth', true);
            $basetable = \Config::get('ormauth.table_name', 'users');

            // make sure the correct connection is used
            $this->dbconnection('ormauth');

            \DBUtil::drop_index($basetable.'_user_permissions', 'primary');
            \DBUtil::add_fields($basetable.'_user_permissions', [
                'id' => ['type' => 'int', 'constraint' => 11, 'auto_increment' => true, 'primary_key' => true, 'first' => true],
            ]);

            \DBUtil::drop_index($basetable.'_group_permissions', 'primary');
            \DBUtil::add_fields($basetable.'_group_permissions', [
                'id' => ['type' => 'int', 'constraint' => 11, 'auto_increment' => true, 'primary_key' => true, 'first' => true],
            ]);

            \DBUtil::drop_index($basetable.'_role_permissions', 'primary');
            \DBUtil::add_fields($basetable.'_role_permissions', [
                'id' => ['type' => 'int', 'constraint' => 11, 'auto_increment' => true, 'primary_key' => true, 'first' => true],
            ]);
        }

        // reset any DBUtil connection set
        $this->dbconnection(false);
    }

    public function down()
    {
        // get the drivers defined
        $drivers = normalize_driver_types();

        if (in_array('Ormauth', $drivers)) {
            // get the tablename
            \Config::load('ormauth', true);
            $basetable = \Config::get('ormauth.table_name', 'users');

            // make sure the correct connection is used
            $this->dbconnection('ormauth');

            \DBUtil::drop_fields($basetable.'_user_permissions', [
                'id',
            ]);
            \DBUtil::create_index($basetable.'_user_permissions', ['user_id', 'perms_id'], '', 'PRIMARY');

            \DBUtil::drop_fields($basetable.'_group_permissions', [
                'id',
            ]);
            \DBUtil::create_index($basetable.'_group_permissions', ['group_id', 'perms_id'], '', 'PRIMARY');

            \DBUtil::drop_fields($basetable.'_role_permissions', [
                'id',
            ]);
            \DBUtil::create_index($basetable.'_role_permissions', ['role_id', 'perms_id'], '', 'PRIMARY');
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
