<?php

class AuthTest extends TestCase
{
	public function test_exception_types_extend_fuel_exception()
	{
		$this->assertInstanceOf(\FuelException::class, new \Auth\AuthException('auth'));
		$this->assertInstanceOf(\FuelException::class, new \Auth\SimpleUserUpdateException('update', 1));
		$this->assertInstanceOf(\FuelException::class, new \Auth\SimpleUserWrongPassword('password'));
		$this->assertInstanceOf(\FuelException::class, new \Auth\OpauthException('opauth'));
		$this->assertInstanceOf(\Auth\AuthException::class, new \AuthException('aliased'));
	}

	public function test_forge_requires_a_driver_name()
	{
		foreach (array(array(), array('driver' => ''), array('driver' => array('nope')), 42) as $config)
		{
			try
			{
				\Auth\Auth::forge($config);
				$this->fail('forge should have rejected '.json_encode($config));
			}
			catch (\AuthException $e)
			{
				$this->assertStringContainsString('No auth driver specified', $e->getMessage());
			}
		}
	}

	public function test_forge_accepts_a_driver_string_and_merges_driver_config()
	{
		\Config::set('auth.Simpleauth_config', array('extra' => 'from-config', 'id' => 'configured'));

		$driver = \Auth\Auth::forge(array('driver' => 'Simpleauth', 'extra' => 'from-call'));

		$this->assertInstanceOf(\Auth\Auth_Login_Simpleauth::class, $driver);
		$this->assertSame('configured', $driver->get_id());
		$this->assertSame('from-call', $driver->get_config('extra'));
		$this->assertSame($driver, \Auth\Auth::instance('configured'));
		$this->assertNull($this->getStatic('Auth\\Auth', '_instance'));
	}

	public function test_forging_the_same_id_returns_the_original_auth_instance()
	{
		$first = \Auth\Auth::forge('Simpleauth');
		$second = \Auth\Auth::forge(array('driver' => 'Simpleauth'));

		$this->assertSame($first, $second);
		$this->assertSame($first, \Auth\Auth::instance('Simpleauth'));
		// The login-driver registry is replaced even though Auth keeps the first object.
		$this->assertNotSame($first, \Auth_Login_Driver::instance('Simpleauth'));
	}

