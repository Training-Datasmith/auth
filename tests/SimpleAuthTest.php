<?php

class SimpleAuthTest extends TestCase
{
	protected function userRow($id)
	{
		foreach (\DB::$tables['users'] as $row)
		{
			if ($row['id'] == $id)
			{
				return $row;
			}
		}

		return null;
	}

	public function test_create_user_validates_input_and_stores_a_salted_hash()
	{
		$driver = $this->forgeSimpleAuth();

		try
		{
			$driver->create_user('  ', 'secret', 'ada@example.com');
			$this->fail('blank username should be rejected');
		}
		catch (\SimpleUserUpdateException $e)
		{
			$this->assertSame(1, $e->getCode());
		}

		try
		{
			$driver->create_user('ada', 'secret', 'not-an-email');
			$this->fail('invalid email should be rejected');
		}
		catch (\SimpleUserUpdateException $e)
		{
			$this->assertSame(1, $e->getCode());
		}

		$id = $driver->create_user('  ada  ', '  secret  ', '  ada@example.com  ', '50', array(
			'city' => 'London',
			'address' => array('line' => '1 Fleet'),
		));

		$this->assertSame(1, $id);
		$row = $this->userRow($id);
		$this->assertSame('ada', $row['username']);
		$this->assertSame('ada@example.com', $row['email']);
		$this->assertSame(50, $row['group']);
		$this->assertSame(16, strlen($row['salt']));
		$this->assertSame($driver->hash_password('secret'.$row['salt']), $row['password']);
		$this->assertNotSame($driver->hash_password('secret'), $row['password']);
		$this->assertSame(0, $row['last_login']);
		$this->assertSame('', $row['login_hash']);
		$this->assertSame(1700000000, $row['created_at']);
		$this->assertSame(array('city' => 'London', 'address' => array('line' => '1 Fleet')), unserialize($row['profile_fields']));
	}

	public function test_create_user_rejects_duplicate_username_and_email()
	{
		$driver = $this->forgeSimpleAuth();
		$driver->create_user('ada', 'secret', 'ada@example.com');

		try
		{
			$driver->create_user('ada', 'other', 'other@example.com');
			$this->fail('duplicate username should be rejected');
		}
		catch (\SimpleUserUpdateException $e)
		{
			$this->assertSame(3, $e->getCode());
		}

		try
		{
			$driver->create_user('other', 'other', 'ada@example.com');
			$this->fail('duplicate email should be rejected');
		}
		catch (\SimpleUserUpdateException $e)
		{
			$this->assertSame(2, $e->getCode());
		}
	}

	public function test_validate_user_respects_login_type_and_post_fallback()
	{
		$driver = $this->forgeSimpleAuth();
		$driver->create_user('ada', 'secret', 'ada@example.com');

		\Input::$post = array();
		$this->assertFalse($driver->validate_user('', ''));
		$this->assertFalse($driver->validate_user());
		$this->assertFalse($driver->validate_user('ada', ''));
		$this->assertFalse($driver->validate_user('ada', 'wrong'));
		$this->assertIsArray($driver->validate_user('ada', 'secret'));
		$this->assertIsArray($driver->validate_user('ada@example.com', 'secret'));
		$this->assertIsArray($driver->validate_user('  ada  ', '  secret '));

		\Config::set('auth.login_type', 'username');
		$this->assertFalse($driver->validate_user('ada@example.com', 'secret'));
		$this->assertIsArray($driver->validate_user('ada', 'secret'));

		\Config::set('auth.login_type', 'email');
		$this->assertFalse($driver->validate_user('ada', 'secret'));
		$this->assertIsArray($driver->validate_user('ada@example.com', 'secret'));

		\Config::set('auth.login_type', 'both');
		\Input::$post = array('username' => 'ada', 'password' => 'secret');
		$this->assertIsArray($driver->validate_user());

		\Config::set('simpleauth.username_post_key', 'user');
		\Config::set('simpleauth.password_post_key', 'pass');
		\Input::$post = array('user' => 'ada', 'pass' => 'secret');
		$this->assertIsArray($driver->validate_user(' ', ' '));
	}

	public function test_explicit_table_columns_and_connection_are_used()
	{
		\Config::set('simpleauth.table_columns', array('id', 'username', 'password', 'salt', 'email', 'group', 'login_hash', 'profile_fields'));
		\Config::set('simpleauth.db_connection', 'auth_db');
		$driver = $this->forgeSimpleAuth();
		$driver->create_user('ada', 'secret', 'ada@example.com');

		$this->assertIsArray($driver->validate_user('ada', 'secret'));
		$this->assertSame('auth_db', \DB::$last_connection);
	}

