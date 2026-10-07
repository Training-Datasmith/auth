<?php

class AuthDriverTest extends TestCase
{
	public function test_base_driver_forge_must_be_extended()
	{
		$this->expectException(\AuthException::class);
		$this->expectExceptionMessage('factory method');
		\Auth\Auth_Driver::forge();
	}

	public function test_driver_instance_lookup_config_and_guest_login()
	{
		$group = \Auth\Auth_Group_Driver::forge(array('driver' => 'Simplegroup', 'id' => 12, 'label' => 'primary'));

		$this->assertSame('12', $group->get_id());
		$this->assertSame('primary', $group->get_config('label'));
		$this->assertSame('fallback', $group->get_config('missing', 'fallback'));
		$group->set_config('label', 'renamed');
		$this->assertSame('renamed', $group->get_config('label'));
		$this->assertFalse($group->guest_login());

		$this->assertSame($group, \Auth\Auth_Group_Driver::instance('12'));
		$this->assertSame($group, \Auth\Auth_Group_Driver::instance());
		$this->assertFalse(\Auth\Auth_Group_Driver::instance('other'));
		$this->assertSame(array('12' => $group), \Auth\Auth_Group_Driver::instance(true));
	}

	public function test_simpleauth_guest_login_follows_config()
	{
		$driver = $this->forgeSimpleAuth();
		$this->assertTrue($driver->guest_login());

		\Config::set('simpleauth.guest_login', false);
		$this->assertFalse($driver->guest_login());
	}

	public function test_login_driver_hashes_with_the_configured_pbkdf2_parameters()
	{
		$driver = $this->forgeSimpleAuth();
		$expected = base64_encode(hash_pbkdf2('sha256', 'secret', 'test-salt', 2, 32, true));

		$this->assertSame($expected, $driver->hash_password('secret'));
		$this->assertNotSame($expected, $driver->hash_password('other'));

		\Config::set('auth.iterations', 3);
		$this->assertNotSame($expected, $driver->hash_password('secret'));

		\Config::set('auth.iterations', 2);
		\Config::set('auth.salt', 'other-salt');
		$this->assertNotSame($expected, $driver->hash_password('secret'));
	}

	public function test_remember_me_is_off_until_a_cookie_session_is_configured()
	{
		$driver = $this->forgeSimpleAuth();
		$this->assertFalse($driver->remember_me(5));
		$driver->dont_remember_me();

		\Config::set('simpleauth.remember_me.enabled', true);
		\Config::set('simpleauth.remember_me.cookie_name', 'remember');
		\Config::set('simpleauth.remember_me.expiration', 1234);
		\Auth\Auth_Login_Simpleauth::_init();

		$session = $this->getStatic('Auth\\Auth_Login_Driver', 'remember_me');
		$this->assertInstanceOf(\CookieSession::class, $session);
		$this->assertSame('remember', $session->config['cookie']['cookie_name']);
		$this->assertSame(1234, $session->config['expiration_time']);
		$this->assertTrue($session->config['encrypt_cookie']);
		$this->assertFalse($session->config['expire_on_close']);
		$this->assertSame('cookie', $session->config['driver']);

		$this->assertFalse($driver->remember_me(0));
		$this->assertTrue($driver->remember_me(5));
		$this->assertSame(5, $session->get('user_id'));
		$driver->dont_remember_me();
		$this->assertTrue($session->destroyed);
		$this->assertNull($session->get('user_id'));
	}

	public function test_user_array_includes_configured_accessors_only()
	{
		$driver = $this->forgeSimpleAuth();
		$driver->create_user('ada', 'secret', 'ada@example.com', 1, array('city' => 'London'));
		$driver->login('ada', 'secret');

		$user = $driver->get_user_array(array('password', 'email'));

		$this->assertSame('ada@example.com', $user['email']);
		$this->assertSame('ada', $user['screen_name']);
		$this->assertSame(array(array('Simplegroup', 1)), $user['groups']);
		$this->assertSame(array('city' => 'London'), $user['profile_fields']);
		$this->assertArrayNotHasKey('password', $user);
	}

	public function test_user_array_on_an_empty_driver_uses_false_defaults()
	{
		$driver = $this->forgeSimpleAuth();
		$user = $driver->get_user_array();

		$this->assertFalse($user['email']);
		$this->assertFalse($user['screen_name']);
		$this->assertFalse($user['groups']);
		$this->assertFalse($user['profile_fields']);
	}
}
