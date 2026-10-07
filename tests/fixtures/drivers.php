<?php

namespace Auth;

class Auth_Login_Stub extends \Auth_Login_Driver
{
	public $checks = 0;
	public $logins = 0;
	public $logouts = 0;
	public $login_result = false;
	public $check_result = false;

	protected function perform_check()
	{
		$this->checks++;

		return $this->check_result;
	}

	public function validate_user($username_or_email = '', $password = '')
	{
		return $this->login_result;
	}

	public function login($username_or_email = '', $password = '')
	{
		$this->logins++;
		if ($this->login_result)
		{
			Auth::_register_verified($this);
			return true;
		}

		return false;
	}

	public function logout()
	{
		$this->logouts++;
	}

	public function get_user_id()
	{
		return array($this->id, 1);
	}

	public function get_groups()
	{
		return array(array('Simplegroup', 1));
	}

	public function get_email()
	{
		return 'stub@example.com';
	}

	public function get_screen_name()
	{
		return 'stub';
	}
}

class Auth_Login_Customsimple extends Auth_Login_Simpleauth {}