	public function test_login_logout_and_session_hash()
	{
		$driver = $this->forgeSimpleAuth();
		$driver->create_user('ada', 'secret', 'ada@example.com', 1, array('city' => 'London'));

		$this->assertFalse($driver->login('ada', 'nope'));
		$this->assertSame(0, \Session::$rotations);
		$this->assertNull(\Session::get('username'));
		$this->assertSame('guest', $driver->get_screen_name());
		$this->assertSame(array(), \Auth\Auth::verified());

		$this->assertTrue($driver->login('ada', 'secret'));
		$expected = sha1('hash-saltada1700000000');
		$this->assertSame('ada', \Session::get('username'));
		$this->assertSame($expected, \Session::get('login_hash'));
		$this->assertSame(1, \Session::$rotations);
		$this->assertSame($driver, \Auth\Auth::verified('Simpleauth'));
		$this->assertSame(array('Simpleauth', 1), $driver->get_user_id());
		$this->assertSame(array(array('Simplegroup', 1)), $driver->get_groups());
		$this->assertSame('ada@example.com', $driver->get_email());
		$this->assertSame('ada', $driver->get_screen_name());
		$this->assertSame('London', $driver->get('city'));
		$this->assertSame('London', $driver->get_profile_fields('city'));
		$this->assertSame('missing', $driver->get('nope', 'missing'));
		$this->assertSame($expected, $this->userRow(1)['login_hash']);
		$this->assertSame(1700000000, $this->userRow(1)['last_login']);

		$this->assertTrue($driver->check());
		\Auth\Auth::logout();
		$this->assertNull(\Session::get('username'));
		$this->assertNull(\Session::get('login_hash'));
		$this->assertSame('guest', $driver->get_screen_name());
		$this->assertSame(array(), \Auth\Auth::verified());
	}

	public function test_guest_login_can_be_disabled()
	{
		\Config::set('simpleauth.guest_login', false);
		$driver = $this->forgeSimpleAuth();

		$this->assertFalse($driver->login('ada', 'secret'));
		$this->assertFalse($driver->get_user_id());
		$this->assertFalse($driver->get_groups());
		$this->assertFalse($driver->get_email());
		$this->assertFalse($driver->get_screen_name());
		$this->assertFalse($driver->get_profile_fields());
		$this->assertSame('fallback', $driver->get('email', 'fallback'));
	}

	public function test_perform_check_restores_a_matching_session_and_clears_a_mismatch()
	{
		$driver = $this->forgeSimpleAuth();
		$id = $driver->create_user('ada', 'secret', 'ada@example.com');
		\DB::update('users')->set(array('login_hash' => 'abc'))->where('id', '=', $id)->execute();
		\Session::set('username', 'ada');
		\Session::set('login_hash', 'abc');

		$this->assertTrue($driver->check());
		$this->assertSame('ada', $driver->get_screen_name());

		\Session::set('login_hash', 'nope');
		$this->assertFalse($driver->check());
		$this->assertNull(\Session::get('username'));
		$this->assertNull(\Session::get('login_hash'));
		$this->assertSame('guest', $driver->get_screen_name());
	}

	public function test_loaded_user_is_not_requeried_when_the_username_is_unchanged()
	{
		$driver = $this->forgeSimpleAuth();
		$driver->create_user('ada', 'secret', 'ada@example.com');
		$this->assertTrue($driver->login('ada', 'secret'));
		$hash = \Session::get('login_hash');

		\DB::update('users')->set(array('login_hash' => 'replaced'))->where('username', '=', 'ada')->execute();

		$this->assertTrue($driver->check());
		$this->assertSame($hash, \Session::get('login_hash'));
	}

	public function test_multiple_logins_accept_a_different_hash()
	{
		$driver = $this->forgeSimpleAuth();
		$driver->create_user('ada', 'secret', 'ada@example.com');
		\Config::set('simpleauth.multiple_logins', true);
		\Session::set('username', 'ada');
		\Session::set('login_hash', 'not-the-stored-hash');

		$this->assertTrue($driver->check());
	}

	public function test_failed_check_clears_session_credentials_and_keeps_the_guest()
	{
		$driver = $this->forgeSimpleAuth();
		$this->assertFalse($driver->check());
		$this->assertSame('guest', $driver->get_screen_name());

		\Session::set('username', 'ada');
		\Session::set('login_hash', 'abc');

		$this->assertFalse($driver->check());
		$this->assertNull(\Session::get('username'));
		$this->assertNull(\Session::get('login_hash'));
		$this->assertSame('guest', $driver->get_screen_name());
	}

