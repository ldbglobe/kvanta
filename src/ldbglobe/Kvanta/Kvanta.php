<?php
namespace ldbglobe\Kvanta;

class Kvanta {

	public $db_handler = null;
	public $env_name = null;

	public function __construct($db_handler,$env_name)
	{
		$this->db_handler = $db_handler;
		$this->env_name = $env_name;

		$this->init();
	}

	public function init()
	{
		$env_name = $this->env_name;

		if(!$this->table_exists($env_name.'_services'))
		{
			$this->db_handler->query("CREATE TABLE `${env_name}_services` (
				`code` varchar(64) NOT NULL,
				`limit` int(11) NOT NULL,
				`periodicity` varchar(32) NOT NULL,
				`cdate` datetime NOT NULL,
				`mdate` datetime NOT NULL,
				`active` tinyint(1) NOT NULL,
				`history` text NOT NULL,
				`history_time` datetime DEFAULT NULL,
				PRIMARY KEY (`code`)
			);");
		}
		if(!$this->table_exists($env_name.'_transactions'))
		{
			$this->db_handler->query("CREATE TABLE `${env_name}_transactions` (
				`code` varchar(64) NOT NULL,
				`cdate` datetime NOT NULL,
				`quantity` int(11) NOT NULL,
				`utime` decimal(16,6) NOT NULL,
				PRIMARY KEY (`code`,`utime`) USING BTREE
			);");
		}
		
	}

	public function load($code,$limit=null,$periodicity=null)
	{
		$service = new \ldbglobe\Kvanta\Kvanta_Services($this,$code);
		if($service->ready())
			return $service;

		if($limit && $periodicity)
			$service = $this->create($code,$limit,$periodicity);

		if($service->ready())
			return $service;

		unset($service);
		return FALSE;
	}

	public function create($code,$limit,$periodicity) // yearly, monthly, daily, hourly
	{
		$env_name = $this->env_name;

		$db_statement = $this->db_handler->prepare("INSERT INTO `${env_name}_services` SET
			`code`=:_code,
			`limit`=:_limit,
			`periodicity`=:_periodicity,
			`cdate`=NOW(),
			`mdate`=NOW(),
			`active`=:_active,
			`history`=\"\"
		;");
		$db_statement->execute(array(
			'_code'=>$code,
			'_limit'=>$limit,
			'_periodicity'=>$periodicity,
			'_active'=>1
		));
		return $this->load($code);
	}

	private function table_exists($table_name)
	{
		try {
			$result = $this->db_handler->query("SELECT 1 FROM `${table_name}` LIMIT 1");
			// Result is either boolean FALSE (no table found) or PDOStatement Object (table found)
			return $result !== FALSE;
		} catch (\Exception $e) {
			// We got an exception == table not found
			return FALSE;
		}
	}
}

class Kvanta_Services {

	private $kvanta_instance = null;
	private $code = null;
	private $settings = null;

	public function __construct($kvanta_instance,$code)
	{
		$this->kvanta_instance = $kvanta_instance;
		$this->code = $code;

		$this->init();
	}

	private function _db_handler()
	{
		return $this->kvanta_instance->db_handler;
	}

	private function _env_name()
	{
		return $this->kvanta_instance->env_name;
	}

	public function init()
	{
		$env_name = $this->_env_name();
		$code = $this->code;
		try {
			$result = $this->_db_handler()->query("SELECT * FROM `${env_name}_services` WHERE `code`=\"${code}\"");
		} catch (\Exception $e) {
			$result = FALSE;
		}
		if($result)
		{
			$this->settings = $result->fetchObject();
		}
	}

	public function ready()
	{
		return is_object($this->settings);
	}

	public function getCDate()
	{
		return isset($this->settings->cdate) ? $this->settings->cdate : null;
	}
	public function getMDate()
	{
		return isset($this->settings->mdate) ? $this->settings->mdate : null;
	}

