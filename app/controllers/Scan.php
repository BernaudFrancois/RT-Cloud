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
	public function show($idDisque, $option = false) {
		if (Auth::isAuth()) { //verifie user connecté
			$user = Auth::getUser();
			$disk = DAO::getOne('disque', 'id ='. $idDisque .'&& idUtilisateur = '. $user->getId());

			if($option) {
				switch($option) {
					case 'rename':
						$this->loadView('scan/rename.html', ['disk' => $disk, 'user' => $user]);
						return false;
						break;
					case 'changeTarif':
						$tarifs = DAO::getAll('tarif');
						$selected = $disk->getTarif()->getId();
						$this->loadView('scan/changeTarif.html', ['disk' => $disk, 'user' => $user, 'tarifs' => $tarifs, 'selected' => $selected]);
						return false;
						break;
					default:
						echo '<div class="alert alert-danger">Paramètre inconnu</div>';
						return false;
						break;
				}
				return false;
			}

			if(empty($disk)) {
				$msg = new DisplayedMessage();
				$msg->setContent('Le disque n\'existe pas ou ne vous appartient pas !')
					->setType('warning')
					->setDismissable(true)
					->show($this);
				return false;
			}


			$diskName = $disk->getNom();
			$occupation = $disk->getOccupation();
			$disk->occupation = DirectoryUtils::formatBytes($occupation / 100 * $disk->getQuota());
			$disk->occupationTotal = DirectoryUtils::formatBytes($disk->getQuota());

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
			$tarif = ModelUtils::getDisqueTarif($disk);


			$this->loadView("scan/vFolder.html", array('user' => $user, 'disk' => $disk, 'diskName' => $diskName, 'tarif' => $tarif));
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
			$msg = new DisplayedMessage();
			$msg->setContent('Vous devez vous connecter pour avoir accès à cette ressource')
					->setType('danger')
					->setDismissable(false)
					->show($this);
			echo Auth::getInfoUser();
		}
	}

	public function changeTarif() {
		$valid_input = ['diskId', 'userId', 'tarif'];
		if(!empty($_POST)) {
			foreach ($_POST as $input => $v) {
				if (!in_array($input, $valid_input)) {
					echo '<div class="alert alert-danger">Une erreur est survenue, veuillez réessayer ultérieurement</div>';
					echo '<a href="MyDisques/index" class="btn btn-primary btn-block">Revenir aux disques</a>';
					return false;
				}
			}

			$disk = DAO::getOne('disque', 'id = '. $_POST['diskId']);
			$diskTarif = new DisqueTarif();
			$diskTarif->setDisque($disk);

			$tarif = DAO::getOne('tarif', 'id = '. $_POST['tarif']);
			$diskTarif->setTarif($tarif);
			$diskTarif->setStartDate(date('Y-m-d H:m:s'));

			$actual_size = $disk->getOccupation() / 100 * $disk->getTarif()->getQuota() * ModelUtils::sizeConverter($disk->getTarif()->getUnite());
			$new_size = $tarif->getQuota() * ModelUtils::sizeConverter($tarif->getUnite());
			if($actual_size > $new_size) {
				echo '<div class="alert alert-danger">Vous ne pouvez réduire l\'offre actuelle puisque votre quota est supérieur au nouveau</div>';
				echo '<a href="Scan/show/'. $_POST['diskId'] .'" class="btn btn-primary btn-block">Revenir au disque</a>';
				return false;
			}
			else
				$disk->addTarif($diskTarif);

			if (DAO::update($disk, true)) {
				$this->forward('Scan', 'show', $_POST['diskId']);
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