<?php

use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class OpauthTest extends TestCase
{
	/**
	 * @runInSeparateProcess
	 */
	#[RunInSeparateProcess]
	public function test_init_requires_the_opauth_library()
	{
		$this->expectException(\OpauthException::class);
		$this->expectExceptionMessage('Opauth composer package not installed');
		\Auth\Auth_Opauth::_init();
	}

	protected function ensureOpauth()
	{
		if (class_exists('Opauth', false))
		{
			return;
		}

		eval(<<<'PHP'
class Opauth
{
	public static $valid = true;
	public static $reason = 'bad signature';
	public $config;
	public $autorun;

	public function __construct($config, $autorun = true)
	{
		$this->config = $config;
		$this->autorun = $autorun;
	}

	public function validate($hash, $timestamp, $signature, &$reason)
	{
		$reason = static::$reason;
		return static::$valid;
	}
}
PHP);
	}

	protected function bootOpauth(array $config = array())
	{
		$this->ensureOpauth();
		\Config::set('auth.driver', 'Simpleauth');
		\Config::set('opauth.security_salt', 'salt');
		\Config::set('opauth.path', '/auth/oauth/');
		\Config::set('opauth.Strategy', array('Facebook' => array('app_id' => '1')));
		foreach ($config as $key => $value)
		{
			\Config::set('opauth.'.$key, $value);
		}
		\Auth\Auth_Opauth::_init();
	}

	protected function forgeOpauth(array $config = array(), $autorun = true)
	{
		$this->bootOpauth();

		return \Auth\Auth_Opauth::forge($config, $autorun);
	}

	protected function callbackPayload(array $auth)
	{
		\Input::$get['opauth'] = base64_encode(json_encode(array(
			'auth' => $auth,
			'timestamp' => 1700000000,
			'signature' => 'sig',
		)));
	}

	protected function authPayload(array $info = array(), $uid = 'uid-1', $provider = 'Facebook')
	{
		return array(
			'provider' => $provider,
			'uid' => $uid,
			'info' => $info,
			'credentials' => array(
				'token' => 'tok',
				'secret' => 'sec',
				'expires' => '1700000000',
				'refresh_token' => 'ref',
			),
		);
	}

	public function test_init_rejects_unsupported_auth_drivers()
	{
		$this->ensureOpauth();
		\Config::set('auth.driver', 'Nope');

		$this->expectException(\OpauthException::class);
		$this->expectExceptionMessage('No supported driver found');
		\Auth\Auth_Opauth::_init();
	}

	public function test_init_detects_simpleauth_and_ormauth_provider_tables()
	{
		$this->ensureOpauth();
		\Config::set('auth.driver', 'Simpleauth');
		\Config::set('simpleauth.table_name', 'people');
		\Config::set('simpleauth.db_connection', 'auth_db');
		\Auth\Auth_Opauth::_init();
		$this->assertSame('people_providers', $this->getStatic('Auth\\Auth_Opauth', 'provider_table'));
		$this->assertSame('auth_db', $this->getStatic('Auth\\Auth_Opauth', 'db_connection'));

		\Config::set('auth.driver', 'Ormauth');
		\Config::set('ormauth.table_name', 'members');
		\Config::set('ormauth.db_connection', 'orm_db');
		\Auth\Auth_Opauth::_init();
		$this->assertSame('members_providers', $this->getStatic('Auth\\Auth_Opauth', 'provider_table'));
		$this->assertSame('orm_db', $this->getStatic('Auth\\Auth_Opauth', 'db_connection'));
	}

	public function test_forge_requires_a_salt_and_a_known_strategy()
	{
		$this->ensureOpauth();
		\Config::set('auth.driver', 'Simpleauth');
		\Auth\Auth_Opauth::_init();
		\Config::set('opauth.security_salt', null);
		\Config::set('opauth.path', '/auth/oauth/');

		try
		{
			\Auth\Auth_Opauth::forge(array('provider' => 'Facebook'), true);
			$this->fail('missing salt should be rejected');
		}
		catch (\OpauthException $e)
		{
			$this->assertStringContainsString('security_salt', $e->getMessage());
		}

		\Config::set('opauth.security_salt', 'salt');
		\Config::set('opauth.Strategy', array());
		try
		{
			\Auth\Auth_Opauth::forge(array('provider' => 'Facebook'), true);
			$this->fail('missing strategy should be rejected');
		}
		catch (\OpauthException $e)
		{
			$this->assertStringContainsString('Facebook', $e->getMessage());
		}
	}

	public function test_forge_builds_callback_settings_and_accepts_provider_case_insensitively()
	{
		$opauth = $this->forgeOpauth(array('provider' => 'facebook'), true);
		$instance = $opauth->get_instance();

		$this->assertInstanceOf(\Opauth::class, $instance);
		$this->assertTrue($instance->autorun);
		$this->assertSame('get', $instance->config['callback_transport']);
		$this->assertSame('/auth/callback/', $instance->config['callback_url']);
		$this->assertSame('users_providers', $instance->config['table']);
		$this->assertSame('salt', $instance->config['security_salt']);
		$this->assertSame('x', $opauth->get('auth.uid', 'x'));
	}

	public function test_forge_can_take_only_the_autorun_flag_and_an_explicit_table()
	{
		$this->bootOpauth();
		$opauth = \Auth\Auth_Opauth::forge(false);
		$instance = $opauth->get_instance();

		$this->assertFalse($instance->autorun);
		$this->assertSame('Callback', $instance->config['provider']);

		$opauth = \Auth\Auth_Opauth::forge(array(
			'table' => 'custom_providers',
			'provider' => 'Facebook',
			'callback_url' => '/done',
		), true);
		$this->assertSame('custom_providers', $opauth->get_instance()->config['table']);
		$this->assertSame('/done', $opauth->get_instance()->config['callback_url']);
	}

	public function test_callback_rejects_missing_error_and_invalid_payloads()
	{
		$opauth = $this->forgeOpauth(array('provider' => 'Facebook'), false);

		\Input::$get['opauth'] = false;
		try
		{
			$opauth->login_or_register();
			$this->fail('missing callback should be rejected');
		}
		catch (\OpauthException $e)
		{
			$this->assertStringContainsString('no valid response', $e->getMessage());
		}

		\Input::$get['opauth'] = base64_encode(json_encode(array('error' => 'denied')));
		try
		{
			$opauth->login_or_register();
			$this->fail('error payload should be rejected');
		}
		catch (\OpauthException $e)
		{
			$this->assertStringContainsString('error auth response', $e->getMessage());
		}

		\Input::$get['opauth'] = base64_encode(json_encode(array('auth' => array('provider' => 'Facebook'))));
		try
		{
			$opauth->login_or_register();
			$this->fail('incomplete payload should be rejected');
		}
		catch (\OpauthException $e)
		{
			$this->assertStringContainsString('Missing key', $e->getMessage());
		}

		$this->callbackPayload($this->authPayload());
		\Opauth::$valid = false;
		try
		{
			$opauth->login_or_register();
			$this->fail('bad signature should be rejected');
		}
		catch (\OpauthException $e)
		{
			$this->assertStringContainsString('bad signature', $e->getMessage());
		}
	}

	public function test_callback_accepts_a_serialized_payload()
	{
		$opauth = $this->forgeOpauth(array('provider' => 'Facebook'), false);
		$response = array(
			'auth' => $this->authPayload(array('nickname' => 'ada', 'email' => 'ada@example.com')),
			'timestamp' => 1700000000,
			'signature' => 'sig',
		);
		\Input::$get['opauth'] = base64_encode(serialize($response));

		$this->assertSame('register', $opauth->login_or_register());
		$stored = \Session::get('auth-strategy');
		$this->assertSame('ada', $stored['user']['nickname']);
		$this->assertSame('Facebook', $stored['authentication']['provider']);
		$this->assertSame('uid-1', $stored['authentication']['uid']);
	}

	public function test_login_or_register_creates_and_logs_in_a_new_user()
	{
		$this->forgeSimpleAuth();
		\Config::set('opauth.default_group', 50);
		$opauth = $this->forgeOpauth(array(
			'provider' => 'Facebook',
			'auto_registration' => false,
		), false);
		$this->callbackPayload($this->authPayload(array(
			'nickname' => 'ada',
			'email' => 'ada@example.com',
			'password' => 'secret-pass',
			'name' => 'Ada Lovelace',
		)));

		$this->assertSame('registered', $opauth->login_or_register());
		$this->assertSame('ada', \Auth\Auth::instance()->get_screen_name());
		$this->assertSame('Ada Lovelace', \Auth\Auth::instance()->get_profile_fields('fullname'));
		$this->assertSame(50, \Auth\Auth::instance()->get('group'));
		$this->assertCount(1, \DB::$tables['users_providers']);
		$this->assertSame('uid-1', \DB::$tables['users_providers'][0]['uid']);
		$this->assertSame('tok', $opauth->get('auth.credentials.token'));
	}

	public function test_auto_registration_fills_a_password_and_uses_email_as_the_username()
	{
		$this->forgeSimpleAuth();
		$opauth = $this->forgeOpauth(array(
			'provider' => 'Facebook',
			'auto_registration' => true,
		), false);
		$this->callbackPayload($this->authPayload(array(
			'email' => 'ada@example.com',
			'full_name' => 'Ada Lovelace',
		)));

		$this->assertSame('registered', $opauth->login_or_register());
		$this->assertSame('ada@example.com', \Auth\Auth::instance()->get_screen_name());
		$this->assertSame('Ada Lovelace', \Auth\Auth::instance()->get_profile_fields('fullname'));
	}

	public function test_existing_provider_forces_login()
	{
		$driver = $this->forgeSimpleAuth();
		$id = $driver->create_user('ada', 'secret', 'ada@example.com');
		\DB::insert('users_providers')->set(array(
			'id' => 4,
			'parent_id' => $id,
			'provider' => 'Facebook',
			'uid' => 'uid-1',
		))->execute();
		$opauth = $this->forgeOpauth(array('provider' => 'Facebook'), false);
		$this->callbackPayload($this->authPayload());

		$this->assertSame('logged_in', $opauth->login_or_register());
		$this->assertSame('ada', \Session::get('username'));
		$this->assertSame($driver, \Auth\Auth::verified('Simpleauth'));
	}

	public function test_logged_in_user_can_link_a_provider()
	{
		$driver = $this->forgeSimpleAuth();
		$driver->create_user('ada', 'secret', 'ada@example.com');
		$this->assertTrue($driver->login('ada', 'secret'));
		$opauth = $this->forgeOpauth(array(
			'provider' => 'Facebook',
			'link_multiple_providers' => true,
		), false);
		$this->callbackPayload($this->authPayload());

		$this->assertSame('linked', $opauth->login_or_register());
		$this->assertSame(1, \DB::$tables['users_providers'][0]['parent_id']);
		$this->assertSame('Facebook', \DB::$tables['users_providers'][0]['provider']);
		$this->assertSame('1700000000', \DB::$tables['users_providers'][0]['expires']);
	}

	public function test_link_provider_replaces_duplicates_and_parses_expiry()
	{
		$this->forgeSimpleAuth();
		$opauth = $this->forgeOpauth(array('provider' => 'Facebook'), false);
		\DB::insert('users_providers')->set(array(
			'parent_id' => 1,
			'provider' => 'Facebook',
			'uid' => 'uid-1',
			'expires' => 1,
		))->execute();

		$id = $this->swallowWarnings(function () use ($opauth) {
			return $opauth->link_provider(array(
				'parent_id' => 9,
				'provider' => 'Facebook',
				'uid' => 'uid-1',
				'expires' => '2020-01-02 03:04:05',
			));
		});

		$this->assertNotFalse($id);
		$this->assertCount(1, \DB::$tables['users_providers']);
		$this->assertSame(9, \DB::$tables['users_providers'][0]['parent_id']);
		$this->assertSame(\DateTime::createFromFormat('Y-m-d H:i:s', '2020-01-02 03:04:05')->getTimestamp(), \DB::$tables['users_providers'][0]['expires']);

		$before = time();
		$this->swallowWarnings(function () use ($opauth) {
			$opauth->link_provider(array(
				'parent_id' => 9,
				'provider' => 'Facebook',
				'uid' => 'uid-2',
				'expires' => 'not-a-date',
			));
		});
		$this->assertGreaterThanOrEqual($before, \DB::$tables['users_providers'][1]['expires']);
		$this->assertLessThanOrEqual(time(), \DB::$tables['users_providers'][1]['expires']);
	}

	public function test_a_user_cannot_link_a_second_provider_when_that_is_disabled()
	{
		$driver = $this->forgeSimpleAuth();
		$id = $driver->create_user('ada', 'secret', 'ada@example.com');
		\DB::insert('users_providers')->set(array(
			'parent_id' => $id,
			'provider' => 'Google',
			'uid' => 'google-1',
		))->execute();
		$this->assertTrue($driver->login('ada', 'secret'));
		\Config::set('opauth.link_multiple_providers', false);
		$opauth = $this->forgeOpauth(array(
			'provider' => 'Facebook',
		), false);
		$this->callbackPayload($this->authPayload());

		$this->expectException(\OpauthException::class);
		$this->expectExceptionMessage('already linked to "Google"');
		$opauth->login_or_register();
	}

	public function test_get_returns_the_default_when_the_response_is_not_an_array()
	{
		$opauth = $this->forgeOpauth(array('provider' => 'Facebook'), false);
		$property = new ReflectionProperty($opauth, 'response');
		$property->setValue($opauth, 'nope');

		$this->assertSame('fallback', $opauth->get('auth.uid', 'fallback'));
	}

	public function test_create_user_builds_a_fullname_from_first_and_last_name()
	{
		$this->forgeSimpleAuth();
		$opauth = $this->forgeOpauth(array('provider' => 'Facebook', 'default_group' => 1), false);
		$method = new ReflectionMethod($opauth, 'create_user');
		$id = $method->invoke($opauth, array(
			'nickname' => 'ada',
			'email' => 'ada@example.com',
			'password' => 'secret-pass',
			'first_name' => 'Ada',
			'last_name' => 'Lovelace',
		));

		$this->assertSame(1, $id);
		$driver = \Auth\Auth::instance();
		$driver->force_login($id);
		$this->assertSame('Ada Lovelace', $driver->get_profile_fields('fullname'));
	}
}
