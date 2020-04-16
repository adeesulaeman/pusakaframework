<?php 
namespace Pusaka\Database\Mysql;

use mysqli;
use mysqli_sql_exception;

use Pusaka\Database\Factory\DriverInterface;

use Pusaka\Database\Exceptions\DatabaseException;
use Pusaka\Database\Exceptions\ConnectionException;
use Pusaka\Database\Exceptions\SqlException;

class Driver implements DriverInterface {

	private $db;

	private $config 		= [];

	private $error 			= NULL;

	function __construct($config = []) {

		mysqli_report(MYSQLI_REPORT_STRICT);

		$this->config = $config;

	}

	function builder() {
		return new Builder($this);
	}

	function factory() {
		return new Factory($this);
	}

	function open() {

		if(isset($this->db)) {
			return $this;
		}

		try {

			$this->db = new mysqli(
				$this->config['hostname'] ?? '127.0.0.1', 
				$this->config['username'] ?? 'root', 
				$this->config['password'] ?? '', 
				$this->config['database'] ?? '',
				$this->config['port'] ?? '3306'
			);

		}catch(mysqli_sql_exception $e) {
			throw new ConnectionException($e->getMessage());
		}

	}

	function close() {

		if(isset($this->db)) {
			if($this->db !== NULL) {
				$this->db->close();
				unset($this->db);
			}
		}

	}

	function transaction() {
		$this->db->begin_transaction();
	}

	function commit() {
		$this->db->commit();
	}

	function rollback() {
		$this->db->rollback();
	}

	function query($query) {

		$result = $this->db->query($query, MYSQLI_USE_RESULT);

		if(!$result) {
			
			$this->error = $this->db->error;

			throw new SqlException($this->error() . "\r\n\r\n" . $query);
		
		}

		return new Result($result);

	}

	function execute($query) {

		$result = $this->db->real_query($query);

		if(!$result) {

			$this->error = $this->db->error;

			throw new SqlException($this->error() . "\r\n\r\n" . $query);
		
		}

		return $result;

	}

	function capsulate($string) {

		return '`'.$string.'`';

	}

	function error() {
		
		$error = '';

		if(is_string($this->error)) {
			
			return $this->error;

		}

		if(is_array($this->error)) {

			foreach ($this->error as $value) {
			
				$error = $value . "\r\n\r\n";

			}

			return $error;

		}

		return $this->error;

	}

	function __destruct() {

		$this->close();
		unset($this->config);
		unset($this->error);

	}

}