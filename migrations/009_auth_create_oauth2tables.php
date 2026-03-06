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

namespace Fuel\Migrations;

include __DIR__.'/../normalizedrivertypes.php';

class Auth_Create_Oauth2tables
{
    public function up()
    {
        // get the drivers defined
        $drivers = normalize_driver_types();

        if (in_array('Simpleauth', $drivers)) {
            // get the tablename
            \Config::load('simpleauth', true);
            $basetable = \Config::get('simpleauth.table_name', 'users');

            // make sure the correct connection is used
            $this->dbconnection('simpleauth');
        } elseif (in_array('Ormauth', $drivers)) {
            // get the tablename
            \Config::load('ormauth', true);
            $basetable = \Config::get('ormauth.table_name', 'users');

            // make sure the correct connection is used
            $this->dbconnection('ormauth');
        } else {
            $basetable = 'users';
        }

        \DBUtil::create_table($basetable.'_clients', [
            'id' => ['type' => 'int', 'constraint' => 11, 'auto_increment' => true],
            'name' => ['type' => 'varchar', 'constraint' => 32, 'default' => ''],
            'client_id' => ['type' => 'varchar', 'constraint' => 32, 'default' => ''],
            'client_secret' => ['type' => 'varchar', 'constraint' => 32, 'default' => ''],
            'redirect_uri' => ['type' => 'varchar', 'constraint' => 255, 'default' => ''],
            'auto_approve' => [ 'type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'autonomous' => [ 'type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'status' => [ 'type' => 'enum', 'constraint' => '"development","pending","approved","rejected"', 'default' => 'development'],
            'suspended' => [ 'type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'notes' => ['type' => 'tinytext'],
        ], ['id']);
        \DBUtil::create_index($basetable.'_clients', 'client_id', 'client_id', 'UNIQUE');

        \DBUtil::create_table(
            $basetable.'_sessions',
            [
                'id' => ['type' => 'int', 'constraint' => 11, 'auto_increment' => true],
                'client_id' => ['type' => 'varchar', 'constraint' => 32, 'default' => ''],
                'redirect_uri' => ['type' => 'varchar', 'constraint' => 255, 'default' => ''],
                'type_id' => ['type' => 'varchar', 'constraint' => 64],
                'type' => [ 'type' => 'enum', 'constraint' => '"user","auto"', 'default' => 'user'],
                'code' => ['type' => 'text'],
                'access_token' => ['type' => 'varchar', 'constraint' => 50, 'default' => ''],
                'stage' => [ 'type' => 'enum', 'constraint' => '"request","granted"', 'default' => 'request'],
                'first_requested' => [ 'type' => 'int', 'constraint' => 11],
                'last_updated' => [ 'type' => 'int', 'constraint' => 11],
                'limited_access' => [ 'type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            ],
            ['id'],
            true,
            false,
            null,
            [
                [
                    'constraint' => 'oauth_sessions_ibfk_1',
                    'key' => 'client_id',
                    'reference' => [
                        'table' => $basetable.'_clients',
                        'column' => 'client_id',
                    ],
                    'on_delete' => 'CASCADE',
                ],
            ]
        );

        \DBUtil::create_table($basetable.'_scopes', [
            'id' => ['type' => 'int', 'constraint' => 11, 'auto_increment' => true],
            'scope' => ['type' => 'varchar', 'constraint' => 64, 'default' => ''],
            'name' => ['type' => 'varchar', 'constraint' => 64, 'default' => ''],
            'description' => ['type' => 'varchar', 'constraint' => 255, 'default' => ''],
        ], ['id']);
        \DBUtil::create_index($basetable.'_scopes', 'scope', 'scope', 'UNIQUE');

        \DBUtil::create_table(
            $basetable.'_sessionscopes',
            [
                'id' => ['type' => 'int', 'constraint' => 11, 'auto_increment' => true],
                'session_id' => ['type' => 'int', 'constraint' => 11],
                'access_token' => ['type' => 'varchar', 'constraint' => 50, 'default' => ''],
                'scope' => ['type' => 'varchar', 'constraint' => 64, 'default' => ''],
            ],
            ['id'],
            true,
            false,
            null,
            [
                [
                    'constraint' => 'oauth_sessionscopes_ibfk_1',
                    'key' => 'scope',
                    'reference' => [
                        'table' => $basetable.'_scopes',
                        'column' => 'scope',
                    ],
                ],
                [
                    'constraint' => 'oauth_sessionscopes_ibfk_2',
                    'key' => 'session_id',
                    'reference' => [
                        'table' => $basetable.'_sessions',
                        'column' => 'id',
                    ],
                    'on_delete' => 'CASCADE',
                ],
            ]
        );
        \DBUtil::create_index($basetable.'_sessionscopes', 'session_id', 'session_id');
        \DBUtil::create_index($basetable.'_sessionscopes', 'access_token', 'access_token');
        \DBUtil::create_index($basetable.'_sessionscopes', 'scope', 'scope');

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
            $basetable = \Config::get('simpleauth.table_name', 'users');

            // make sure the correct connection is used
            $this->dbconnection('simpleauth');
        } elseif (in_array('Ormauth', $drivers)) {
            // get the tablename
            \Config::load('ormauth', true);
            $basetable = \Config::get('ormauth.table_name', 'users');

            // make sure the correct connection is used
            $this->dbconnection('ormauth');
        } else {
            $basetable = 'users';
        }

        \DBUtil::drop_table($basetable.'_sessionscopes');
        \DBUtil::drop_table($basetable.'_sessions');
        \DBUtil::drop_table($basetable.'_scopes');
        \DBUtil::drop_table($basetable.'_clients');

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