	public function test_forging_two_driver_classes_with_the_same_id_is_rejected()
	{
		\Auth\Auth::forge(array('driver' => 'Simpleauth', 'id' => 'shared'));

		$this->expectException(\AuthException::class);
		$this->expectExceptionMessage('same id "shared"');
		\Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'shared'));
	}

	public function test_multiple_ids_keep_the_first_driver_as_the_default()
	{
		$front = \Auth\Auth::forge(array('driver' => 'Simpleauth', 'id' => 'front'));
		$back = \Auth\Auth::forge(array('driver' => 'Simpleauth', 'id' => 'back'));

		$this->assertNotSame($front, $back);
		$this->assertNull($this->getStatic('Auth\\Auth', '_instance'));
		$this->assertSame($front, \Auth\Auth::instance('front'));
		$this->assertSame($back, \Auth\Auth::instance('back'));
		$this->assertFalse(\Auth\Auth::instance('missing'));
	}

	public function test_instance_without_a_driver_cannot_forge_one_implicitly()
	{
		$this->expectException(\AuthException::class);
		\Auth\Auth::instance();
	}

	public function test_init_forges_the_configured_driver_and_starts_a_guest_session()
	{
		\Auth\Auth::_init();

		$driver = \Auth\Auth::instance();
		$this->assertInstanceOf(\Auth\Auth_Login_Simpleauth::class, $driver);
		$this->assertSame('guest', $driver->get_screen_name());
		$this->assertSame(array('Simpleauth', 0), $driver->get_user_id());
		$this->assertSame(array(), \Auth\Auth::verified());
		$this->assertTrue(\Auth\Auth::has_access('website.read'));
		$this->assertFalse(\Auth\Auth::has_access('comments.delete'));
	}

	public function test_unload_removes_only_the_default_when_given_null()
	{
		$front = \Auth\Auth::forge(array('driver' => 'Simpleauth', 'id' => 'front'));
		$back = \Auth\Auth::forge(array('driver' => 'Simpleauth', 'id' => 'back'));
		$this->setStatic('Auth\\Auth', '_instance', $front);

		$this->assertTrue(\Auth\Auth::unload());
		$this->assertNull($this->getStatic('Auth\\Auth', '_instance'));
		$this->assertFalse(\Auth\Auth::instance('front'));
		$this->assertSame($back, \Auth\Auth::instance('back'));
		$this->assertNotSame($front, $back);

		$this->expectException(\AuthException::class);
		\Auth\Auth::instance();
	}

	public function test_unload_removes_a_known_id_and_rejects_an_unknown_id()
	{
		$driver = \Auth\Auth::forge(array('driver' => 'Simpleauth', 'id' => 'front'));

		$this->assertFalse(\Auth\Auth::unload('missing'));
		$this->assertSame($driver, \Auth\Auth::instance('front'));
		$this->assertFalse(\Auth\Auth::unload());

		$this->assertTrue(\Auth\Auth::unload('front'));
		$this->assertFalse(\Auth\Auth::instance('front'));

		$again = \Auth\Auth::forge(array('driver' => 'Simpleauth', 'id' => 'front'));
		$this->setStatic('Auth\\Auth', '_instance', $again);
		$this->assertTrue(\Auth\Auth::unload('front'));
		$this->assertNull($this->getStatic('Auth\\Auth', '_instance'));
		$this->assertFalse(\Auth\Auth::instance('front'));
	}

	public function test_login_stops_at_the_first_success_unless_multiple_verification_is_enabled()
	{
		$first = \Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'first'));
		$second = \Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'second'));
		$first->login_result = true;
		$second->login_result = true;

		$this->assertTrue(\Auth\Auth::login('ada', 'secret'));
		$this->assertSame(1, $first->logins);
		$this->assertSame(0, $second->logins);
		$this->assertSame($first, \Auth\Auth::verified('first'));
		$this->assertFalse(\Auth\Auth::verified('second'));
	}

	public function test_login_tries_later_drivers_after_a_failure()
	{
		$first = \Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'first'));
		$second = \Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'second'));
		$first->login_result = false;
		$second->login_result = true;

		$this->assertTrue(\Auth\Auth::login('ada', 'secret'));
		$this->assertSame(1, $first->logins);
		$this->assertSame(1, $second->logins);
		$this->assertFalse(\Auth\Auth::verified('first'));
		$this->assertSame($second, \Auth\Auth::verified('second'));
	}

	public function test_login_with_no_drivers_or_no_matches_returns_false()
	{
		$this->assertFalse(\Auth\Auth::login('ada', 'secret'));

		$driver = \Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'only'));
		$driver->login_result = false;
		$this->assertFalse(\Auth\Auth::login('ada', 'secret'));
		$this->assertSame(array(), \Auth\Auth::verified());
	}

	public function test_verify_multiple_logins_attempts_every_driver()
	{
		\Config::set('auth.verify_multiple_logins', true);
		$first = \Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'first'));
		$second = \Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'second'));
		$first->login_result = true;
		$second->login_result = true;

		$this->assertTrue($this->getStatic('Auth\\Auth', '_verify_multiple'));
		$this->assertTrue(\Auth\Auth::login('ada', 'secret'));
		$this->assertSame(1, $first->logins);
		$this->assertSame(1, $second->logins);
		$this->assertCount(2, \Auth\Auth::verified());
	}

	public function test_logout_only_notifies_verified_drivers_and_clears_them()
	{
		$first = \Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'first'));
		$second = \Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'second'));
		$first->login_result = false;
		$second->login_result = true;
		\Auth\Auth::login('ada', 'secret');

		\Auth\Auth::logout();

		$this->assertSame(0, $first->logouts);
		$this->assertSame(1, $second->logouts);
		$this->assertSame(array(), \Auth\Auth::verified());
		$this->assertFalse(\Auth\Auth::verified('second'));
	}

	public function test_check_reports_drivers_that_were_already_verified()
	{
		$driver = \Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'stub'));
		$driver->check_result = true;

		$this->assertTrue(\Auth\Auth::check());
		$this->assertSame(1, $driver->checks);
		$this->assertSame($driver, \Auth\Auth::verified('stub'));

		$this->assertTrue(\Auth\Auth::check());
		$this->assertTrue(\Auth\Auth::check('stub'));
		$this->assertTrue(\Auth\Auth::check(array($driver)));
		// A driver that is already verified is not asked to perform_check again.
		$this->assertSame(1, $driver->checks);
	}

	public function test_check_keeps_asking_until_a_driver_verifies()
	{
		$driver = \Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'stub'));
		$driver->check_result = false;

		$this->assertFalse(\Auth\Auth::check());
		$this->assertFalse(\Auth\Auth::check('stub'));
		$this->assertSame(2, $driver->checks);
		$this->assertSame(array(), \Auth\Auth::verified());

		$driver->check_result = true;
		$this->assertTrue(\Auth\Auth::check(array($driver)));
		$this->assertSame($driver, \Auth\Auth::verified('stub'));
		$this->assertTrue(\Auth\Auth::check(array($driver)));
		$this->assertSame(3, $driver->checks);
	}

	public function test_register_driver_type_accepts_new_types_and_existing_pairs()
	{
		$this->assertTrue(\Auth\Auth::register_driver_type('acl', 'has_access'));
		$this->assertTrue(\Auth\Auth::register_driver_type('custom', 'custom_check'));

		$drivers = $this->getStatic('Auth\\Auth', '_drivers');
		$this->assertSame('custom', $drivers['custom_check']);
	}

	public function test_register_driver_type_rejects_conflicts()
	{
		$this->assertFalse(\Auth\Auth::register_driver_type('acl', 'member'));
		$this->assertNotEmpty(\Errorhandler::$notices);
		$this->assertStringContainsString('Cannot add driver type', \Errorhandler::$notices[0]);

		\Errorhandler::reset();
		$this->assertFalse(\Auth\Auth::register_driver_type('login', 'missing_method'));
		$this->assertNotEmpty(\Errorhandler::$notices);
		$this->assertStringContainsString('Cannot add driver type', \Errorhandler::$notices[0]);
		$this->assertArrayNotHasKey('missing_method', $this->getStatic('Auth\\Auth', '_drivers'));
	}

	public function test_unregister_driver_type_protects_builtins_and_removes_custom_types()
	{
		\Auth\Auth::register_driver_type('custom', 'custom_check');

		$this->assertFalse(\Auth\Auth::unregister_driver_type('login'));
		$this->assertFalse(\Auth\Auth::unregister_driver_type('group'));
		$this->assertFalse(\Auth\Auth::unregister_driver_type('acl'));
		$this->assertTrue(\Auth\Auth::unregister_driver_type('custom'));

		$drivers = $this->getStatic('Auth\\Auth', '_drivers');
		$this->assertArrayNotHasKey('custom_check', $drivers);
		$this->assertSame('group', $drivers['member']);
		$this->assertNotEmpty(\Errorhandler::$notices);
		$this->assertStringContainsString('Cannot remove driver type', \Errorhandler::$notices[0]);
	}

	public function test_magic_calls_resolve_group_and_acl_drivers_and_delegate_login_methods()
	{
		$login = $this->bootSimpleAuth();
		$login->create_user('ada', 'secret', 'ada@example.com', 1);
		$this->assertTrue($login->login('ada', 'secret'));

		$this->assertInstanceOf(\Auth\Auth_Group_Simplegroup::class, \Auth\Auth::group('Simplegroup'));
		$this->assertInstanceOf(\Auth\Auth_Acl_Simpleacl::class, \Auth\Auth::acl('Simpleacl'));
		$this->assertIsArray(\Auth\Auth::group(true));
		$this->assertTrue(\Auth\Auth::member(1));
		$this->assertFalse(\Auth\Auth::member(100));
		$this->assertSame('ada@example.com', \Auth\Auth::get_email());
	}

	public function test_magic_access_checks_follow_the_verified_user()
	{
		$login = $this->forgeSimpleAuth();
		$login->create_user('ada', 'secret', 'ada@example.com', 50);
		$this->assertTrue($login->login('ada', 'secret'));

		$this->assertTrue(\Auth\Auth::has_access('comments.[read, delete]'));
		$this->assertTrue(\Auth\Auth::has_any_access(array('comments.delete', 'posts.create')));
		$this->assertFalse(\Auth\Auth::has_all_access(array('comments.read', 'posts.create')));
		$this->assertTrue(\Auth\Auth::has_all_access(array('comments.read', 'posts.read')));
	}

	public function test_unknown_static_method_is_rejected()
	{
		$driver = \Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'stub'));
		$this->setStatic('Auth\\Auth', '_instance', $driver);

		$this->expectException(\BadMethodCallException::class);
		$this->expectExceptionMessage('Invalid method');
		\Auth\Auth::not_a_real_method();
	}

	public function test_static_delegation_is_disabled_when_multiple_logins_are_verified()
	{
		\Config::set('auth.verify_multiple_logins', true);
		$first = \Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'first'));
		\Auth\Auth::forge(array('driver' => 'Stub', 'id' => 'second'));
		$this->setStatic('Auth\\Auth', '_instance', $first);

		$this->expectException(\BadMethodCallException::class);
		\Auth\Auth::get_email();
	}

	public function test_guest_access_uses_guest_login_when_nobody_is_verified()
	{
		$login = $this->bootSimpleAuth();
		$this->assertFalse($login->login('missing', 'secret'));

		$this->assertTrue(\Auth\Auth::has_access('website.[read]'));
		$this->assertFalse(\Auth\Auth::has_access('comments.create'));
		$this->assertSame('guest', \Auth\Auth::get_screen_name());
	}
}
