<?php
use micro\js\Jquery;
use micro\orm\DAO;

/**
 * Contrôleur permettant d'afficher/gérer 1 disque
 * @author jcheron
 * @version 1.1
 * @package cloud.controllers
 */
class Scan extends BaseController {

	public function index(){

	}

	/**
	 * Affiche un disque
	 * @param int $idDisque
	 * @param bool|string $option
	 * @return bool
	 * @throws Exception
	 */
	public function show($idDisque, $option = false) { // $option sera utilisé pour des fonctions de type rename & changeTarif
		if (Auth::isAuth()) { //verifie user connecté
			$user = Auth::getUser(); // Recupération du user (objet)
			$disk = DAO::getOne('disque', 'id ='. $idDisque .'&& idUtilisateur = '. $user->getId());
			//Recuperation du disque de l'id envoyé par le bouton
			
			if($option) { //vérification de la présence d'une option
				switch($option) { // selectionne le cas qui correspond au parametre
					case 'rename':
						$this->loadView('scan/rename.html', ['disk' => $disk, 'user' => $user]); //on charge la vue
						return false; //on stop la fonction
						break;
					case 'changeTarif':
						$tarifs = DAO::getAll('tarif'); // récupération de tous les tarifs
						$selected = $disk->getTarif()->getId(); //affichage du tarif actuel à l'affichage du menu déroulant
						$this->loadView('scan/changeTarif.html', ['disk' => $disk, 'user' => $user, 'tarifs' => $tarifs, 'selected' => $selected]);
						return false; //on stop la fonction
						break;
					default:
						echo '<div class="alert alert-danger">Paramètre inconnu</div>'; // si une option inconnue par le switch est saisie ( ne devrait pas se produire)
						return false;
						break;
				}
				return false;
			}

			if(empty($disk)) { // message si mauvais id de disque
				$msg = new DisplayedMessage();
				$msg->setContent('Le disque n\'existe pas ou ne vous appartient pas !')
					->setType('warning')
					->setDismissable(true) 
					->show($this);
				return false;
			}

			// Suite TODO 1.2
			$diskName = $disk->getNom(); //On récupere le nom au disque
			$occupation = $disk->getOccupation();
			// On réutilise le même code que dans le controlleur myDisque pour afficher les occupations
			$disk->occupation = DirectoryUtils::formatBytes($occupation / 100 * $disk->getQuota());
			$disk->occupationTotal = DirectoryUtils::formatBytes($disk->getQuota());
			// Réglage des seuils pour la barre de progression pour l'affichage d'un statut d'occupation

			if($occupation <= 100 && $occupation > 80) {
				$disk->status = 'Proche saturation';
				$disk->style = 'danger';
			}
			if($occupation <= 80 && $occupation > 50) {
				$disk->status = 'Forte occupation';
				$disk->style = 'warning';
			}
			if($occupation <= 50 && $occupation > 10) {
				$disk->status = 'RAS';
				$disk->style = 'success';
			}
			if($occupation <= 10 && $occupation > 0) {
				$disk->status = 'Peu occupé';
				$disk->style = 'info';
			}

			$disk->_services = DAO::getManyToMany($disk, 'services');
			//requetes pour recuperer tous les services du disque associé
			
			$tarif = ModelUtils::getDisqueTarif($disk);
			//Utilisation de la méthode qui permet de retourner le tarif d'un disque


			$this->loadView("scan/vFolder.html", array('user' => $user, 'disk' => $disk, 'diskName' => $diskName, 'tarif' => $tarif));
			//Chargement de la vue avec comme paramètre l'objet utilisateur, l'objet utilisateur, l'objet disque
			// et la variable contenant le nom du disque
			
			
			Jquery::executeOn("#ckSelectAll", "click", "$('.toDelete').prop('checked', $(this).prop('checked'));$('#btDelete').toggle($('.toDelete:checked').length>0)");
			Jquery::executeOn("#btUpload", "click", "$('#tabsMenu a:last').tab('show');");
			Jquery::doJqueryOn("#btDelete", "click", "#panelConfirmDelete", "show");
			Jquery::postOn("click", "#btConfirmDelete", "scan/delete", "#ajaxResponse", array("params" => "$('.toDelete:checked').serialize()"));
			Jquery::doJqueryOn("#btFrmCreateFolder", "click", "#panelCreateFolder", "toggle");
			Jquery::postFormOn("click", "#btCreateFolder", "Scan/createFolder", "frmCreateFolder", "#ajaxResponse");
			Jquery::execute("window.location.hash='';scan('" . $diskName . "')", true);
			echo Jquery::compile();
		}
		else {
			$msg = new DisplayedMessage(); // message si non connecté
			$msg->setContent('Vous devez vous connecter pour avoir accès à cette ressource')
					->setType('danger')
					->setDismissable(false) // message qu'on ne peut pas fermer
					->show($this);
			echo Auth::getInfoUser();
		}
	}

