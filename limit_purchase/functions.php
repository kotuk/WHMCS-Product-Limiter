<?php
use WHMCS\Database\Capsule;

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

class limit_purchase
{
	var $config;

	function __construct()
	{
		$this->loadConfig();
	}

	function loadConfig()
	{
		$this->config = array();

		foreach (Capsule::table('mod_limit_purchase_config')->get() as $c) {
			$this->config[$c->name] = $c->value;
		}
	}

	function setConfig($name, $value)
	{
		if(isset($this->config[$name]))
		{
			Capsule::table('mod_limit_purchase_config')
				->where('name', mysqli_escape_string($name))
				->update([
					'value' => mysqli_escape_string($value)
				]
			);
		}
		else
		{
			Capsule::table('mod_limit_purchase_config')
				->insert([
					'name'  => mysqli_escape_string($name),
					'value' => mysqli_escape_string($value)
				]
			);
		}

		$this->config[$name] = $value;
	}

	function getLimitedProducts()
	{
		$output = array();
		
		$result = Capsule::table('mod_limit_purchase')
			->join('tblproducts', 'mod_limit_purchase.product_id', '=', 'tblproducts.id')
			->where('mod_limit_purchase.active', 1)
			->get();
		
		foreach ($result as $r) {
			$output[$r->product_id] = array(
				'limit' => $r->limit,
				'error' => $r->error
			);
		}
		
		return $output;
	}
}

?>
