<?php

class AclDriverTest extends TestCase
{
	public static function conditionProvider()
	{
		return array(
			'area only adds an empty right' => array('comments', array('comments', '')),
			'empty string becomes an empty area' => array('', array('', '')),
			'single right' => array('comments.read', array('comments', 'read')),
			'bracket list trims spaces' => array('comments.[ update , delete ]', array('comments', array('update', 'delete'))),
			'tight bracket list' => array('comments.[read]', array('comments', array('read'))),
			'array conditions pass through' => array(array('posts', array('create')), array('posts', array('create'))),
			'unclosed bracket stays a string' => array('comments.[read', array('comments', '[read')),
		);
	}

	/**
	 * @dataProvider conditionProvider
	 */
	public function test_parse_conditions($input, $expected)
	{
		$this->assertSame($expected, \Auth\Auth_Acl_Driver::_parse_conditions($input));
	}

	public function test_conditions_with_more_than_two_segments_are_not_truncated()
	{
		$this->markTestIncomplete(
			'Auth_Acl_Driver::_parse_conditions() (classes/auth/acl/driver.php:79) keeps only the first two segments, so "admin.users.delete" is checked as "admin.users" and can over-grant. Rejecting or supporting deeper conditions is a behavior change, so it is left incomplete.'
		);
	}

	public function test_parse_conditions_rejects_non_strings()
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Received: 12');
		\Auth\Auth_Acl_Driver::_parse_conditions(12);
	}

	public function test_has_any_and_has_all_access_on_the_acl_driver()
	{
		$this->forgeSimpleAuth();
		$acl = \Auth\Auth::acl('Simpleacl');
		$entity = array('Simplegroup', 50);

		$this->assertTrue($acl->has_any_access(array('comments.delete', 'posts.create'), $entity));
		$this->assertFalse($acl->has_any_access(array('posts.create', 'posts.delete'), $entity));
		$this->assertFalse($acl->has_any_access(array(), $entity));

		$this->assertTrue($acl->has_all_access(array('comments.read', 'posts.read'), $entity));
		$this->assertFalse($acl->has_all_access(array('comments.read', 'posts.create'), $entity));
		$this->assertTrue($acl->has_all_access(array(), $entity));
	}
}
