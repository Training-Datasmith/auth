<?php

class SimpleGroupTest extends TestCase
{
	public function test_get_name_requires_a_login_instance_when_the_group_is_omitted()
	{
		\Auth\Auth_Group_Driver::forge(array('driver' => 'Simplegroup'));

		$this->expectException(\AuthException::class);
		$this->expectExceptionMessage('No auth driver specified');
		\Auth\Auth::group('Simplegroup')->get_name();
	}

	public function test_group_catalog_names_and_roles()
	{
		$this->forgeSimpleAuth();
		$group = \Auth\Auth::group('Simplegroup');

		$this->assertSame(array(-1, 0, 1, 50, 100), $group->groups());
		$this->assertSame('Users', $group->get_name(1));
		$this->assertSame('Banned', $group->get_name(-1));
		$this->assertNull($group->get_name(404));
		$this->assertSame(array('user'), $group->get_roles(1));
		$this->assertSame(array('user', 'moderator'), $group->get_roles('50'));
		$this->assertSame(array('banned'), $group->get_roles(-1));
		$this->assertSame(array(), $group->get_roles(404));
	}

	public function test_current_guest_membership_name_and_roles()
	{
		$login = $this->bootSimpleAuth();
		$this->assertFalse($login->check());
		$group = \Auth\Auth::group('Simplegroup');

		$this->assertTrue($group->member(0));
		$this->assertFalse($group->member(1));
		$this->assertFalse($group->member(404));
		$this->assertSame('Guests', $group->get_name());
		$this->assertSame(array(), $group->get_roles());
	}

	public function test_membership_uses_the_loaded_login_driver_not_the_supplied_user_id()
	{
		$login = $this->bootSimpleAuth();
		$login->create_user('ada', 'secret', 'ada@example.com', 50);
		$this->assertTrue($login->login('ada', 'secret'));
		$group = \Auth\Auth::group('Simplegroup');

		$this->assertTrue($group->member(50));
		$this->assertFalse($group->member(1));
		$this->assertTrue($group->member(50, array('Simpleauth', 999)));
		$this->assertFalse($group->member(1, array('Simpleauth', 999)));
		$this->assertSame('Moderators', $group->get_name());
		$this->assertSame(array('user', 'moderator'), $group->get_roles());
	}

	public function test_membership_lookup_for_an_unknown_login_driver_errors()
	{
		$this->forgeSimpleAuth();
		$group = \Auth\Auth::group('Simplegroup');

		$this->expectException(\Error::class);
		$group->member(1, array('missing', 1));
	}

	public function test_login_driver_aggregates_group_and_role_lists()
	{
		$login = $this->forgeSimpleAuth();

		$this->assertSame(array(-1, 0, 1, 50, 100), $login->groups());
		$this->assertSame(array('#', 'user', 'moderator', 'banned', 'admin'), $login->roles());
		$this->assertSame(array(-1, 0, 1, 50, 100), $login->groups('Simplegroup'));
		$this->assertSame(array('#', 'user', 'moderator', 'banned', 'admin'), $login->roles('Simpleacl'));
	}

	public function test_group_access_helpers_check_the_current_user()
	{
		$login = $this->forgeSimpleAuth();
		$login->create_user('ada', 'secret', 'ada@example.com', 1);
		$this->assertTrue($login->login('ada', 'secret'));
		$group = \Auth\Auth::group('Simplegroup');

		$this->assertTrue($group->has_any_access(array('secrets.delete'), null, array('Simplegroup', 100)));
		$this->assertFalse($group->has_any_access(array('comments.delete'), 'Simpleacl', array('Simplegroup', 1)));
		$this->assertFalse($group->has_all_access(array('comments.read', 'comments.delete'), 'Simpleacl', array('Simplegroup', 1)));
		$this->assertTrue($group->has_all_access(array('comments.read', 'comments.create'), 'Simpleacl', array('Simplegroup', 1)));
		$this->assertTrue($group->has_access('comments.read', null, array('Simplegroup', 1)));
		$this->assertFalse($group->has_access('comments.delete', 'Simpleacl', array('Simplegroup', 1)));
	}

	public function test_group_has_access_for_verified_users_does_not_overwrite_driver_id()
	{
		$this->markTestIncomplete(
			'Auth_Group_Driver::has_access() assigns $this->id = $g_id[0] (classes/auth/group/driver.php:91) when no group is given. That overwrites the driver id while scanning verified users. Left incomplete because changing it is ambiguous when more than one group driver is loaded.'
		);
	}

	public function test_group_access_without_a_verified_user_fails()
	{
		$this->forgeSimpleAuth();
		$group = \Auth\Auth::group('Simplegroup');

		$this->assertFalse($group->has_access('website.read', null, null));
	}
}
