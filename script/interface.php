<?php

if (! defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
} // Disables token renewal
require '../config.php';

$get = GETPOST('get', 'alpha');

switch ($get) {
	case 'search':
		__out(_search(GETPOST('key')), 'json');
		break;
	default:
		break;
}

function _search(string $keyword)
{
	global $user, $hookmanager;
	$hookmanager->initHooks(array('relatedAjax'));

//	$synonyms = [
//		'action' => 'actioncomm',
//		'entrepot' => 'stock',
//		'invoice' => 'facture',
//		'order' => 'commande',
//		'shipping' => 'expedition',
//		'adherent' => 'member',
//		'company' => 'societe',
//	];
	$supportedElements = [
		'facture',
		'commande',
		'shipping',
		'propal',
		'project',
		'task',
		'societe',
		'contact',
		'actioncomm',
		'product',
		'facture_fournisseur',
		'commande_fournisseur',
		'fichinter',
		'contrat',
		'ticket',
	];

	/**
	 * Ideally, an object type should require no specific configuration because the table name, the object name,
	 * the reference field, the ID field etc. should all use the same pattern and be derived from the element name,
	 * but this is not always the case.
	 *
	 * This array allow us to override the default name for a specific element.
	 * Possible keys are:
	 * - 'table_element': if you need to override the table name; no prefix.
	 * - 'join_to_soc': if set, there will be a join to llx_societe to show the third party in the dropdown
	 *                  (true by default)
	 * - 'ref_field': by default, 'ref', but sometimes can be named differently
	 * - 'ref_field2': if specified, that field will be concatenated to the ref in the json response
	 * - 'multicompany_element': the element name passed to getEntity() for multi-entity elements
	 * - 'check_read_permission': true by default; if false, won't check any permissions before searching
	 * - 'rights_module': if specified, will be passed as the 1st argument to $user->hasRight(); default: element name
	 * - 'rights_permlevel1': if speciifed, will be passed as the 2nd argument; default: 'lire'
	 * - 'rights_permlevel2': if specified, 3rd argument
	 */
	$elementConfiguration = [
		'facture' => [],
		'commande' => [],
		'shipping' => [
			'multicompany_element' => '',
		],
		'propal' => [],
		'project' => [],
		'project_task' => [
			'multicompany_element' => '',
			'join_to_soc' => false,
		],
		'societe' => [
			'ref_field' => 'nom',
			'join_to_soc' => false,
		],
		'contact' => [
			'multicompany_element' => 'socpeople',
			'ref_field' => 'lastname',
			'rights_module' => 'societe',
			'rights_permlevel1' => 'contact',
			'rights_permlevel2' => 'lire',
		],
		'actioncomm' => [
			'id_field' => 'id',
			'ref_field' => 'id',
			'check_read_permission' => false,
		],
		'product' => [
			'join_to_soc' => false,
		],
		'facture_fournisseur' => [
			'multicompany_element' => 'facture_fourn',
		],
		'commande_fournisseur' => [],
		'fichinter' => [
			'multicompany_element' => '',
			'rights_module' => 'ficheinter',
		],
		'contrat' => [],
		'ticket' => [
			'rights_permlevel1' => 'read'
		],
	];

	if (isModEnabled("assetatm")) {
		// todo: implémenter le hook dans le module concerné
		$elementConfiguration['assetatm'] = [
			'table' => 'assetatm',
			'ref_field' => 'serial_number',
			'id_field' => 'rowid',
			'join_to_soc' => false
		];
	}

	if (isModEnabled('chiffrage')) {
		// todo: implémenter le hook dans le module concerné
		$elementConfiguration['chiffrage'] = [
			'table' => 'chiffrage_chiffrage',
			'multicompany_element' => 'chiffrage_chiffrage',
			'rights_permlevel1' => 'chiffrage',
			'rights_permlevel2' => 'read',
		];
	}

	$parameters = ['supportedType' => &$elementConfiguration];
	$hookmanager->executeHooks('relatedAddSupportedObjectTypes', $parameters);

	$searchResults = [];
	foreach ($elementConfiguration as $element => $typeSpecificData) {
		$elementProperties = getElementProperties($element);
		if (!isModEnabled($typeSpecificData['module'] ?? $elementProperties['module'] ?? $element)) {
			continue;
		}
		if ($typeSpecificData['check_read_permission'] ?? true) {
			// by default, we check if the user has read permission on this element.
			$rightsModule = $typeSpecificData['rights_module'] ?? $elementProperties['element'] ?? $element;
			$rightsPermLevel1 = $typeSpecificData['rights_permlevel1'] ?? 'lire';
			$rightsPermLevel2 = $typeSpecificData['rights_permlevel2'] ?? '';
			if (! $user->hasRight($rightsModule, $rightsPermLevel1, $rightsPermLevel2)) {
				// user not allowed to view this element type => we don't add it to the ajax response
				continue;
			}
		}
		$searchResults[$element] = _search_type($element, $typeSpecificData, $keyword);
	}

	return $searchResults;
}

