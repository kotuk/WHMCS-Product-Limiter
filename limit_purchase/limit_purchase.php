<?php
use WHMCS\Database\Capsule;

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

function limit_purchase_config() 
{
	return array(
		"name" 		=> "Product Limiter",
		"description" 	=> "This addon allows you to limit the purchase of an products/services for each client",
		"version" 	=> "1.0.6",
		"author" 	=> "KoTuK",
		"language" 	=> "english",
	);
}

function limit_purchase_activate() 
{	
	try {
		/** Delete the table */
		Capsule::schema()->dropIfExists('mod_limit_purchase_config');
		Capsule::schema()->dropIfExists('mod_limit_purchase');
		
	    Capsule::schema()->create(
			'mod_limit_purchase_config',
			function ($table) {
				/** @var \Illuminate\Database\Schema\Blueprint $table */
				$table->string('name', 255);
				$table->text('value');
			}
	    );
		
	    Capsule::schema()->create(
			'mod_limit_purchase',
			function ($table) {
				/** @var \Illuminate\Database\Schema\Blueprint $table */
				$table->increments('id');
				$table->integer('product_id')->default(0);
				$table->integer('limit')->default(0);
				$table->text('error');
				$table->tinyInteger('active')->default(0);
			}
	    );
		
		Capsule::table('mod_limit_purchase_config')->insert([
			[
				"name" => "localkey",
				"value" => ''
			],
			[
				"name" => "version_check",
				"value" => '0'
			],
			[
				"name" => "version_new",
				"value" => ''
			],
		]);
	} catch (\Exception $e) {
	    $error[] = "Unable to create table: {$e->getMessage()}";
	}

	if(sizeof($error))
	{
		limit_purchase_deactivate();
	}

	return array(
		'status'	=> sizeof($error) ? 'error' : 'success',
		'description'	=> sizeof($error) ? implode(" -> ", $error) : '',
	);
}

function limit_purchase_deactivate() 
{
	try {
		Capsule::schema()->dropIfExists('mod_limit_purchase_config');
		Capsule::schema()->dropIfExists('mod_limit_purchase');
	} catch (\Exception $e) {
	    $error[] = "Unable to create table: {$e->getMessage()}";
	}
	
	return array(
		'status'	=> sizeof($error) ? 'error' : 'success',
		'description'	=> sizeof($error) ? implode(" -> ", $error) : '',
	);
}

function limit_purchase_upgrade($vars) 
{
	if(version_compare($vars['version'], '1.0.1', '<'))
	{
		Capsule::schema()->dropIfExists('mod_limit_purchase_config');
		
	    Capsule::schema()->create(
			'mod_limit_purchase_config',
			function ($table) {
				/** @var \Illuminate\Database\Schema\Blueprint $table */
				$table->string('name', 255);
				$table->text('value');
			}
	    );
		
		Capsule::table('mod_limit_purchase_config')->insert([
			[
				"name" => "localkey",
				"value" => ''
			],
			[
				"name" => "version_check",
				"value" => '0'
			],
			[
				"name" => "version_new",
				"value" => ''
			],
		]);
	}
}

