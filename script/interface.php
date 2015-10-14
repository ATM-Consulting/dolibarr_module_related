<?php

	require '../config.php';
	
	$get = GETPOST('get');
	
	switch ($get) {
		case 'search':
			
			__out(_search(GETPOST('key')),'json' );
			
			break;
		
		default:
			
			break;
	}

function _search($keyword) {
	
	$Tab = array();
	
	$TType=array('invoice','commande','propal','projet','task','company','contact','event', 'product');
	
	foreach($TType as $type) {
		
		$Tab[$type] = _search_type($type, $keyword);
		
	}
	
	return $Tab;
	
}
	
function _search_type($type, $keyword) {
	global $db, $langs;
	
	$table = MAIN_DB_PREFIX.$type;
	$objname = ucfirst($type);
	$id_field = 'rowid';
	$ref_field = 'ref';
	$ref_field2 = '';
	
	if($type == 'company') {
		$table = MAIN_DB_PREFIX.'societe';
		$objname = 'Societe';
		$ref_field='nom';
	}
	elseif($type == 'projet') {
		$table = MAIN_DB_PREFIX.'projet';
		$objname = 'Project';
	}
	elseif($type == 'task') {
		$table = MAIN_DB_PREFIX.'projet_task';
		
	}
	elseif($type == 'event') {
		$table = MAIN_DB_PREFIX.'actioncomm';
		$objname = 'ActionComm';
		$id_field = 'id';
		$ref_field = 'id';
		$ref_field2 = 'label'; 
	}
	elseif($type == 'order') {
		$table = MAIN_DB_PREFIX.'commande';
		$objname = 'Commande';
	}
	elseif($type == 'invoice') {
		$table = MAIN_DB_PREFIX.'facture';
		$objname = 'Facture';
		$ref_field = 'facnumber';
	}
	elseif($type == 'contact') {
		$table = MAIN_DB_PREFIX.'socpeople';
		$ref_field = 'lastname';
		
	}
	
	
	$Tab = array();
	
	$sql = "SELECT ".$id_field." as rowid, CONCAT(".$ref_field." ".( empty($ref_field2) ? '' : ",' ',".$ref_field2 )." ) as ref FROM ".$table." WHERE ".$ref_field." LIKE '".$keyword."%' ";
	if(!empty($ref_field2)) {
		$sql.=" OR ".$ref_field2." LIKE '".$keyword."%' ";
	}
	
	$sql.=" LIMIT 20 ";
	
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
		
			$Tab[$obj->rowid] = $obj->ref;
			
		}
		
		return $Tab;	
	}
	
	
	
}