/**
 * @param string $table
 * @return bool
 */
function _checkTableExist(string $table): bool
{
	global $db;
	$res = $db->query('SHOW TABLES LIKE \''.$db->escape($table).'\' ');
	if (! $res) {
		return false;
	}

	if ($db->num_rows($res) > 0) {
		return true;
	} else {
		return false;
	}
}

function _search_type(string $type, array $typeSpecificData, string $keyword)
{
	global $db;

	// getElementProperties() is precisely here to help us handle naming exceptions for core elements
	// (and there is a hook `getElementProperties` too that modules can implement if their objects
	// don't use the standard naming pattern).
	$elementProperties = getElementProperties($type);
	$table = $typeSpecificData['table_element'] ?? $elementProperties['table_element'] ?? $type;
	$id_field = $typeSpecificData['id_field'] ?? 'rowid';
	$ref_field = $typeSpecificData['ref_field'] ?? 'ref';
	$ref_field2 = $typeSpecificData['ref_field2'] ?? '';
	$join_to_soc = $typeSpecificData['join_to_soc'] ?? true;
	$multicompanyElement = $typeSpecificData['multicompany_element'] ?? '';

	// From Dolibarr V19 tables are created at Dolibarr installation but after module activation
	// so we need to check if table exist
	if (! _checkTableExist($db->prefix().$table)) {
		return [];
	}

	$Tab = array();

	$sql = "SELECT t.".$id_field." as rowid, CONCAT(t.".$ref_field." ".(empty($ref_field2) ? '' : ",' ',t.".$ref_field2)." ) as ref ";

	if ($join_to_soc) {
		if ($type == 'task') {
			$sql .= ",CONCAT(p.title,', ',s.nom) as client";
		} else {
			if ($type == 'order' || $type == 'commande') {
				$sql .= ",CONCAT(s.nom , ', Date : ' , DATE_FORMAT(t.date_commande,'%m-%d-%Y')) as client";
			} else {
				$sql .= ",s.nom as client";
			}
		}
	}

	$sql .= " FROM ".$db->prefix().$table." as t ";

	if ($join_to_soc) {
		if ($type == 'task') {
			$sql .= " LEFT JOIN ".$db->prefix()."projet p ON (p.rowid = t.fk_projet) ";
			$sql .= " LEFT JOIN ".$db->prefix()."societe s ON (s.rowid = p.fk_soc) ";
		} else {
			$sql .= " LEFT JOIN ".$db->prefix()."societe s ON (s.rowid = t.fk_soc) ";
		}
	}
	$sql .= " WHERE 1 ";

	if (! empty($multicompanyElement)) {
		$sql .= '  AND t.entity IN ('.getEntity($multicompanyElement).')  ';
	}

	if ($db->type == 'pgsql' && ($ref_field == 'id' || $ref_field == 'rowid')) {
		$sql .= " AND CAST(t.".$ref_field." AS TEXT) LIKE '".$keyword."%' ";
	} else {
		$sql .= " AND t.".$ref_field." LIKE '".$keyword."%' ";
	}

	if (! empty($ref_field2) && $db->type == 'pgsql' && ($ref_field2 == 'id' || $ref_field2 == 'rowid')) {
		$sql .= " OR CAST(t.".$ref_field2." AS TEXT) LIKE '".$keyword."%' ";
	} elseif (! empty($ref_field2)) {
		$sql .= " OR t.".$ref_field2." LIKE '".$keyword."%' ";
	}

	$sql .= " LIMIT 20 ";
	$res = $db->query($sql);

	if ($res === false) {
		return [];
	}

	$nb_results = $db->num_rows($res);

	if ($nb_results == 0) {
		return array();
	} else {
		while ($obj = $db->fetch_object($res)) {
			$r = $obj->ref;
			if (! empty($obj->client)) {
				$r .= ', '.$obj->client;
			}

			$Tab[$obj->rowid] = $r;
		}

		return $Tab;
	}
}
