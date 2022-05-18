<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_related.class.php
 * \ingroup related
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class ActionsRelated
 */

class ActionsRelated
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * mapping type => object->element
	 */
	const TYPEMAP = array(
		'invoice' => 'facture',
		'company' => 'societe',
		'projet' => 'project',
		'facture_fournisseur' => 'invoice_supplier',
		'commande_fournisseur' => 'order_supplier',
	);

	const CLASSPATHMAP = array(
		'task' => '/projet/class/task.class.php',
		'event' => '/comm/action/class/actioncomm.class.php',
		'action' => '/comm/action/class/actioncomm.class.php',
		'project' => '/projet/class/project.class.php',
		'projet' => '/projet/class/project.class.php',
		'ordre_fabrication' => '/ordre_fabrication_asset.class.php',
		'asset' => '/asset/class/asset.class.php',
		'assetatm' => '/assetatm/class/asset.class.php',
		'contratabonnement' => '/contrat/class/contrat.class.php',
		'ticket' => '/ticket/class/ticket.class.php',
		'fichinter' => '/fichinter/class/fichinter.class.php',
		'order_supplier' => '/fourn/class/fournisseur.commande.class.php',
		'shipping' => '/expedition/class/expedition.class.php',
		'invoice_supplier' => '/fourn/class/fournisseur.facture.class.php'
	);

	// type => class name; pas besoin si ucfirst(type) == classname
	const CLASSNAMEMAP = array(
		'event' => 'ActionComm',
		'action' => 'ActionComm',
		'ordre_fabrication' => 'TAssetOf',
		'asset' => 'TAsset',
		'assetatm' => 'TAsset',
		'contratabonnement' => 'Contrat',
		'projet' => 'Project',
		'fichinter' => 'Fichinter',
		'order_supplier' => 'CommandeFournisseur',
		'shipping' => 'Expedition',
		'invoice_supplier' => 'FactureFournisseur'
	);

	const DATEFIELDMAP = array(
		'event' => 'datep',
		'action' => 'datep',
	);

	const IS_ABRICOT = array(
		'asset',
		'assetatm',
	);

	/**
	 * mapping vrai object->element => nom du module
	 * En effet, fetchObjectLinked part  du principe que le nom du module correspond
	 * toujours au champ 'element' de l’objet (idéalement ça devrait être le cas, mais en
	 * pratique, pas toujours). En fait, fetchObjectLinked incorpore quelques exceptions pour
	 * des modules très fréquemment utilisés (facture…) mais pas pour tous.
	 *
	 * Comme fetchObjectLinked ne conserve pas les objets liés provenant de modules désactivés,
	 * pour éviter qu’il se débarrasse des objets liés à des modules mal nommés, il faut créer
	 * un leurre (un objet module activé correspondant au nom attendu par fetchObjectLinked).
	 *
	 * Il faudrait faire une PR cœur pour que ce mapping soit fourni par le commonobject et
	 * utilisé directement par fetchObjectLinked (plus idéal mais moins réaliste : renommer
	 * les modules ou les éléments pour que tout corresponde).
	 */
	const MODULENAMEMAP = array(
		'event' => 'agenda',
		'project' => 'projet',
		'task' => 'projet',
	);

	public $relatedLinkAdded = false;

	/**
	 * liste des elements pris en charge nativement par Dolibarr
	 * not use const to be able to use hook
	 * @var array $knowedElements
	 */
	public $knownElements = array(
		'project',
		'asset',
		'contratabonnement',
		'projet',
		'fichinter',
		'order_supplier',
		'shipping',
		'invoice_supplier',
		'commande',
		'facture',
		'propal'
	);

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * @param array $parameters
	 * @param CommonObject $object
	 * @param string $action
	 * @param HookManager $hookmanager
	 * @return int
	 */
	function doActions($parameters, &$object, &$action, $hookmanager) {
		if ($action === 'add_related_link' || $action === 'delete_related_link') {
			global $langs, $conf, $user;
			$action_orig = $action; // copy $action onto non-reference variable before resetting it
			$action = '';
			$db = &$object->db;
			if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', true);
			include_once dirname(__DIR__) . '/config.php';
			$langs->load('related@related');

			if ($action_orig === 'add_related_link') {
				$type = GETPOST('type_related_object', 'alphanohtml');
				if (isset($this::TYPEMAP[$type])) $type = $this::TYPEMAP[$type];
				$idRelatedObject = intval(GETPOST('id_related_object', 'int'));
				$object->fetchObjectLinked(
					null,
					'',
					null,
					'',
					'OR',
					1,
					'sourcetype',
					false
				);
				if (is_array($object->linkedObjectsIds[$type]) && in_array($idRelatedObject, $object->linkedObjectsIds[$type])) {
					// link already exists
					$this->errors[] = $langs->trans('RelationAlreadyExists');
				} else {
					$db->begin();
					$res = $object->add_object_linked( $type , $idRelatedObject);
					if ($res <= 0) {
						$db->rollback();
						$this->errors[] = $langs->trans('RelationCantBeAdded');
					} else {
						$this->relatedLinkAdded = true;
						include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
						$triggers = new Interfaces($db);
						$restrigger = $triggers->run_triggers('RELATED_ADD_LINK', $object, $user, $langs, $conf);
						if ($restrigger < 0) {
							$db->rollback();
							$this->errors = array_merge($this->errors, $triggers->errors);
						} else {
							$db->commit();
						}
						setEventMessage($langs->trans('RelationAdded'));
					}
				}

			} elseif ($action_orig === 'delete_related_link') {

				$idLink = GETPOST('id_link', 'int');
				if($idLink){
					$object->deleteObjectLinked(
						'',
						'',
						'',
						'',
						$idLink
					);
				}
			}
			// après l’action, on re-fetch les objets liés (potentiellement ajoutés ou supprimés)
			$object->fetchObjectLinked();

			if (count($this->errors)) {
				return -1;
			}
		}
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */


	function blockRelated($parameters, &$object, &$action, $hookmanager, $moreStyle='') {
		global $langs,
			   $db,
			   $user,
			   $conf,
			   $related_link_added;
		$newToken = function_exists('newToken')?newToken():$_SESSION['newtoken'];
		$error = 0; // Error counter
		if (!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', true);
		include_once dirname(__DIR__) . '/config.php';

		$PDOdb = new TPDOdb;

		$langs->load('related@related');
		// Ce bazar vient de fetchObjectLinked qui s'autocensure pour les types d'objet dont le nom ne
		// correspond pas à un module activé (sauf certains qui ont un traitement spécial).
		$TFakeModule = array();
		foreach ($this::MODULENAMEMAP as $objectname => $realmodulename) {
			if (empty($conf->{$objectname}->enabled)         // le "faux" module n'est pas activé
				&& !empty($conf->{$realmodulename}->enabled) // le vrai module correspondant doit être activé
			) {
				$TFakeModule[] = $objectname;
				$conf->{$objectname} = new stdClass();
				$conf->{$objectname}->enabled = true;
			}
		}
		$object->fetchObjectLinked();
		foreach ($TFakeModule as $objectname) unset($conf->{$objectname});
		?>
		<div class="blockrelated_content" style="<?php echo $moreStyle ?>">
			<form name="formLinkObj" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
				<input type="hidden" name="action" value="add_related_link"  />
				<input type="hidden" name="id" value="<?php echo $object->id ? $object->id : GETPOST('id','int'); ?>"  />
				<input type="hidden" name="socid" value="<?php echo GETPOST('socid','int'); ?>"  />
				<input type="hidden" name="facid" value="<?php echo GETPOST('facid','int'); ?>"  />

				<input type="hidden" name="token" value="<?php echo function_exists('newToken') ? newToken() : $_SESSION['newtoken']; ?>"  />

				<div class="titre"><i class="fa fa-link" style="color:var(--colortexttitlenotab, #25b7d3 );" ></i> <?php echo $langs->trans('ElementToLink'); ?></div>

				<input type="hidden" id="id_related_object" name="id_related_object" value=""  />
				<input type="hidden" id="type_related_object" name="type_related_object" value=""  />


				<table class="noborder allwidth">
					<tr class="liste_titre">
						<td><?php echo $langs->trans("Ref"); ?> <input type="text" id="add_related_object" name="add_related_object" value="" class="flat" /> <input type="submit" id="bt_add_related_object" name="bt_add_related_object" class="button" value="<?php echo $langs->trans('AddRelated') ?>" style="display:none" /></td>
						<td align="center"><?php echo $langs->trans("Date"); ?></td>
						<td align="center"><?php echo $langs->trans("Status"); ?></td>
						<td align="center"><?php echo $langs->trans("Action"); ?></td>
					</tr>
					<?php
						$class = 'pair';

						foreach($object->linkedObjectsIds as $linkedObjectType => &$TSubIdObject) {

							// le but de $showThisLink: n’afficher de lien vers l'élément que si ce n'est pas déjà
							// pris en charge par le standard Dolibarr
							//    @see Form::showLinkedObjectBlock()

							// les élements connus de Dolibarr doivent être écarté si il partage le même tiers
							$showThisLink = !in_array($linkedObjectType, $this->knownElements);

							/** Cas particuliers pour afficher le lien : **/
							// si l'objet lié est un tiers, un contrat/abonnement, un produit ou un projet
							if (in_array($linkedObjectType, array('societe', 'contratabonnement', 'product', 'project', 'action')))
								$showThisLink = true;
							// si on est sur une fiche tiers et que l'objet lié est une facture, propale ou commande
							elseif (in_array($object->element, array('societe', 'projet'))
									&& in_array($linkedObjectType, array('facture', 'propal', 'commande')))
								$showThisLink = true;
							// si on est sur une fiche événement
							elseif (in_array($object->element, array('action')))
								$showThisLink = true;
							// si l'objet lié n'est pas chargé
							elseif (!isset( $object->linkedObjects[$linkedObjectType] ))
								$showThisLink = true;

							// $showThisLink doit être false si l'objet est géré en natif
							if (!$showThisLink) continue;

							foreach($TSubIdObject as $k => $id_object) {
								$date_create = 0;
								$classname = ucfirst($linkedObjectType);
								$statut = 'N/A';
								$date_field = null;
								$abricot = false;

								if (isset($this::CLASSNAMEMAP[$linkedObjectType])) {
									$classname = $this::CLASSNAMEMAP[$linkedObjectType];
								}
								if (isset($this::CLASSPATHMAP[$linkedObjectType])) {
									$classpath = $this::CLASSPATHMAP[$linkedObjectType];
									dol_include_once($classpath);
								}
								if (isset($this::DATEFIELDMAP[$linkedObjectType])) {
									$date_field = $this::DATEFIELDMAP[$linkedObjectType];
								}
								if (in_array($linkedObjectType, $this::IS_ABRICOT)) {
									$abricot = true;
								}
								if(!class_exists($classname)) {
									$link=$langs->trans('CantInstanciateClass', $classname);
									if (isset($object->linkedObjects[$linkedObjectType][$k])) 
									{
										$subobject = $object->linkedObjects[$linkedObjectType][$k];
										$link = $subobject->getNomUrl(1);
										$class = ($class == 'impair') ? 'pair' : 'impair';

                                                                        	if(!empty($date_field) && !empty($subobject->{$date_field})) $date_create = $subobject->{$date_field};
                                                                        	if(empty($date_create) && !empty($subobject->date_creation)) $date_create = $subobject->date_creation;
                                                                        	if(empty($date_create) && !empty($subobject->date_create)) $date_create = $subobject->date_create;
                                                                        	if(empty($date_create) && !empty($subobject->date_c)) $date_create = $subobject->date_c;
                                                                        	if(empty($date_create) && !empty($subobject->datec)) $date_create = $subobject->datec;
										if(method_exists($subobject, 'getLibStatut')) $statut = $subobject->getLibStatut(3);
									}
								}
								else if(!empty($abricot)) {

									if(empty($PDOdb)) $PDOdb = new TPDOdb;

									$subobject =new $classname;
									$subobject->load($PDOdb, $id_object);

									if(method_exists($subobject, 'getNomUrl')) {
										$link = $subobject->getNomUrl(1);
									}
									else{
										$link = $id_object.'/'.$classname;
									}

									$class = ($class == 'impair') ? 'pair' : 'impair';

									$date_create = $subobject->date_cre;
									if(method_exists($subobject, 'getLibStatut')) $statut = $subobject->getLibStatut(3);
								}
								else {
									$subobject =new $classname($db);
									$subobject->fetch($id_object);

									if(method_exists($subobject, 'getNomUrl')) {
										$link = $subobject->getNomUrl(1);
									}
									else{
										$link = $id_object.'/'.$classname;
									}

									$class = ($class == 'impair') ? 'pair' : 'impair';

									if(!empty($date_field) && !empty($subobject->{$date_field})) $date_create = $subobject->{$date_field};
									if(empty($date_create) && !empty($subobject->date_creation)) $date_create = $subobject->date_creation;
									if(empty($date_create) && !empty($subobject->date_create)) $date_create = $subobject->date_create;
									if(empty($date_create) && !empty($subobject->date_c)) $date_create = $subobject->date_c;
									if(empty($date_create) && !empty($subobject->datec)) $date_create = $subobject->datec;

									if(method_exists($subobject, 'getLibStatut')) $statut = $subobject->getLibStatut(3);
								}

								$Tids = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX."element_element",array('fk_source'=>$id_object,'fk_target'=>$object->id,'sourcetype'=>$linkedObjectType,'targettype'=>$object->element));
								if(empty($Tids)) $Tids = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX."element_element",array('fk_source'=>$object->id,'fk_target'=>$id_object,'sourcetype'=>$object->element,'targettype'=>$linkedObjectType));

								?>
								<tr class="oddeven">
									<td><?php echo $link; ?></td>
									<td align="center"><?php echo !empty($date_create) ? dol_print_date($date_create,'day') : ''; ?></td>
									<td align="center"><?php echo $statut; ?></td>
									<td align="center">
									<?php if(!(($object->element === 'shipping' && $subobject->element === 'commande') || ($object->element === 'commande' && $subobject->element === 'shipping'))) { // On affiche la poubelle uniquement s'il ne s'agit pas d'un lien entre commande et expédition ?>
										<a href="?<?php echo ($object->element === 'societe' ? 'socid=' : 'id=').$object->id; ?>&token=<?php echo $newToken; ?>&action=delete_related_link&id_link=<?php echo $Tids[0]; ?>"><?php print img_picto($langs->trans("Delete"), 'delete.png') ?></a>
									<?php } ?>
									</td>
								</tr>
								<?php

							}
						}
					?>
					</table>


			</form>
		</div>
			<script type="text/javascript">

				$(document).ready(function() {

					$('.blockrelated_content').each(function() {
						$(this).closest('div.tabsAction').after($(this));
					});

					$('#add_related_object').autocomplete({
					  source: function( request, response ) {
						$.ajax({
						  url: "<?php echo dol_buildpath('/related/script/interface.php',1) ?>",
						  dataType: "json",
						  data: {
							  key: request.term
							,get:'search'
						  }
						  ,success: function( data ) {
							  var c = [];
							  $.each(data, function (i, cat) {

								var first = true;
								$.each(cat, function(j, label) {

									if(first) {
										c.push({value:i, label:i, object:'title'});
										first = false;
									}

									c.push({ value: j, label:'  '+label, object:i});

								});


							  });

							  response(c);



						  }
						});
					  },
					  minLength: 1,
					  select: function( event, ui ) {

						if(ui.item.object == 'title') return false;
						else {
							$('#id_related_object').val(ui.item.value);
							$('#add_related_object').val(ui.item.label.trim());
							$('#type_related_object').val(ui.item.object);

							$('#bt_add_related_object').css('display','inline');

							return false;
						}

					  },
					  open: function( event, ui ) {
						$( this ).removeClass( "ui-corner-all" ).addClass( "ui-corner-top" );
					  },
					  close: function() {
						$( this ).removeClass( "ui-corner-top" ).addClass( "ui-corner-all" );
					  }
					});

					$( "#add_related_object" ).autocomplete().data("uiAutocomplete")._renderItem = function( ul, item ) {

						  $li = $( "<li />" )
								.attr( "data-value", item.value )
								.append( item.label )
								.appendTo( ul );

						  if(item.object=="title") $li.css("font-weight","bold");

						  return $li;
					};


					var blockrelated = $('div.tabsAction .blockrelated_content');
					if (blockrelated.length == 1)
					{
						if ($('.blockrelated_content').length > 1)
						{
							blockrelated.remove();
						}
						else
						{
							blockrelated.appendTo($('div.tabsAction'));
						}
					}

				});

			</script>

		<?php


		if (! $error)
		{

			return 0; // or return 1 to replace standard code
		}
		else
		{
			$this->errors[] = 'Cant link related';
			return -1;
		}
	}

	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager) {
		if( $parameters['currentcontext']=='actioncard' || $parameters['currentcontext']=='contactcard' || $parameters['currentcontext']=='globalcard') {

			if (!empty($object))return $this->blockRelated($parameters, $object, $action, $hookmanager, "width:50%;clear:both;margin-bottom:20px;");
		}
		return 0;
	}

	function showLinkedObjectBlock($parameters, &$object, &$action, $hookmanager)
	{
		if (in_array('commonobject', explode(':', $parameters['context'])))

		{
			return $this->blockRelated($parameters, $object, $action, $hookmanager);

		}

		return 0;
	}

	function mainCardTabAddMore($parameters, &$object, &$action, $hookmanager) {
		if( in_array('projectcard', explode(':', $parameters['context']))) {

			return $this->blockRelated($parameters, $object, $action, $hookmanager, "width:50%;clear:both;margin-bottom:20px;margin-left:20px;");
		}

	}

}
