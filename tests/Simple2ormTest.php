<?php

use Fuel\Tasks\Simple2orm;

class Simple2ormTest extends TestCase
{
	public function test_run_without_options_prints_help()
	{
		Simple2orm::run();
		$text = \Cli::$output[0][0];

		$this->assertStringContainsString('converts an existing SimpleAuth setup to OrmAuth', $text);
		$this->assertStringContainsString('php oil refine simple2orm:help', $text);
		$this->assertStringContainsString('--validate', $text);
		$this->assertStringContainsString('--migrate', $text);
	}

	public function test_help_can_be_called_directly()
	{
		Simple2orm::help();

		$this->assertStringContainsString('Runtime options', \Cli::$output[0][0]);
	}

	public function test_validate_reports_a_missing_application_environment()
	{
		\Cli::$options = array('validate' => true);

		$this->swallowWarnings(function () {
			Simple2orm::run();
		});

		$text = implode("\n", array_map(function ($line) {
			return $line[0];
		}, \Cli::$output));

		$this->assertStringContainsString('You environment did not validate', $text);
		$this->assertStringContainsString('does not exist', $text);
		$this->assertStringContainsString('Auth database migrations haven\'t run', $text);
	}

	public function test_migrate_is_skipped_when_validation_fails()
	{
		\Cli::$options = array('m' => true);

		$this->swallowWarnings(function () {
			Simple2orm::run();
		});

		$text = implode("\n", array_map(function ($line) {
			return $line[0];
		}, \Cli::$output));

		$this->assertStringContainsString('Validation failed. Skipping the actual migration.', $text);
		$this->assertStringContainsString('light_red', implode(',', array_map(function ($line) {
			return (string) $line[1];
		}, \Cli::$output)));
	}
}