function limit_purchase_output($vars) 
{
	$modulelink = $vars['modulelink'];
	$version = $vars['version'];

	require_once(dirname(__FILE__) . '/functions.php');

	$lp = new limit_purchase;

	if($lp->config['version_check'] <= (time() - (60 * 60 * 24)))
	{
		$url = "http://clients.jetserver.net/version/limitpurchase.txt";

		$remote_version = file_get_contents($url);
		$remote_version = trim($remote_version);

		if($remote_version)
		{
			$lp->setConfig('version_new', $remote_version);
			$lp->config['version_new'] = $remote_version;
		}

		$lp->setConfig('version_check', time());
	}

	if(version_compare($version, $lp->config['version_new'], '<'))
	{
?>
		<div class="infobox">
			<strong><span class="title"><?php echo $vars['_lang']['newversiontitle']; ?></span></strong><br />
			<?php echo sprintf($vars['_lang']['newversiondesc'], $lp->config['version_new']); ?>
		</div>
<?php
	}

	$ids = $limits = array();

	$action 	= $_REQUEST['action'];
	$product_id 	= intval($_REQUEST['product_id']);
	$id 		= intval($_REQUEST['id']);
	$limit 		= intval($_REQUEST['limit']);
	$error 		= mysqli_escape_string($_REQUEST['error']) ? mysqli_escape_string($_REQUEST['error']) : 'Can only be purchased once';
	$active 	= intval($_REQUEST['active']);

	$manage_details = array();

	switch($action)
	{
		case 'enable':
		case 'disable': 

			if($id)
			{
				$result = Capsule::table('mod_limit_purchase')->where('id', $id)->get();
				$limit_details = $result[0]->id;

				if($limit_details)
				{
					Capsule::table('mod_limit_purchase')->where('id', $id)->update([
						'active' => $action == 'disable' ? 0 : 1
					]);

					$_SESSION['limit_purchase'] = array(
						'type'		=> 'success',
						'message'	=> $vars['_lang']['actionlimit' . ($action == 'disable' ? 'disabled' : 'enabled')],
					);
				}
				else
				{
					$_SESSION['limit_purchase'] = array(
						'type'		=> 'error',
						'message'	=> $vars['_lang']['actionnolimitid'],
					);
				}
			}
			else
			{
				$_SESSION['limit_purchase'] = array(
					'type'		=> 'error',
					'message'	=> $vars['_lang']['actionnolimitprovided'],
				);
			}

			header('Location: ' . $modulelink);
			exit;

		break;

		case 'add':

			if($product_id)
			{
				$result = Capsule::table('tblproducts')->where('id', $product_id)->get();

				$product_details = $result[0]->id;

				if($product_details)
				{
					$result = Capsule::table('mod_limit_purchase')->where('product_id', $product_id)->get();
					$limit_details = $result[0]->id;

					if(!$limit_details)
					{
						if($limit > 0 && $error)
						{
							Capsule::table('mod_limit_purchase')->insert([
								'product_id' => $product_id,
								'limit'      => $limit,
								'error'      => $error,
								'active'     => $active ? 1 : 0
							]);

							$_SESSION['limit_purchase'] = array(
								'type'		=> 'success',
								'message'	=> $vars['_lang']['actionadded'],
							);
						}
						else
						{
							$errors = array();

							if(!$limit) $errors[] = '&bull; ' . $vars['_lang']['limit'];
							if(!$error) $errors[] = '&bull; ' . $vars['_lang']['errormessage'];

							$_SESSION['limit_purchase'] = array(
								'type'		=> 'error',
								'message'	=> $vars['_lang']['actionfieldsreq'] . '<br />' . implode("<br />", $errors),
							);
						}
					}
					else
					{
						$_SESSION['limit_purchase'] = array(
							'type'		=> 'error',
							'message'	=> $vars['_lang']['actionlimitexists'],
						);
					}
				}
				else
				{
					$_SESSION['limit_purchase'] = array(
						'type'		=> 'error',
						'message'	=> $vars['_lang']['actionnoproductid'],
					);
				}
			}
			else
			{
				$_SESSION['limit_purchase'] = array(
					'type'		=> 'error',
					'message'	=> $vars['_lang']['actionselectproduct'],
				);
			}

			header('Location: ' . $modulelink);
			exit;
		break;

		case 'edit':

			if($id)
			{
				$result = Capsule::table('mod_limit_purchase')->where('id', $id)->get();
				$limit_details = $result[0]->id;

				if($limit_details)
				{
					if($product_id)
					{
						$result = Capsule::table('tblproducts')->where('id', $product_id)->get();
						$product_details = $result[0]->id;
						
						if($product_details)
						{
							if($limit > 0 && $error)
							{
								Capsule::table('mod_limit_purchase')
									->where('id', $id)
									->update([
									'product_id' => $product_id,
									'limit'      => $limit,
									'error'      => $error,
									'active'     => $active ? 1 : 0
								]);

								$_SESSION['limit_purchase'] = array(
									'type'		=> 'success',
									'message'	=> $vars['_lang']['actionlimitedited'],
								);
							}
							else
							{
								$errors = array();

								if(!$limit) $errors[] = '&bull; ' . $vars['_lang']['limit'];
								if(!$error) $errors[] = '&bull; ' . $vars['_lang']['errormessage'];

								$_SESSION['limit_purchase'] = array(
									'type'		=> 'error',
									'message'	=> $vars['_lang']['actionfieldsreq'] . '<br />' . implode("<br />", $errors),
								);
							}
						}
						else
						{
							$_SESSION['limit_purchase'] = array(
								'type'		=> 'error',
								'message'	=> $vars['_lang']['actionnoproductid'],
							);
						}
					}
					else
					{
						$_SESSION['limit_purchase'] = array(
							'type'		=> 'error',
							'message'	=> $vars['_lang']['actionselectproduct'],
						);
					}
				}
				else
				{
					$_SESSION['limit_purchase'] = array(
						'type'		=> 'error',
						'message'	=> $vars['_lang']['actionnolimitid'],
					);
				}
			}
			else
			{
				$_SESSION['limit_purchase'] = array(
					'type'		=> 'error',
					'message'	=> $vars['_lang']['actionnolimitprovided'],
				);
			}

			header('Location: ' . $modulelink);
			exit;
		break;

		case 'delete':

			if($id)
			{
				$result = Capsule::table('mod_limit_purchase')->where('id', $id)->get();
				$limit_details = $result[0]->id;
				
				if($limit_details)
				{
					Capsule::table('mod_limit_purchase')->where('id', $id)->delete();

					$_SESSION['limit_purchase'] = array(
						'type'		=> 'success',
						'message'	=> $vars['_lang']['actionlimitdeleted'],
					);
				}
				else
				{
					$_SESSION['limit_purchase'] = array(
						'type'		=> 'error',
						'message'	=> $vars['_lang']['actionnolimitid'],
					);
				}
			}
			else
			{
				$_SESSION['limit_purchase'] = array(
					'type'		=> 'error',
					'message'	=> $vars['_lang']['actionnolimitprovided'],
				);
			}

			header('Location: ' . $modulelink);
			exit;
		break;

		case 'manage':

			if($id)
			{
				$result = Capsule::table('mod_limit_purchase')->where('id', $id)->get();
				$limit_details = $result[0]->id;

				if($limit_details)
				{
					$result = Capsule::table('mod_limit_purchase')->where('id', $id)->get();
					$manage_details = $result[0];
				}
				else
				{
					$_SESSION['limit_purchase'] = array(
						'type'		=> 'error',
						'message'	=> $vars['_lang']['actionnolimitid'],
					);
				}
			}
			else
			{
				$_SESSION['limit_purchase'] = array(
					'type'		=> 'error',
					'message'	=> $vars['_lang']['actionnolimitprovided'],
				);
			}

			if(isset($_SESSION['limit_purchase']))
			{
				header('Location: ' . $modulelink);
				exit;
			}
		break;
	}

	$result = Capsule::table('mod_limit_purchase')->get();
	
	foreach($result as $row) {
		if($manage_details->product_id != $row->product_id)
		{
			$result2 = Capsule::table('tblproducts')->where('id', $row->product_id)->get();
			$product = $result2[0];

			$ids[] = $row->product_id;
			$limits[] = array_merge((array)$row, array('product_details' => $product));
		}
	}

	if(isset($_SESSION['limit_purchase']))
	{
?>
		<div class="<?php echo $_SESSION['limit_purchase']['type']; ?>box">
			<strong><span class="title"><?php echo $vars['_lang']['info']; ?></span></strong><br />
			<?php echo $_SESSION['limit_purchase']['message']; ?>
		</div>
<?php
		unset($_SESSION['limit_purchase']);
	}
	
	$products = Capsule::table('tblproducts')->whereNotIn('id', $ids)->get();
?>
	<h2><?php echo (sizeof($manage_details) ? $vars['_lang']['editlimit'] : $vars['_lang']['addlimit']); ?></h2>
	<form action="<?php echo $modulelink; ?>&amp;action=<?php echo (sizeof($manage_details) ? 'edit&amp;id=' . $manage_details->id : 'add'); ?>" method="post">

	<table width="100%" cellspacing="2" cellpadding="3" border="0" class="form">
	<tbody>
	<tr>
		<td width="15%" class="fieldlabel"><?php echo $vars['_lang']['product']; ?></td>
		<td class="fieldarea">
			<select name="product_id" class="form-control select-inline">
				<?php if(!sizeof($manage_details)) { ?>
				<option selected="selected" value="0"><?php echo $vars['_lang']['selectproduct']; ?></option>
				<?php } ?>
				<?php foreach($products as $product_details) { ?>
				<option <?php if($manage_details->product_id == $product_details->id) { ?> selected="selected"<?php } ?> value="<?php echo $product_details->id; ?>"><?php echo $product_details->name; ?></option>
				<?php } ?>
			</select>
		</td>
	</tr>
	<tr>
		<td class="fieldlabel"><?php echo $vars['_lang']['limit']; ?></td>
		<td class="fieldarea"><input type="text" value="<?php echo $manage_details->limit; ?>" size="5" name="limit" /> <?php echo $vars['_lang']['limitdesc']; ?></td>
	</tr>
	<tr>
		<td class="fieldlabel"><?php echo $vars['_lang']['errormessage']; ?></td>
		<td class="fieldarea"><input type="text" value="<?php echo $manage_details->error; ?>" size="65" name="error" /><br /><?php echo $vars['_lang']['errormessagedesc']; ?></td>
	</tr>
	<tr>
		<td class="fieldlabel"><?php echo $vars['_lang']['active']; ?></td>
		<td class="fieldarea">
			<input type="radio" <?php if($manage_details->active) { ?>checked="checked" <?php } ?>value="1" name="active" /> <?php echo $vars['_lang']['yes']; ?>
			<input type="radio" <?php if(!$manage_details->active) { ?>checked="checked" <?php } ?>value="0" name="active" /> <?php echo $vars['_lang']['no']; ?>
		</td>
	</tr>
	</tbody>
	</table>

	<p align="center">
		<input type="submit" class="btn btn-primary" value="<?php echo (sizeof($manage_details) ? $vars['_lang']['save'] : $vars['_lang']['createlimitation']); ?>" />
		<?php if(sizeof($manage_details)) { ?>
			<a href="<?php echo $modulelink; ?>" class="btn btn-default"><?php echo $vars['_lang']['cancel']; ?></a>
		<?php } ?>
	</p>
	</form>

	<?php if(!sizeof($manage_details)) { ?>

	<div class="tablebg">

		<table width="100%" cellspacing="1" cellpadding="3" border="0" class="datatable">
		<tbody>
		<tr>
			<th><?php echo $vars['_lang']['product']; ?></th>
			<th><?php echo $vars['_lang']['limit']; ?></th>
			<th><?php echo $vars['_lang']['errormessage']; ?></th>
			<th width="20"></th>
			<th width="20"></th>
			<th width="20"></th>
		</tr>
		<?php foreach($limits as $limit_details) { ?>
		<tr>
			<td><?php echo $limit_details['product_details']->name; ?></td>
			<td style="text-align: center;"><?php echo $limit_details['limit']; ?></td>
			<td><?php echo str_replace('{PNAME}', $limit_details['product_details']->name, $limit_details['error']); ?></td>
			<td><a href="<?php echo $modulelink; ?>&amp;action=<?php echo ($row->active ? 'disable' : 'enable'); ?>&amp;id=<?php echo $limit_details['id']; ?>"><img src="images/icons/<?php echo ($limit_details['active'] ? 'tick.png' : 'disabled.png'); ?>" /></a></td>
			<td><a href="<?php echo $modulelink; ?>&amp;action=manage&amp;id=<?php echo $limit_details['id']; ?>"><img border="0" src="images/edit.gif" /></a></td>
			<td><a href="<?php echo $modulelink; ?>&amp;action=delete&amp;id=<?php echo $limit_details['id']; ?>"><img width="16" height="16" border="0" alt="Delete" src="images/delete.gif" /></a></td>
		</tr>
		<?php } ?>
		</tbody>
		</table>
	</div>

	<?php } ?>
<?php
}
?>
