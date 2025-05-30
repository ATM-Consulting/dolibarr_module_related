<?php
	if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1); // Disables token renewal
	require '../config.php';

	$get = GETPOST('get','alpha');

	switch ($get) {
		case 'search':

			__out(_search(GETPOST('key')),'json' );

			break;

		default:

			break;
	}

function _search($keyword) {
	global $db, $conf, $langs;

	$Tab = array();

	$TType=array(
		'invoice',
		'commande',
		'shipping',
		'propal',
		'project',
		'task',
		'company',
		'contact',
		'event',
		'product',
		'facture_fournisseur',
		'commande_fournisseur',
		'fichinter',
		'contrat',
		'ticket',
	);

    if(isModEnabled("assetatm")) {
        $TType[] = 'assetatm';
    }

	if (isModEnabled('chiffrage')) {
		$TType[] = 'chiffrage';
	}

	//$TType=array('facture_fournisseur', 'commande_fournisseur');
	foreach($TType as $type) {
		$Tab[$type] = _search_type($type, $keyword);
	}

	return $Tab;

}



/**
 * @param string $table
 * @return bool
 */
function _checkTableExist(string $table): bool {
	global $db;
	$res = $db->query('SHOW TABLES LIKE \''.$db->escape($table).'\' ');
	if (!$res) {
		return false;
	}

	if ($db->num_rows($res)>0) {
		return true;
	}else{
		return false;
	}
}

