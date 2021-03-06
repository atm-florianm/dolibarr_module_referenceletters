<?php
/*
 * Copyright (C) 2016  Florian HENRY <florian.henry@open-concept.pro>
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
 * \file class/actions_referenceletters.class.php
 * \ingroup referenceletters
 * \brief This file is an example hook overload class file
 * Put some comments here
 */

/**
 * Class ActionsReferenceLetters
 */
class ActionsReferenceLetters
{
	/**
	 *
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array ();

	/**
	 *
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 *
	 * @var array Errors
	 */
	public $errors = array ();

	/**
	 * Constructor
	 */
	public function __construct($db) {
		$this->db = $db;
		$this->error = 0;
		$this->errors = array ();
		$this->resprints = null;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param array() $parameters Hook metadatas (context, etc...)
	 * @param CommonObject &$object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param string &$action Current action (if set). Generally create or edit or null
	 * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
	 * @return int < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function afterPDFCreation($parameters, &$object, &$action, $hookmanager) {
		global $langs, $conf, $user;

		$error = 0; // Error counter
		dol_syslog("Hook '" . get_class($this) . "' for action '" . __METHOD__ . "' launched by " . __FILE__);
		if (in_array('referencelettersinstacecard', explode(':', $parameters['context']))) {
			if (! empty($conf->global->REF_LETTER_CREATEEVENT)) {
				$instance_letter = $parameters['instance_letter'];
				dol_syslog("Hook '" . get_class($this) . " id=" . $instance_letter->id);
				// var_dump($instance_letter);
				$langs->load('referenceletters@referenceletters');
				$now = dol_now();

				dol_include_once('/comm/action/class/actioncomm.class.php');
				$actioncomm = new ActionComm($this->db);
				$actioncomm->type_code = 'AC_LTR_DOC';
				if ($conf->global->REF_LETTER_TYPEEVENTNAME == 'normal') {
					$actioncomm->label = $langs->trans('RefLtrNewLetters') . ' ' . $instance_letter->ref_int;
				} else {
					// find refletter inforamtion
					dol_include_once('/referenceletters/class/referenceletters.class.php');
					$object_refletter = new Referenceletters($this->db);
					$result = $object_refletter->fetch($instance_letter->fk_referenceletters);
					if ($ret < 0) {
						$this->error = $object_refletter->error;
						$this->errors[] = $object_refletter->errors;

						dol_syslog(get_class($this) . $this->error, LOG_ERR);
						return - 1;
					}
					if ($instance_letter->element_type == 'thirdparty') {
						$actioncomm->label = $object_refletter->title . '-' . $instance_letter->srcobject->nom;
					} else {
						$actioncomm->label = $object_refletter->title . '-' . $instance_letter->srcobject->thirdparty->name;
					}

				}
				if(is_array($instance_letter->content_letter)) {
					$first_value = reset($instance_letter->content_letter);
				}
				$actioncomm->note = $first_value['content_text'];
				$actioncomm->datep = $now;
				$actioncomm->datef = $now;
				$actioncomm->durationp = 0;
				$actioncomm->punctual = 1;
				// Not applicable
				$actioncomm->percentage = - 1;
				// $actioncomm->contact = $contactforaction;
				if ($instance_letter->element_type == 'thirdparty') {
					$actioncomm->socid = $instance_letter->srcobject->id;
				} else {
					$actioncomm->socid = $instance_letter->srcobject->thirdparty->id;
				}
				$actioncomm->author = $user; // User saving action
				$actioncomm->userdone = $user; // User doing action
				$actioncomm->fk_element = $instance_letter->id;
				$actioncomm->elementtype = 'referenceletters_' . $instance_letter->element;
				$actioncomm->userownerid = $user->id;
				$ret = $actioncomm->create($user); // User qui saisit l'action
				if ($ret < 0) {
					$error = "Failed to insert : " . $actioncomm->error . " ";
					$this->error = $error;
					$this->errors[] = $error;

					dol_syslog(get_class($this) . $this->error, LOG_ERR);
					return - 1;
				} else {

					if (! empty($conf->global->REF_LETTER_EVTCOPYFILE)) {
						dol_include_once('/core/lib/files.lib.php');

						$objectref = dol_sanitizeFileName($instance_letter->ref_int);
						$srcdir = $conf->referenceletters->dir_output . "/".$instance_letter->element_type."/" . $objectref;
						$srcfile = $srcdir . '/' . $objectref . ".pdf";
						$destdir = $conf->agenda->dir_output . '/' . $ret;
						$destfile = $destdir . '/' . $objectref . ".pdf";

						if (dol_mkdir($destdir) >= 0) {
							$result = dol_copy($srcfile, $destfile);
							if ($result < 0) {
								$error = $langs->trans('RefLtrErrorCopyFile');
								$this->error = $error;
								$this->errors[] = $error;

								dol_syslog(get_class($this) . $this->error, LOG_ERR);
								return - 1;
							}
						}
					}
				}
			}

			// $this->results = array('myreturn' => $myvalue);
			// $this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		}
	}

	function doActions($parameters, &$object, &$action, $hookmanager) {

		global $db, $conf, $user, $langs;

		if(in_array($parameters['currentcontext'], array('propalcard', 'ordercard', 'contractcard', 'invoicecard', 'supplier_proposalcard', 'ordersuppliercard','expeditioncard'))) {

		    if($action === 'builddoc') {

				$model = GETPOST('model');

				// Récupération de l'id du modèle
				if(strpos($model, 'rfltr_') !== false) {

 					// Récupération l'id du modèle sélectionné
					$models = explode('rfltr_', $model);
					$id_model = $models[1];
					
					// MAJ de l'extrafield
					$object->array_options['options_rfltr_model_id'] = intval($id_model);
					$object->insertExtraFields();
					
					$_POST['model'] = "rfltr_dol_" . (($object->element !== 'order_supplier' && $object->element !== 'shipping') ? $object->element : $object->table_element);
					
				}
			}

		}

	}

	function formBuilddocOptions($parameters, &$object, &$action, $hookmanager) {

		global $db;

		/***** Permet d'afficher les modèles disponibles dans la liste de génération de la fiche de chaque élément *****/

		// 1 - On récupère les modèles disponibles pour ce type de document
		$element = $object->element;
		if($element === 'facture') $element = 'invoice';
		if($element === 'commande') $element = 'order';
		if($element === 'contrat') $element = 'contract';
		if($element === 'shipping') $element = 'expedition';

		$TModelsID=array();
		dol_include_once('/referenceletters/class/referenceletters.class.php');
		$object_refletters = new Referenceletters($db);
		$result = $object_refletters->fetch_all('ASC', 't.rowid', 0, 0, array('t.element_type'=>$element,'t.status'=>1));
		if ($result<0) {
			setEventMessages(null,$object_refletters->errors,'errors');
		} else {
			if (is_array($object_refletters->lines) && count($object_refletters->lines)>0) {
				foreach($object_refletters->lines as $line) {
					$TModelsID[] = array('id'=>$line->id, 'title'=>$line->title, 'default_doc'=>$line->default_doc);
				}
			}
		}

		if(count($TModelsID)==0) return 0;

		// 2 - On ajoute les données au selectmodels
		?>
		<script>

			$(document).ready(function(){
				var tab = new Array();
				var modelgeneric = $("#model").find('option[value=rfltr_dol_<?php print ($object->element !== 'order_supplier') ? $object->element : $object->table_element; ?>]');
				console.log(modelgeneric);
				if (modelgeneric.length > 0)
				{
					modelgeneric[0].remove();
				}
				
				<?php
				$defaultset=0;
				foreach($TModelsID as &$TData) {
				    $selected = 0;
				    if($TData['id'] == $object->array_options['options_rfltr_model_id']) {
				        $selected = 1;
				        $defaultset=1;
				    }
				?>
					var option = new Option('<?php print $db->escape($TData['title']); ?>', 'rfltr_<?php print $TData['id']; ?>', false, <?php print $selected; ?>);
					tab.push(option);
					$("#model").append(tab);
    				<?php
    				if (!empty($TData['default_doc']) && !$defaultset) {?>
    					$("#model").val('rfltr_<?php print $TData['id']; ?>').change();
    				<?php
						$defaultset=1;
				    }
				} 
				?>
				
			});

		</script>
		<?php
	}

}
