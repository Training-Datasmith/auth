<?php

class DbQueryGroupingTest extends TestCase
{
	public function test_where_groups_are_applied_as_nested_conditions()
	{
		\DB::insert('people')->set(array('id' => 1, 'name' => 'ada', 'group' => 1))->execute();
		\DB::insert('people')->set(array('id' => 2, 'name' => 'bob', 'group' => 2))->execute();

		$rows = \DB::select()
			->from('people')
			->where_open()
				->where('group', '=', 1)
			->where_close()
			->where_open()
				->where('name', '=', 'ada')
				->or_where('name', '=', 'bob')
			->where_close()
			->execute();

		$this->assertCount(1, $rows);
		$this->assertSame('ada', $rows->current()['name']);
		$this->assertSame(1, $rows[0]['id']);
	}

	public function test_or_where_open_joins_the_group_with_or()
	{
		\DB::insert('people')->set(array('id' => 1, 'name' => 'ada', 'group' => 1))->execute();
		\DB::insert('people')->set(array('id' => 2, 'name' => 'bob', 'group' => 2))->execute();

		$rows = \DB::select()
			->from('people')
			->where('group', '=', 2)
			->or_where_open()
				->where('name', '=', 'ada')
			->or_where_close()
			->execute();

		$names = array();
		foreach ($rows as $row)
		{
			$names[] = $row['name'];
		}

		$this->assertSame(array('ada', 'bob'), $names);
	}

	public function test_and_binds_tighter_than_or()
	{
		\DB::insert('people')->set(array('id' => 1, 'name' => 'ada', 'group' => 1))->execute();
		\DB::insert('people')->set(array('id' => 2, 'name' => 'bob', 'group' => 2))->execute();

		$rows = \DB::select()
			->from('people')
			->where('name', '=', 'ada')
			->or_where('name', '=', 'bob')
			->where('group', '=', 2)
			->execute();

		$this->assertCount(2, $rows);
	}

	public function test_a_close_without_an_open_group_is_rejected()
	{
		$this->expectException(\LogicException::class);
		\DB::select()->from('people')->where_close();
	}

	public function test_an_unclosed_group_is_rejected()
	{
		$this->expectException(\LogicException::class);
		\DB::select()->from('people')->where_open()->where('id', '=', 1)->execute();
	}

	public function test_unsupported_operators_are_rejected()
	{
		$this->expectException(\LogicException::class);
		\DB::select()->from('people')->where('id', '>', 0)->execute();
	}
}
