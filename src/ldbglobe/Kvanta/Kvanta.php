<?php
namespace ldbglobe\Kvanta;

class Kvanta {

	private $db_handler = null;
	private $table_name = null;

	public function __construct($db_handler,$table_name)
	{
		$this->db_handler = $db_handler;
		$this->table_name = $table_name;
	}
}