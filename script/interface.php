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
	global $db, $conf, $langs;

	$Tab = array();

	$TType=array('invoice','commande','propal','projet','task','company','contact','event', 'product', 'facture_fournisseur', 'commande_fournisseur','fichinter');

	if(!empty($conf->of->enabled)) {
		$TType[] = 'ordre_fabrication';
	}

	//$TType=array('facture_fournisseur', 'commande_fournisseur');
	foreach($TType as $type) {
		$Tab[$type] = _search_type($type, $keyword);
	}

	return $Tab;

}

function _search_type($type, $keyword) {
	global $db, $conf, $langs;

	$table = MAIN_DB_PREFIX.$type;
	$objname = ucfirst($type);
	$id_field = 'rowid';
	$ref_field = 'ref';
	$ref_field2 = '';
	$join_to_soc = false;

	if($type == 'company') {
		$table = MAIN_DB_PREFIX.'societe';
		$objname = 'Societe';
		$ref_field='nom';
	}
	elseif($type == 'projet') {
		$table = MAIN_DB_PREFIX.'projet';
		$objname = 'Project';
		$join_to_soc = true;
	}
	elseif($type == 'task' || $type == 'project_task') {
		$table = MAIN_DB_PREFIX.'projet_task';
		$objname = 'Task';
		$id_field = 'rowid';
		$ref_field = 'ref';
		$join_to_soc = true;
	}
	elseif($type == 'event' || $type=='action') {
		$table = MAIN_DB_PREFIX.'actioncomm';
		$objname = 'ActionComm';
		$id_field = 'id';
		$ref_field = 'id';
		$ref_field2 = 'label';
		$join_to_soc = true;
	}
	elseif($type == 'order' || $type == 'commande') {
		$table = MAIN_DB_PREFIX.'commande';
		$objname = 'Commande';
		$join_to_soc = true;
	}
	elseif($type == 'invoice') {
		$table = MAIN_DB_PREFIX.'facture';
		$objname = 'Facture';
		$ref_field = 'facnumber';
		$join_to_soc = true;
	}
	elseif($type == 'contact') {
        $table = MAIN_DB_PREFIX.'socpeople';
        $ref_field = 'lastname';
		$join_to_soc = true;
    }
    elseif($type == 'propal') {
        $table = MAIN_DB_PREFIX.'propal';
        $ref_field = 'ref';
        $join_to_soc = true;
    }
    elseif($type == 'product') {
        $table = MAIN_DB_PREFIX.'product';
        $ref_field = 'ref';

    }
    elseif ($type=='facture_fournisseur') {
        $table=MAIN_DB_PREFIX.'facture_fourn';
        //$id_field='rowid';
        $objname='FactureFourn';
        $ref_field='ref';
		$join_to_soc = true;
    }
    elseif ($type=='commande_fournisseur'){
        $table=MAIN_DB_PREFIX.'commande_fournisseur';
        $objname='CommandeFournisseur';
        $ref_field='ref';
		$join_to_soc = true;
    }
    elseif ($type=='fichinter'){
    	$table=MAIN_DB_PREFIX.'fichinter';
    	$objname='Fichinter';
    	$ref_field='ref';
    	$join_to_soc = true;
    }
	else if(!empty($conf->of->enabled) && $type == 'ordre_fabrication') {
		$table=MAIN_DB_PREFIX.'assetOf';
        $objname='TAssetOf';
        $ref_field='numero';

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
			$sql.=" LEFT JOIN ".MAIN_DB_PREFIX."projet p ON (p.rowid = t.fk_projet) ";
			$sql.=" LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (s.rowid = p.fk_soc) ";
		}
		else {
			$sql.=" LEFT JOIN ".MAIN_DB_PREFIX."societe s ON (s.rowid = t.fk_soc) ";
		}
	}

	if ($db->type == 'pgsql' && ($ref_field=='id' || $ref_field=='rowid')) {
		$sql.=" WHERE CAST(t.".$ref_field." AS TEXT) LIKE '".$keyword."%' ";
	} else {
		$sql.=" WHERE t.".$ref_field." LIKE '".$keyword."%' ";
	}

	if (!empty($ref_field2) && $db->type == 'pgsql' && ($ref_field2=='id' || $ref_field2=='rowid')) {
		$sql.=" OR CAST(t.".$ref_field2." AS TEXT) LIKE '".$keyword."%' ";
	} elseif (!empty($ref_field2)) {
		$sql.=" OR t.".$ref_field2." LIKE '".$keyword."%' ";
	}


	$sql.=" LIMIT 20 ";
	//var_dump($sql);
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
