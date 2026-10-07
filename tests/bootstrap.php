<?php

if ( ! defined('DS'))
{
	define('DS', DIRECTORY_SEPARATOR);
}

if ( ! defined('APPPATH'))
{
	define('APPPATH', sys_get_temp_dir().DS.'fuel-auth-tests-'.getmypid().DS);
}

if ( ! is_dir(APPPATH))
{
	mkdir(APPPATH, 0777, true);
}

require __DIR__.'/FuelStub.php';

$root = dirname(__DIR__);

/**
 * Fuel's autoloader aliases namespaced package classes into the global namespace.
 * Aliases have to exist before a subclass file is loaded, because those files
 * extend the global names (\Auth_Driver, \Auth_Login_Driver, ...).
 */
function fuel_alias($class)
{
	$global = preg_replace('/^Auth\\\\/', '', $class);
	if ( ! class_exists($global, false) and ! interface_exists($global, false))
	{
		class_alias($class, $global);
	}
}

function fuel_require($path, array $classes)
{
	require $path;
	foreach ($classes as $class)
	{
		fuel_alias($class);
	}
}

fuel_require($root.'/classes/auth/exceptions.php', array(
	'Auth\\SimpleUserUpdateException',
	'Auth\\SimpleUserWrongPassword',
	'Auth\\OpauthException',
));
fuel_require($root.'/classes/auth.php', array(
	'Auth\\Auth',
	'Auth\\AuthException',
));
fuel_require($root.'/classes/auth/driver.php', array(
	'Auth\\Auth_Driver',
));
fuel_require($root.'/classes/auth/login/driver.php', array(
	'Auth\\Auth_Login_Driver',
));
fuel_require($root.'/classes/auth/acl/driver.php', array(
	'Auth\\Auth_Acl_Driver',
));
fuel_require($root.'/classes/auth/group/driver.php', array(
	'Auth\\Auth_Group_Driver',
));
fuel_require($root.'/classes/auth/login/simpleauth.php', array(
	'Auth\\Auth_Login_Simpleauth',
));
fuel_require($root.'/classes/auth/acl/simpleacl.php', array(
	'Auth\\Auth_Acl_Simpleacl',
));
fuel_require($root.'/classes/auth/group/simplegroup.php', array(
	'Auth\\Auth_Group_Simplegroup',
));
fuel_require($root.'/classes/auth/opauth.php', array(
	'Auth\\Auth_Opauth',
));
require $root.'/tasks/simple2orm.php';
fuel_require(__DIR__.'/fixtures/drivers.php', array(
	'Auth\\Auth_Login_Stub',
	'Auth\\Auth_Login_Customsimple',
));

require __DIR__.'/TestCase.php';
