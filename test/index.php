<?php
require "../vendor/autoload.php";

use ldbglobe\Kvanta\Kvanta;

$dsn = 'mysql:dbname=test;host=127.0.0.1';
$user = 'root';
$password = '';

try {
    $dbh = new PDO($dsn, $user, $password);
} catch (PDOException $e) {
    echo 'Connexion échouée : ' . $e->getMessage();
}

$kvanta = new Kvanta($dbh,'kvanta_sample');

$kv_service = $kvanta->load('test_service');
if(!$kv_service)
{
	$kv_service = $kvanta->create('test_service',30000,'yearly');
}

/*
$kv_service->setPeriodicity('monthly');
$kv_service->setLimit(100);
*/

$periodicity = $kv_service->getPeriodicity();
$limit = $kv_service->getLimit();
echo $periodicity.':'.$limit."\n";

for($i=0;$i<10;$i++)
{
	$kv_service->removeQuota(rand(5,10),time()-0*rand(0,3600*24*365*5));
	$kv_service->addQuota(rand(1,5),time()-0*rand(0,3600*24*365*5));
}

//$kv_service->buildHistory();
print_r($kv_service->getQuota());
print_r($kv_service->getHistory());

//$kv_service->clearTransactions();