function _search_type($type, $keyword) {
	global $db, $conf, $langs;

	$table = $db->prefix().$type;
	$objname = ucfirst($type);
	$id_field = 'rowid';
	$ref_field = 'ref';
	$ref_field2 = '';
	$join_to_soc = false;
	$element ='';

	if ($type == 'company') {
		$table = $db->prefix() . 'societe';
		$objname = 'Societe';
		$element = 'societe';
		$ref_field = 'nom';
	} elseif ($type == 'project') {
		$table = $db->prefix() . 'projet';
		$objname = 'Project';
		$element = 'project';
		$join_to_soc = true;
	} elseif ($type == 'task' || $type == 'project_task') {
		$table = $db->prefix() . 'projet_task';
		$objname = 'Task';
		$id_field = 'rowid';
		$ref_field = 'ref';
		$join_to_soc = true;
	} elseif ($type == 'event' || $type == 'action') {
		$table = $db->prefix() . 'actioncomm';
		$objname = 'ActionComm';
		$id_field = 'id';
		$ref_field = 'id';
		$ref_field2 = 'label';
		$element = 'actioncomm';
		$join_to_soc = true;
	} elseif ($type == 'order' || $type == 'commande') {
		$table = $db->prefix() . 'commande';
		$objname = 'Commande';
		$element = 'commande';
		$join_to_soc = true;
	} elseif ($type == 'shipping') {
		$table = $db->prefix() . 'expedition';
		$objname = 'Expedition';
		$join_to_soc = true;
	} elseif ($type == 'invoice') {
		$table = $db->prefix() . 'facture';
		$objname = 'Facture';
		$ref_field = 'ref';
		$element = 'facture';
		$join_to_soc = true;
	} elseif ($type == 'contact') {
		$table = $db->prefix() . 'socpeople';
		$ref_field = 'lastname';
		$element = 'socpeople';
		$join_to_soc = true;
	} elseif ($type == 'propal') {
		$table = $db->prefix() . 'propal';
		$ref_field = 'ref';
		$element = 'propal';
		$join_to_soc = true;
	} elseif ($type == 'product') {
		$table = $db->prefix() . 'product';
		$ref_field = 'ref';
		$element = 'product';

	} elseif ($type == 'facture_fournisseur') {
		$table = $db->prefix() . 'facture_fourn';
		//$id_field='rowid';
		$objname = 'FactureFourn';
		$ref_field = 'ref';
		$element = 'facture_fourn';
		$join_to_soc = true;
	} elseif ($type == 'commande_fournisseur') {
		$table = $db->prefix() . 'commande_fournisseur';
		$objname = 'CommandeFournisseur';
		$ref_field = 'ref';
		$element = 'commande_fournisseur';
		$join_to_soc = true;
	} elseif ($type == 'contrat') {
		$table = $db->prefix() . 'contrat';
		$ref_field = 'ref';
		$element = 'contrat';
		$join_to_soc = true;
	} elseif ($type == 'fichinter') {
		$table = $db->prefix() . 'fichinter';
		$objname = 'Fichinter';
		$ref_field = 'ref';
		$join_to_soc = true;
	} else if (isModEnabled("assetatm") && $type == 'assetatm') {
		$table = $db->prefix() . 'assetatm';
		$objname = 'TAsset';
		$ref_field = 'serial_number';
		$id_field = 'rowid';
	} elseif (isModEnabled('chiffrage') && $type == 'chiffrage') {
		$table = $db->prefix() . 'chiffrage_chiffrage';
		$objname = 'Chiffrage';
		$element = 'chiffrage_chiffrage';
		$join_to_soc = true;
	}

	// From Dolibarr V19 tables are created at Dolibarr installation but after module activation
	// so we need to check if table exist
	if(!_checkTableExist($table)){
		return [];
	}

	$Tab = array();


	$sql = "SELECT t.".$id_field." as rowid, CONCAT(t.".$ref_field." ".( empty($ref_field2) ? '' : ",' ',t.".$ref_field2 )." ) as ref ";

	if($join_to_soc) {
		if($type == 'task') {
			$sql.=",CONCAT(p.title,', ',s.nom) as client";
		}
		else if($type == 'order' || $type == 'commande') {
			$sql.=",CONCAT(s.nom , ', Date : ' , DATE_FORMAT(t.date_commande,'%m-%d-%Y')) as client";
		}
		else {
			$sql.=",s.nom as client";
		}
	}

	$sql.=" FROM ".$table." as t ";

	if($join_to_soc) {
		if($type == 'task') {
			$sql.=" LEFT JOIN ".$db->prefix()."projet p ON (p.rowid = t.fk_projet) ";
			$sql.=" LEFT JOIN ".$db->prefix()."societe s ON (s.rowid = p.fk_soc) ";
		}
		else {
			$sql.=" LEFT JOIN ".$db->prefix()."societe s ON (s.rowid = t.fk_soc) ";
		}
	}
	$sql.=" WHERE 1 ";

	if(!empty($element))
	{
		$sql.= '  AND t.entity IN (' . getEntity($element) . ')  ';
	}

	if ($db->type == 'pgsql' && ($ref_field=='id' || $ref_field=='rowid')) {
		$sql.=" AND CAST(t.".$ref_field." AS TEXT) LIKE '".$keyword."%' ";
	} else {
		$sql.=" AND t.".$ref_field." LIKE '".$keyword."%' ";
	}

	if (!empty($ref_field2) && $db->type == 'pgsql' && ($ref_field2=='id' || $ref_field2=='rowid')) {
		$sql.=" OR CAST(t.".$ref_field2." AS TEXT) LIKE '".$keyword."%' ";
	} elseif (!empty($ref_field2)) {
		$sql.=" OR t.".$ref_field2." LIKE '".$keyword."%' ";
	}

	$sql.=" LIMIT 20 ";
//	var_dump($sql);
    //$sql="SELECT ff.ref FROM  ".MAIN_DB_PREFIX."facture_fourn ff WHERE ";
	$res = $db->query($sql);

	if($res === false) {
		pre($db,true);
	}

	$nb_results = $db->num_rows($res);


	if($nb_results == 0) {
		return array();
	}
	else{
		while($obj = $db->fetch_object($res)) {

			$r = $obj->ref;
			if(!empty($obj->client))$r.=', '.$obj->client;

			$Tab[$obj->rowid] = $r;

		}
		return $Tab;
	}



}
