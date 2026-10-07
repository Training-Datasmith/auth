<?php

use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class TestCase extends PhpUnitTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->resetRuntime();
	}

	protected function resetRuntime()
	{
		$this->setStatic('Auth\\Auth', '_instance', null);
		$this->setStatic('Auth\\Auth', '_instances', array());
		$this->setStatic('Auth\\Auth', '_verified', array());
		$this->setStatic('Auth\\Auth', '_verify_multiple', false);
		$this->setStatic('Auth\\Auth', '_drivers', array(
			'member' => 'group',
			'has_access' => 'acl',
			'has_any_access' => 'acl',
			'has_all_access' => 'acl',
		));

		foreach (array('Auth\\Auth_Login_Driver', 'Auth\\Auth_Group_Driver', 'Auth\\Auth_Acl_Driver') as $class)
		{
			$this->setStatic($class, '_instance', null);
			$this->setStatic($class, '_instances', array());
		}
		$this->setStatic('Auth\\Auth_Login_Driver', 'remember_me', null);
		$this->setStatic('Auth\\Auth_Opauth', 'provider_table', null);
		$this->setStatic('Auth\\Auth_Opauth', 'db_connection', null);
		$this->setStatic('Fuel\\Tasks\\Simple2orm', 'data', array());

		\DB::reset();
		\DBUtil::reset();
		\Session::reset();
		\Input::reset();
		\Errorhandler::reset();
		\Cli::reset();
		\Date::$now = 1700000000;
		\Fuel::$is_cli = true;

		if (class_exists('Opauth', false))
		{
			\Opauth::$valid = true;
			\Opauth::$reason = 'bad signature';
		}

		\Config::reset();
		\Config::load('auth', true);
		\Config::load('simpleauth', true);
		\Config::load('ormauth', true);
		\Config::load('opauth', true);
		\Config::set('auth.iterations', 2);
		\Config::set('auth.salt', 'test-salt');
		\Config::set('auth.verify_multiple_logins', false);
		\Config::set('auth.login_type', 'both');
		\Config::set('simpleauth.login_hash_salt', 'hash-salt');
		\Config::set('simpleauth.groups', $this->groupConfig());
		\Config::set('simpleauth.roles', $this->roleConfig());
		\Config::set('simpleauth.guest_login', true);
		\Config::set('simpleauth.multiple_logins', false);
		\Config::set('simpleauth.remember_me.enabled', false);
		\Config::set('simpleauth.db_connection', null);
		\Config::set('simpleauth.table_name', 'users');
		\Config::set('simpleauth.table_columns', null);

		\Auth\Auth_Login_Simpleauth::_init();
		\Auth\Auth_Group_Simplegroup::_init();
		\Auth\Auth_Acl_Simpleacl::_init();
	}

	protected function groupConfig()
	{
		return array(
			-1  => array('name' => 'Banned', 'roles' => array('banned')),
			0   => array('name' => 'Guests', 'roles' => array()),
			1   => array('name' => 'Users', 'roles' => array('user')),
			50  => array('name' => 'Moderators', 'roles' => array('user', 'moderator')),
			100 => array('name' => 'Administrators', 'roles' => array('user', 'moderator', 'admin')),
		);
	}

	protected function roleConfig()
	{
		return array(
			'#'          => array('website' => array('read')),
			'user'       => array('comments' => array('create', 'read')),
			'moderator'  => array('comments' => array('update', 'delete'), 'posts' => array('read')),
			'banned'     => false,
			'admin'      => true,
		);
	}

	protected function refreshSimpleDrivers()
	{
		\Auth\Auth_Group_Simplegroup::_init();
		\Auth\Auth_Acl_Simpleacl::_init();
	}

	protected function forgeSimpleAuth(array $config = array())
	{
		$config = array_merge(array('driver' => 'Simpleauth'), $config);

		return \Auth\Auth::forge($config);
	}

	/**
	 * Forge SimpleAuth and publish it as the default instance, which is what Auth::_init() does.
	 * forge() itself leaves the default null.
	 */
	protected function bootSimpleAuth(array $config = array())
	{
		$driver = $this->forgeSimpleAuth($config);
		if ($this->getStatic('Auth\\Auth', '_instance') === null)
		{
			$this->setStatic('Auth\\Auth', '_instance', $driver);
		}

		return $driver;
	}

	protected function setStatic($class, $property, $value)
	{
		$reflection = new ReflectionProperty($class, $property);
		$reflection->setAccessible(true);
		$reflection->setValue(null, $value);
	}

	protected function getStatic($class, $property)
	{
		$reflection = new ReflectionProperty($class, $property);
		$reflection->setAccessible(true);

		return $reflection->getValue(null);
	}
}
