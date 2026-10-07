<?php

class SimpleAclTest extends TestCase
{
	protected function acl()
	{
		$this->forgeSimpleAuth();

		return \Auth\Auth::acl('Simpleacl');
	}

	public function test_roles_lists_configured_role_names()
	{
		$acl = $this->acl();

		$this->assertSame(array('#', 'user', 'moderator', 'banned', 'admin'), $acl->roles());
	}

	public function test_user_rights_include_the_wildcard_role()
	{
		$acl = $this->acl();
		$entity = array('Simplegroup', 1);

		$this->assertTrue($acl->has_access('website.read', $entity));
		$this->assertTrue($acl->has_access('comments.[create, read]', $entity));
		$this->assertFalse($acl->has_access('comments.[create, delete]', $entity));
		$this->assertFalse($acl->has_access('posts.read', $entity));
		$this->assertTrue($acl->has_access(array('comments', 'read'), $entity));
	}

	public function test_area_without_a_right_is_allowed_when_no_boolean_role_denies_it()
	{
		$acl = $this->acl();

		$this->assertTrue($acl->has_access('secrets', array('Simplegroup', 1)));
		$this->assertFalse($acl->has_access('secrets.delete', array('Simplegroup', 1)));
	}

	public function test_moderator_rights_are_merged_across_roles_and_areas()
	{
		$acl = $this->acl();
		$entity = array('Simplegroup', 50);

		$this->assertTrue($acl->has_access('comments.[create, read, update, delete]', $entity));
		$this->assertTrue($acl->has_access('posts.read', $entity));
		$this->assertFalse($acl->has_access('posts.delete', $entity));
	}

	public function test_boolean_roles_short_circuit()
	{
		$acl = $this->acl();

		$this->assertFalse($acl->has_access('website.read', array('Simplegroup', -1)));
		$this->assertTrue($acl->has_access('secrets.delete', array('Simplegroup', 100)));
	}

	public function test_a_later_allow_does_not_override_an_earlier_boolean_deny()
	{
		\Config::set('simpleauth.groups', $this->groupConfig() + array(
			7 => array('name' => 'Conflicted', 'roles' => array('banned', 'admin')),
		));
		$this->refreshSimpleDrivers();
		$acl = $this->acl();

		$this->assertFalse($acl->has_access('website.read', array('Simplegroup', 7)));
	}

	public function test_unknown_groups_still_receive_the_wildcard_role()
	{
		$acl = $this->acl();

		$this->assertTrue($acl->has_access('website.read', array('Simplegroup', 404)));
		$this->assertFalse($acl->has_access('comments.create', array('Simplegroup', 404)));
	}

	public function test_missing_role_names_are_skipped()
	{
		\Config::set('simpleauth.groups', $this->groupConfig() + array(
			8 => array('name' => 'Ghosts', 'roles' => array('ghost')),
		));
		\Config::set('simpleauth.roles', array(
			'user' => array('comments' => array('read')),
		));
		$this->refreshSimpleDrivers();
		$acl = $this->acl();

		$this->assertFalse($acl->has_access('website.read', array('Simplegroup', 8)));
		$this->assertFalse($acl->has_access('comments.read', array('Simplegroup', 8)));
		$this->assertTrue($acl->has_access('comments.read', array('Simplegroup', 1)));
	}

	public function test_missing_group_driver_or_unusable_condition_denies_access()
	{
		$acl = $this->acl();

		$this->assertFalse($acl->has_access('website.read', array('missing', 1)));
		// A null driver id resolves to the default group driver.
		$this->assertTrue($acl->has_access('website.read', array(null, 1)));
	}

	public function test_roles_list_refreshes_when_the_driver_is_reinitialised()
	{
		$acl = $this->acl();
		\Config::set('simpleauth.roles', array('editor' => array('posts' => array('update'))));
		\Auth\Auth_Acl_Simpleacl::_init();

		$this->assertSame(array('editor'), $acl->roles());
	}
}