	public function test_remember_me_forces_login_for_a_stored_user()
	{
		$driver = $this->forgeSimpleAuth();
		$id = $driver->create_user('ada', 'secret', 'ada@example.com');
		\Config::set('simpleauth.remember_me.enabled', true);
		\Auth\Auth_Login_Simpleauth::_init();
		$remember = $this->getStatic('Auth\\Auth_Login_Driver', 'remember_me');
		$remember->set('user_id', $id);

		$this->assertTrue($driver->check());
		$this->assertSame('ada', \Session::get('username'));
		$this->assertSame('', \Session::get('login_hash'));
		$this->assertSame($driver, \Auth\Auth::verified('Simpleauth'));
		$this->assertSame(1, \Session::$rotations);
	}

	public function test_force_login_edges_and_cli_hash_handling()
	{
		$driver = $this->forgeSimpleAuth();
		$id = $driver->create_user('ada', 'secret', 'ada@example.com');
		\DB::update('users')->set(array('login_hash' => 'existing'))->where('id', '=', $id)->execute();

		$this->assertFalse($driver->force_login(''));
		$this->assertFalse($driver->force_login(0));
		$this->assertFalse($driver->force_login(99));
		$this->assertNull(\Session::get('username'));
		$this->assertSame('guest', $driver->get_screen_name());

		\Fuel::$is_cli = true;
		$this->assertTrue($driver->force_login($id));
		$this->assertSame('existing', \Session::get('login_hash'));
		$this->assertSame('existing', $this->userRow($id)['login_hash']);
		$this->assertSame(0, $this->userRow($id)['last_login']);

		\Fuel::$is_cli = false;
		\Session::reset();
		$this->assertTrue($driver->force_login((string) $id));
		$expected = sha1('hash-saltada1700000000');
		$this->assertSame($expected, \Session::get('login_hash'));
		$this->assertSame($expected, $this->userRow($id)['login_hash']);
		$this->assertSame(1700000000, $this->userRow($id)['last_login']);
	}

	public function test_create_login_hash_requires_a_user()
	{
		$driver = $this->forgeSimpleAuth();

		$this->expectException(\SimpleUserUpdateException::class);
		$this->expectExceptionCode(10);
		$driver->create_login_hash();
	}

	public function test_update_user_changes_password_email_group_and_profile()
	{
		$driver = $this->forgeSimpleAuth();
		$ada = $driver->create_user('ada', 'secret', 'ada@example.com', 1, array(
			'city' => 'London',
			'drop_me' => 'gone',
		));
		$bob = $driver->create_user('bob', 'secret', 'bob@example.com', 1);
		$this->assertTrue($driver->login('ada', 'secret'));

		$this->assertFalse($driver->change_password('wrong', 'new-secret'));

		try
		{
			$driver->change_password('secret', '   ');
			$this->fail('empty password should be rejected');
		}
		catch (\SimpleUserUpdateException $e)
		{
			$this->assertSame(6, $e->getCode());
		}

		$this->assertTrue($driver->change_password('secret', 'new-secret'));
		$this->assertFalse($driver->validate_user('ada', 'secret'));
		$this->assertIsArray($driver->validate_user('ada', 'new-secret'));
		$this->assertNotSame($this->userRow($ada)['salt'], '');

		try
		{
			$driver->update_user(array('email' => 'bad'));
			$this->fail('invalid email should be rejected');
		}
		catch (\SimpleUserUpdateException $e)
		{
			$this->assertSame(7, $e->getCode());
		}

		try
		{
			$driver->update_user(array('email' => 'bob@example.com'));
			$this->fail('duplicate email should be rejected');
		}
		catch (\SimpleUserUpdateException $e)
		{
			$this->assertSame(11, $e->getCode());
		}

		$this->assertTrue($driver->update_user(array(
			'email' => 'ada.lovelace@example.com',
			'group' => '100',
			'username' => 'should-be-profile',
			'city' => 'Paris',
			'drop_me' => null,
			'title' => 'Countess',
		)));

		$row = $this->userRow($ada);
		$this->assertSame('ada', $row['username']);
		$this->assertSame('ada.lovelace@example.com', $row['email']);
		$this->assertSame(100, $row['group']);
		$this->assertSame(1700000000, $row['updated_at']);
		$this->assertSame(array(
			'city' => 'Paris',
			'username' => 'should-be-profile',
			'title' => 'Countess',
		), unserialize($row['profile_fields']));
		$this->assertSame('ada', \Session::get('username'));
		$this->assertSame('ada.lovelace@example.com', $driver->get_email());
		$this->assertSame('Paris', $driver->get('city'));
		$this->assertSame('n/a', $driver->get_profile_fields('address.missing', 'n/a'));

		$this->assertTrue($driver->update_user(array('group' => 'nope', 'email' => 'robert@example.com'), 'bob'));
		$this->assertSame(1, $this->userRow($bob)['group']);
		$this->assertSame('robert@example.com', $this->userRow($bob)['email']);
		$this->assertSame('ada.lovelace@example.com', $driver->get_email());
		$this->assertSame('ada', \Session::get('username'));
	}

