<?php

class NormalizeDriverTypesTest extends TestCase
{
	public function test_default_string_driver_normalizes_to_simpleauth()
	{
		$this->assertSame(array('Simpleauth'), normalize_driver_types());
	}

	public function test_known_drivers_and_duplicates_are_collapsed()
	{
		\Config::set('auth.driver', array('Simpleauth', 'Ormauth', 'Simpleauth', 'Auth\\Ormauth'));

		$this->assertSame(array('Simpleauth', 'Ormauth'), normalize_driver_types());
	}

	public function test_namespaced_short_names_match_the_built_in_drivers()
	{
		\Config::set('auth.driver', array('Auth\\Simpleauth', 'Auth\\Ormauth'));

		$this->assertSame(array('Simpleauth', 'Ormauth'), normalize_driver_types());
	}

	public function test_subclasses_of_the_built_in_drivers_inherit_their_type()
	{
		\Config::set('auth.driver', array('Customsimple', 'Auth\\Customsimple'));

		$this->assertSame(array('Simpleauth'), normalize_driver_types());
	}

	public function test_unknown_drivers_are_left_unchanged()
	{
		\Config::set('auth.driver', array('Nope', 'Auth\\Nope'));

		$this->assertSame(array('Nope', 'Auth\\Nope'), normalize_driver_types());
	}

	public function test_an_empty_driver_list_stays_empty()
	{
		\Config::set('auth.driver', array());

		$this->assertSame(array(), normalize_driver_types());
	}
}