	public function changeTarif() {
		$valid_input = ['diskId', 'userId', 'tarif']; // tableau des inputs du formulaire ( ne provenant pas directement du formulaire)
		if(!empty($_POST)) {
			foreach ($_POST as $input => $v) {
				if (!in_array($input, $valid_input)) { // si un input ne correspond pas a un champ de valid_input => retourne une erreur
					echo '<div class="alert alert-danger">Une erreur est survenue, veuillez réessayer ultérieurement</div>';
					echo '<a href="MyDisques/index" class="btn btn-primary btn-block">Revenir aux disques</a>';
					return false;
				}
			}

			$disk = DAO::getOne('disque', 'id = '. $_POST['diskId']); //recuperation du disque
			$diskTarif = new DisqueTarif(); //creation d'une nouvelle instance de disque
			$diskTarif->setDisque($disk); // on lui attribue le disque récupéré

			$tarif = DAO::getOne('tarif', 'id = '. $_POST['tarif']); //on récupère le tarif grâce à  son id
			$diskTarif->setTarif($tarif); //mise à jour du tarif
			$diskTarif->setStartDate(date('Y-m-d H:m:s'));

			$actual_size = $disk->getOccupation() / 100 * $disk->getTarif()->getQuota() * ModelUtils::sizeConverter($disk->getTarif()->getUnite()); // récupération de la taille actuel en octet
			$new_size = $tarif->getQuota() * ModelUtils::sizeConverter($tarif->getUnite()); // récupération de la taille du future tarif
			if($actual_size > $new_size) { // si la capacité occupé du disque est supérieure au nouveau tarif -> refus
				echo '<div class="alert alert-danger">Vous ne pouvez réduire l\'offre actuelle puisque votre quota est supérieur au nouveau</div>';
				echo '<a href="Scan/show/'. $_POST['diskId'] .'" class="btn btn-primary btn-block">Revenir au disque</a>'; //lien de retour au disque
				return false;
			}
			else
				$disk->addTarif($diskTarif); // sinon on applique le nouveau tarif

			if (DAO::update($disk, true) === True) { // mise a jour de la DB
				$this->forward('Scan', 'show', $_POST['diskId']); // retour a l'affichage  du disque
				return false;
			} else
				echo '<div class="alert alert-danger">Une erreur est survenue, veuillez rééssayer ultérieurement</div>';
		}
	}

	public function files($dir="Datas"){
		$cloud=$GLOBALS["config"]["cloud"];
		$root=$cloud["root"].$cloud["prefix"].Auth::getUser()->getLogin()."/";
		$response = DirectoryUtils::scan($root.$dir,$root);

		header('Content-type: application/json');
		echo json_encode(array(
				"name" => $dir,
				"type" => "folder",
				"path" => $dir,
				"items" => $response,
				"root" => $root
		));
	}

	public function upload(){
		$allowed = array('png', 'jpg', 'gif', 'zip');

		if(isset($_FILES['upl']) && $_FILES['upl']['error'] == 0){

			$extension = pathinfo($_FILES['upl']['name'], PATHINFO_EXTENSION);

			if(!in_array(strtolower($extension), $allowed)){
				echo '{"status":"error"}';
				exit;
			}

			if(move_uploaded_file($_FILES['upl']['tmp_name'], $_POST["activeFolder"].'/'.$_FILES['upl']['name'])){
				echo '{"status":"success"}';
				exit;
			}
		}

		echo '{"status":"error"}';
		exit;
	}

	/**
	 * Supprime le fichier dont le nom est fourni dans la clé toDelete du $_POST
	 */
	public function delete(){
		if(array_key_exists("toDelete", $_POST)){
			foreach ($_POST["toDelete"] as $f){
				unlink(realpath($f));
			}
			echo Jquery::execute("scan()");
			echo Jquery::doJquery("#panelConfirmDelete", "hide");

		}
	}

	/**
	 * Crée le dossier dont le nom est fourni dans la clé folderName du $_POST
	 */
	public function createFolder(){
		if(array_key_exists("folderName", $_POST)){
			$pathname=$_POST["activeFolder"].DIRECTORY_SEPARATOR.$_POST["folderName"];
			if(DirectoryUtils::mkdir($pathname)===false){
				$this->showMessage("Impossible de créer le dossier `".$pathname."`", "warning");
			}else{
				Jquery::execute("scan();",true);
			}
			Jquery::doJquery("#panelCreateFolder", "hide");
			echo Jquery::compile();
		}
	}

	/**
	 * Affiche un message dans une alert Bootstrap
	 * @param String $message
	 * @param String $type Class css du message (info, warning...)
	 * @param number $timerInterval Temps d'affichage en ms
	 * @param string $dismissable Alert refermable
	 * @param string $visible
	 */
	public function showMessage($message,$type,$timerInterval=5000,$dismissable=true){
		$this->loadView("main/vInfo",array("message"=>$message,"type"=>$type,"dismissable"=>$dismissable,"timerInterval"=>$timerInterval,"visible"=>true));
	}
}