	public function test_update_user_reports_a_missing_account()
	{
		$driver = $this->forgeSimpleAuth();
		$driver->create_user('ada', 'secret', 'ada@example.com');
		$driver->login('ada', 'secret');

		$this->expectException(\SimpleUserUpdateException::class);
		$this->expectExceptionCode(4);
		$driver->update_user(array('email' => 'new@example.com'), 'missing');
	}

	public function test_reset_and_delete_follow_login_type()
	{
		$driver = $this->forgeSimpleAuth();
		$driver->create_user('ada', 'secret', 'ada@example.com');

		\Config::set('auth.login_type', 'email');
		try
		{
			$driver->reset_password('ada');
			$this->fail('username lookup should fail in email mode');
		}
		catch (\SimpleUserUpdateException $e)
		{
			$this->assertSame(8, $e->getCode());
		}

		$replacement = $driver->reset_password('ada@example.com');
		$this->assertSame(32, strlen($replacement));
		$this->assertTrue(ctype_xdigit($replacement));
		$this->assertFalse($driver->validate_user('ada@example.com', 'secret'));
		$this->assertIsArray($driver->validate_user('ada@example.com', $replacement));

		\Config::set('auth.login_type', 'username');
		$this->assertFalse($driver->delete_user('ada@example.com'));
		$this->assertTrue($driver->delete_user('ada'));
		$this->assertFalse($driver->validate_user('ada', $replacement));

		$driver->create_user('bob', 'secret', 'bob@example.com');
		\Config::set('auth.login_type', 'both');
		$this->assertTrue($driver->delete_user('bob@example.com'));
		$this->assertFalse($driver->delete_user('bob'));
	}

	public function test_delete_user_rejects_empty_identifiers()
	{
		$driver = $this->forgeSimpleAuth();

		foreach (array('', null, '0') as $identifier)
		{
			try
			{
				$driver->delete_user($identifier);
				$this->fail('empty identifier '.var_export($identifier, true).' should be rejected');
			}
			catch (\SimpleUserUpdateException $e)
			{
				$this->assertSame(9, $e->getCode());
			}
		}

		$this->assertFalse($driver->delete_user('   '));
	}

	public function test_access_helpers_use_the_current_group()
	{
		$driver = $this->forgeSimpleAuth();
		$driver->create_user('ada', 'secret', 'ada@example.com', 50);
		$this->assertTrue($driver->login('ada', 'secret'));

		$this->assertTrue($driver->member(50));
		$this->assertFalse($driver->member(1));
		$this->assertTrue($driver->has_access('comments.[read, delete]'));
		$this->assertTrue($driver->has_access('posts.read'));
		$this->assertFalse($driver->has_access('posts.delete'));
		$this->assertTrue($driver->has_any_access(array('posts.delete', 'website.read')));
		$this->assertFalse($driver->has_any_access(array('posts.delete', 'posts.create')));
		$this->assertTrue($driver->has_all_access(array('comments.create', 'posts.read')));
		$this->assertFalse($driver->has_all_access(array('comments.create', 'posts.delete')));
		$this->assertTrue($driver->has_all_access(array()));
		$this->assertFalse($driver->has_any_access(array()));

		$this->assertTrue($driver->has_any_access(array('comments.delete'), 'Simpleacl', array('Simplegroup', 50)));
		$this->assertFalse($driver->has_any_access(array('comments.delete'), 'Simpleacl', array('Simplegroup', 1)));
		$this->assertTrue($driver->has_any_access(array('secrets.delete'), 'Simpleacl', array('Simplegroup', 100)));
		$this->assertFalse($driver->has_all_access(array('comments.read', 'comments.delete'), 'Simpleacl', array('Simplegroup', 1)));
		$this->assertTrue($driver->has_all_access(array('comments.create', 'comments.read'), 'Simpleacl', array('Simplegroup', 1)));
	}

	public function test_access_check_against_an_unknown_acl_driver()
	{
		$this->markTestIncomplete(
			'Auth_Login_Driver::has_access() (classes/auth/login/driver.php:163) calls has_access() on the false that Auth::acl() returns for an unknown acl driver id. Left incomplete because the intended result (false or an AuthException) is not specified.'
		);
	}

	public function test_profile_dot_notation_and_serialized_profile_strings()
	{
		$driver = $this->forgeSimpleAuth();
		$driver->create_user('ada', 'secret', 'ada@example.com', 1, array(
			'address' => array('city' => 'London'),
		));
		$driver->login('ada', 'secret');

		$this->assertSame('London', $driver->get_profile_fields('address.city'));
		$this->assertSame('n/a', $driver->get_profile_fields('address.postcode', 'n/a'));
		$this->assertSame('London', $driver->get('address.city'));
		$this->assertIsArray($driver->get_profile_fields());
	}
}
