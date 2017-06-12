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
	 * Constructor
	 */
	public function __construct()
	{
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
		global $langs, $db, $user, $conf, $related_link_added;
		 	$error = 0; // Error counter
		 	//var_dump($objet);
		 	define('INC_FROM_DOLIBARR', true);
		 	dol_include_once('/related/config.php');

		 	$PDOdb = new TPDOdb;

		 	$langs->load('related@related');

		 	if(GETPOST('action') == 'add_related_link' && !$related_link_added) {

				$type = GETPOST('type_related_object');
                //var_dump($type);exit;
				if($type == 'projet') $type = 'project';
				else if($type == 'invoice') $type = 'facture';
				else if($type == 'company') $type = 'societe';
                else if($type=='facture_fournisseur') $type= 'invoice_supplier';
                else if($type=='commande_fournisseur') $type='order_supplier';

                $object->db->begin(); //escape bad recurssive inclusion 
                //TODO find a way to report this to user
                
                $res = $object->add_object_linked( $type , GETPOST('id_related_object') );
				$object->fetchObjectLinked();
               
             	$object->db->commit();
               	
                if(empty($res)) {
                	setEventMessage($langs->trans('RelationCantBeAdded' ),'errors');
                }
                else{
                    $related_link_added=true;
                    global $langs,$conf;

                    dol_include_once ('/core/class/interfaces.class.php');
                    $interface=new Interfaces($db);

                    $object->id_related_object = GETPOST('id_related_object');
                    $object->type_related_object = $type;

                    $result=$interface->run_triggers('RELATED_ADD_LINK',$object,$user,$langs,$conf);

                    if ($result < 0)
                    {
                        if (!empty($this->errors))
                        {
                            $this->errors=array_merge($this->errors,$interface->errors);
                        }
                        else
                        {
                            $this->errors=$interface->errors;
                        }
                    }

                    setEventMessage($langs->trans('RelationAdded'));

                }
		 	}
			elseif (GETPOST('action') == 'delete_related_link') {
				$idLink = GETPOST('id_link');

				if($idLink){

					$PDOdb->Execute("DELETE FROM ".MAIN_DB_PREFIX."element_element WHERE rowid = ".$idLink);
					$object->fetchObjectLinked();
				}
			}
			else {
			    //var_dump($object);
				if (empty($object->linkedObjects)) $object->fetchObjectLinked();
			}
		//var_dump($object->linkedObjectsIds);
		 	?>
		 	<div class="blockrelated_content" style="<?php echo $moreStyle ?>">
		 		<form name="formLinkObj" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
		 			<input type="hidden" name="action" value="add_related_link"  />
		 			<input type="hidden" name="id" value="<?php echo GETPOST('id'); ?>"  />
		 			<input type="hidden" name="facid" value="<?php echo GETPOST('facid'); ?>"  />
		 			<br>
					<div class="titre"><?php echo $langs->trans('ElementToLink'); ?></div>

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

							foreach($object->linkedObjectsIds as $objecttype => &$TSubIdObject) {
								//var_dump($objecttype);
								if(isset( $object->linkedObjects[$objecttype] ) && $objecttype!='societe' && $objecttype!='product' && $object->element!='project') continue; // on affiche ici que les objects non géré en natif

								foreach($TSubIdObject as $id_object) {
									$date_create = 0;
									$classname = ucfirst($objecttype);
									$statut = 'N/A';

									if($objecttype=='task') {
										dol_include_once('/projet/class/task.class.php');
									}
									else if($objecttype=='event' || $objecttype=='action') {
										dol_include_once('/comm/action/class/actioncomm.class.php');
										$classname='ActionComm';
									}else if ($objecttype=='project') {
										dol_include_once('/projet/class/project.class.php');
									}
									else if ($objecttype=='ordre_fabrication') {
										dol_include_once('/of/class/ordre_fabrication_asset.class.php');
										$classname='TAssetOf';
										$abricot = true;
									}

									if(!class_exists($classname)) {

										$link='CantInstanciateClass '.$classname;


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
									else{
										$subobject =new $classname($db);
										$subobject->fetch($id_object);

										if(method_exists($subobject, 'getNomUrl')) {
											$link = $subobject->getNomUrl(1);
										}
										else{
											$link = $id_object.'/'.$classname;
										}

										$class = ($class == 'impair') ? 'pair' : 'impair';

										if(!empty($subobject->date_creation)) $date_create = $subobject->date_creation;
										if(empty($date_create) && !empty($subobject->date_create)) $date_create = $subobject->date_create;
										if(empty($date_create) && !empty($subobject->date_c)) $date_create = $subobject->date_c;

										if(method_exists($subobject, 'getLibStatut')) $statut = $subobject->getLibStatut(3);
									}

									$Tids = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX."element_element",array('fk_source'=>$id_object,'fk_target'=>$object->id,'sourcetype'=>$objecttype,'targettype'=>$object->element));

									?>
									<tr class="<?php echo $class ?>">
										<td><?php echo $link; ?></td>
										<td align="center"><?php echo !empty($date_create) ? dol_print_date($date_create,'day') : ''; ?></td>
										<td align="center"><?php echo $statut; ?></td>
										<td align="center"><a href="?id=<?php echo $object->id; ?>&action=delete_related_link&id_link=<?php echo $Tids[0]; ?>"><?php print img_picto($langs->trans("Delete"), 'delete.png') ?></a></td>
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

		 				$( "#add_related_object" ).autocomplete( "instance" )._renderItem = function( ul, item ) {

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
			 					$('div.tabsAction').after(blockrelated.clone());
			 					blockrelated.remove();
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
		if( $parameters['currentcontext']='actioncard' || $parameters['currentcontext']='contactcard' || $parameters['currentcontext']=='globalcard') {

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