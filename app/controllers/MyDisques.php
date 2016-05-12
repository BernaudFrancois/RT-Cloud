<?php
use micro\controllers\Controller;
use micro\js\Jquery;
use micro\utils\RequestUtils;
use micro\orm\DAO;

class MyDisques extends BaseController{

	public function initialize(){
		if(!RequestUtils::isAjax()){
			$this->loadView('main/vHeader.html', array('infoUser' => Auth::getInfoUser(), 'fil' => $this->fil));
		}
	}

	public function index() {
		echo Jquery::compile();
		if (Auth::isAuth()){ //verifie user connecté
			$user = Auth::getUser(); // get user name		
			$userId = $user->getId(); // on recup l'id user
			$disques = \micro\orm\DAO::getAll('disque', 'idUtilisateur = '. $userId);
			//DAO BDD nom de colonne "disque" et en paramtètre idUtilisateur
			// on recup tous les disques du user, avec un tableau d'objet

			//Parcourir tableau disque par disque 
			
			foreach($disques as $disque) {
				$disque->occupation = DirectoryUtils::formatBytes($disque->getOccupation() / 100 * $disque->getQuota());
				// Creation attribut occupation , Methode formatBytes avec getOccupation / 100 et getQuota;
				$disque->occupationTotal = DirectoryUtils::formatBytes($disque->getQuota());
				// Creation attribut occupationTotal 

				$occupation = $disque->getOccupation();
				
				//Association d'un etat avec l'occupation d'un disque avec l'attribut progressStyle 
				if($occupation <= 100 && $occupation > 80)
					$disque->progressStyle = 'danger';

				if($occupation <= 80 && $occupation > 50)
					$disque->progressStyle = 'warning';

				if($occupation <= 50 && $occupation > 10)
					$disque->progressStyle = 'success';

				if($occupation <= 10 && $occupation > 0)
					$disque->progressStyle = 'info';
			}
			
			//Charge index.html avec des variables d'objet dans un tableau'

			$this->loadView('MyDisques/index.html', array('user' => $user, 'disques' => $disques));
			//STOP
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

	public function frm() { //fourni le formulaire apres avoir cliqué  sur créer un disque
		$this->loadView('MyDisques/create.html');
	}

	public function update() {
		if(isset($_POST) && !empty($_POST)) {//test disponibilité variable post
			$error = false;// initialisation d'une variable de maintenance

			if(empty($_POST['name'])) { // si erreur ...
				echo '<div class="alert alert-danger">Le nom ne doit pas être vide</div>';
				$error = true;
			}

			if(!$error) { // si pas d'erreur
				$user = Auth::getUser();
				$name = htmlspecialchars($_POST['name']);

				$disk = new Disque(); //création d'un objet disque
				$disk->setUtilisateur($user); // avec les attributs suivants
				$disk->setNom($name);

				if(DAO::insert($disk, true)) { //on insert l'objet disque avec la méthode du framework
					$cloud = $GLOBALS['config']['cloud'];
					$path = $cloud['root'] . $cloud['prefix'] . $user->getLogin() . '/' . $name; //chemin d'accès
					mkdir($path); //créer le dossier relatif au disque, physiquement

					$this->forward('Scan', 'show', $disk->getId()); //charge l'affichage du disque via le controlleur scan
					return false; // stop la fonction
				}
			}
		}
	}

	public function rename() {
		$valid_input = ['diskId', 'userId', 'name']; // tableau des inputs du formulaire ( ne provenant pas directement du formulaire)
		if(!empty($_POST)) {
			foreach($_POST as $input => $v) {
				if(!in_array($input, $valid_input)) { // si un input ne correspond pas a un champ de valid_input => retourne une erreur
					echo '<div class="alert alert-danger">Une erreur est survenue, veuillez réessayer ultérieurement</div>';
					echo '<a href="MyDisques/index" class="btn btn-primary btn-block">Revenir aux disques</a>';
					return false;
				}
			}

			$user = Auth::getUser();
			$disk = DAO::getOne('disque', 'id = '. $_POST['diskId'] .'&& idUtilisateur = '. $_POST['userId']); // on récupère le disque de l'utilisateur
				$oldname = $disk->getNom();	//on stock son ancien nom																//	a partir de l'id du disque et du user
			$disk->setNom($_POST['name']); // on lui affecte son nouveau nom

			$path = $GLOBALS['config']['cloud']['root'] . $GLOBALS['config']['cloud']['prefix'] . $user->getLogin() . '/'; //chemin du dossier
			$req = rename($path . $oldname, $path . $_POST['name']); //renommage de l'ancien dossier pour le nouveau

			if(DAO::update($disk) && $req) { //mise a jour dans la base de donnée
				$this->forward('Scan', 'show', $_POST['diskId']); // si a fonctionné
				return false;									// envoie vers la vue pour afficher le disque
			}
			else // si n'a pas fonctionné
				echo '<div class="alert alert-danger">Une erreur est survenue, veuillez rééssayer ultérieurement</div>';
		}
	}

	public function finalize(){
		if(!RequestUtils::isAjax()){
			$this->loadView("main/vFooter.html");
		}
	}

}