	public function getLimit()
	{
		return isset($this->settings->limit) ? $this->settings->limit : null;
	}
	public function getPeriodicity()
	{
		return isset($this->settings->periodicity) ? $this->settings->periodicity : null;
	}
	public function setLimit($v)
	{
		$env_name = $this->_env_name();
		$code = $this->code;
		try {
			$db_statement = $this->_db_handler()->prepare("UPDATE `${env_name}_services` SET `limit`=:v, `mdate`=NOW() WHERE `code`=:_code");
			$db_statement->execute(array(
				'v'=>$v,
				'_code'=>$code
			));
			$this->init();
			$this->clearHistory();
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}
	public function setPeriodicity($v)
	{
		$env_name = $this->_env_name();
		$code = $this->code;
		try {
			$db_statement = $this->_db_handler()->prepare("UPDATE `${env_name}_services` SET `periodicity`=:v, `mdate`=NOW() WHERE `code`=:_code");
			$db_statement->execute(array(
				'v'=>$v,
				'_code'=>$code
			));
			$this->init();
			$this->clearHistory();
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function removeQuota($quantity,$time=null)
	{
		$env_name = $this->_env_name();
		$code = $this->code;
		try {
			$cdate = $time ? '"'.date('Y-m-d H:i:s',$time).'"':'NOW()';
			$db_statement = $this->_db_handler()->prepare("INSERT INTO `${env_name}_transactions` SET `quantity`=:_quantity, `cdate`=${cdate}, `code`=:_code, `utime`=UNIX_TIMESTAMP(NOW(6))");
			$db_statement->execute(array(
				'_quantity'=>-(abs($quantity)),
				'_code'=>$code
			));
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function addQuota($quantity,$time=null)
	{
		$env_name = $this->_env_name();
		$code = $this->code;
		try {
			$cdate = $time ? '"'.date('Y-m-d H:i:s',$time).'"':'NOW()';
			$db_statement = $this->_db_handler()->prepare("INSERT INTO `${env_name}_transactions` SET `quantity`=:_quantity, `cdate`=${cdate}, `code`=:_code, `utime`=UNIX_TIMESTAMP(NOW(6))");
			$db_statement->execute(array(
				'_quantity'=>abs($quantity),
				'_code'=>$code
			));
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function clearTransactions()
	{
		$env_name = $this->_env_name();
		$code = $this->code;
		try {
			$db_statement = $this->_db_handler()->prepare("DELETE FROM `${env_name}_transactions` WHERE `code`=:_code");
			$db_statement->execute(array(
				'_code'=>$code
			));
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function periodLimit($index=0)
	{
		// $index = 0 => current period
		// $index = -1 => previous period and so on ...

		$periodicity = $this->getPeriodicity();
		switch($periodicity) { //yearly, monthly, daily, hourly
			case 'hourly':
				$from = date('Y-m-d H:00:00',time()-3600*$index);
				$to = date('Y-m-d H:59:59',time()-3600*$index);
				break;
			case 'daily':
				$from = date('Y-m-d 00:00:00',time()-3600*24*$index);
				$to = date('Y-m-d 23:59:59',time()-3600*24*$index);
				break;
			case 'monthly':
				$from = date('Y-m-01 00:00:00',strtotime("-${index} month"));
				$to = date('Y-m-t 23:59:59',strtotime("-${index} month"));
				break;
			case 'yearly':
				$cmonth = date('m',strtotime($this->getCDate()));
				$time = strtotime(date('Y-'.$cmonth.'-01 00:00:00'));
				$from = date('Y-m-01 00:00:00',strtotime("-${index} year",$time));
				$to = date('Y-m-t 23:59:59',strtotime("-${index} year +11 month",$time));
				break;
			default:
				return FALSE;
				break;
		}
		return array($from,$to);
	}

	public function getRemainingQuota()
	{
		$quota = $this->getQuota();
		if($quota && isset($quota->transaction_balance))
		{
			return $this->getLimit() + $quota->transaction_balance;
		}
	}

	public function getQuota($index=0)
	{
		// return current quota based on default limit, periodicity and processed transactions during the current period

		$period = $this->periodLimit($index);
		if($period)
		{
			return $this->getQuotaBetween($period[0],$period[1]);
		}
		return FALSE;
	}

	public function getQuotaBetween($from,$to)
	{
		$period = array($from,$to);

		$env_name = $this->_env_name();
		$code = $this->code;

		$periodicity = $this->getPeriodicity();
		$limit = $limit = $this->getLimit();
		$credits = 0;
		$credits_count = 0;
		$debits = 0;
		$debits_count = 0;

		try {
			$db_statement = $this->_db_handler()->prepare("SELECT
					SUM(Case When quantity > 0 Then quantity Else 0 End) AS credits,
					SUM(Case When quantity > 0 Then 1 Else 0 End) AS credits_count,
					SUM(Case When quantity < 0 Then quantity Else 0 End) AS debits,
					SUM(Case When quantity < 0 Then 1 Else 0 End) AS debits_count
				FROM 
					`${env_name}_transactions`
				WHERE 
					`code`=:_code
					AND (`cdate` BETWEEN :_from AND :_to)
			");
			$result = $db_statement->execute(array(
				'_code'=>$code,
				'_from'=>$period[0],
				'_to'=>$period[1],
			));
			if($result)
			{
				$data = $db_statement->fetchObject();
				$credits = abs($data->credits ? $data->credits : 0);
				$credits_count = abs($data->credits_count ? $data->credits_count : 0);
				$debits = abs($data->debits ? $data->debits : 0);
				$debits_count = abs($data->debits_count ? $data->debits_count : 0);
			}
		} catch (\Exception $e) {
			// nothing to do
		}

		$humanized_period = '';
		switch($periodicity) { //yearly, monthly, daily, hourly
			case 'hourly':
				$humanized_period = date('H\h (Y-m-d)',strtotime($period[0]));
				break;
			case 'daily':
				$humanized_period = date('Y-m-d',strtotime($period[0]));
				break;
			case 'monthly':
				$humanized_period = date('Y-m',strtotime($period[0]));
				break;
			case 'yearly':
				$humanized_period = date('Y-m',strtotime($period[0])).' / '.date('Y-m',strtotime($period[1]));
				break;
			default:
				break;
		}

		return (object)array(
			'from'=>$period[0],
			'to'=>$period[1],
			'humanized_period'=>$humanized_period,
			'credits'=>$credits,
			'credits_count'=>$credits_count,
			'debits'=>$debits,
			'debits_count'=>$debits_count,
			'transaction_balance'=>$credits - $debits,
			'transaction_count'=>$credits_count + $debits_count,
		);
	}

	public function getHistory()
	{
		$env_name = $this->_env_name();
		$code = $this->code;
		try {
			$db_statement = $this->_db_handler()->prepare("SELECT `history` FROM `${env_name}_services` WHERE `code`=:_code AND history_time > \"".date('Y-m-d H:i:s',time()-3600)."\"");
			$result =  $db_statement->execute(array(
				'_code'=>$code
			));
			if(!$result)
			{
				$history = $this->buildHistory();
			}
			else 
			{
				$data = $db_statement->fetchObject();
				$history = isset($data->history) ? json_decode($data->history) : FALSE;
				$history = $history ? $history : $this->buildHistory();

			}
			return $history;

		} catch (\Exception $e) {
			return false;
		}
	}

	public function buildHistory()
	{
		$periodicity = $this->getPeriodicity();
		switch($periodicity) { //yearly, monthly, daily, hourly
			case 'hourly':
				$loop = 24*7;
				break;
			case 'daily':
				$loop = 30*6;
				break;
			case 'monthly':
				$loop = 12*5;
				break;
			case 'yearly':
				$loop = 5;
				break;
			default:
				return FALSE;
				break;
		}
		$history = array();
		for($i=1;$i<=$loop;$i++)
		{
			$h = $this->getQuota($i);
			$history[] = $h;
		}

		$env_name = $this->_env_name();
		$code = $this->code;
		try {
			$db_statement = $this->_db_handler()->prepare("UPDATE `${env_name}_services` SET `history`=:_history, `history_time`=NOW() WHERE `code`=:_code");
			$db_statement->execute(array(
				'_history'=>json_encode($history),
				'_code'=>$code
			));
			return $history;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function clearHistory()
	{
		$env_name = $this->_env_name();
		$code = $this->code;
		try {
			$db_statement = $this->_db_handler()->prepare("UPDATE `${env_name}_services` SET `history`=\"\", `history_time`=NULL WHERE `code`=:_code");
			$db_statement->execute(array(
				'_code'=>$code
			));
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}
}