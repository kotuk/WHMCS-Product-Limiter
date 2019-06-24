<?php
use WHMCS\Database\Capsule;

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

require_once(dirname(__FILE__) . '/functions.php');

add_hook('ShoppingCartValidateCheckout', 1, function ($vars) {
	$errors = array();

	$lp = new limit_purchase;

	$pids = $lp->getLimitedProducts();
	$user_id = intval($_SESSION['uid']);

	if(sizeof($_SESSION['cart']['products']))
	{
		$counter = $delete = array();

		foreach($_SESSION['cart']['products'] as $i => $product_details)
		{
			if(in_array($product_details['pid'], array_keys($pids)))
			{
				if(!isset($counter[$product_details['pid']]))
				{
					$counter[$product_details['pid']] = 0;

					if($user_id)
					{
						$count = Capsule::table('tblhosting')
							->where('userid', $user_id)
							->where('packageid', $product_details['pid'])
							->count();

						$counter[$product_details['pid']] = $count;
					}
				}

				if($pids[$product_details['pid']]['limit'] <= intval($counter[$product_details['pid']]))
				{
					if(!isset($delete[$product_details['pid']]))
					{
						$result = Capsule::table('tblproducts')->where('id', $product_details['pid'])->first();
						$delete[$product_details['pid']] = $result;
					}

					// if you want to automatically delete the unwanted products from the cart, remark the line below
					//unset($_SESSION['cart']['products'][$i]);
				}

				$counter[$product_details['pid']]++;
			}
		}

		foreach($delete as $product_id => $product_details)
		{
			$errors[] = str_replace('{PNAME}', $product_details, $pids[$product_id]['error']);
		}
	}

	return $errors;
});

add_hook('ProductDelete', 1, function ($vars){
	Capsule::table('mod_limit_purchase')->where('product_id', $vars['pid'])->delete();
});
