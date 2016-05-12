<?php
use micro\orm\DAO;
class Admin extends \BaseController {

	private function isAdmin() { //fonction privée, non accessible ailleurs
		if(Auth::isAuth()) { // test connexion
			if(Auth::isAdmin()) { // test de pouvoir admin
				return true;
			}
		}
		$msg = new DisplayedMessage(); // si false
		$msg->setContent('Accès à une ressource non autorisée')
				->setType('danger')
				->setDismissable(false)
				->show($this);
		return false;
	}

	public function index() {
		if(!$this->isAdmin()) // appel de la fonction de controle d'accès
			return false;	// empeche l'execution du reste du code si elle n'est pas validée

		$count = (object)[];
		$count->all = (object)[];
		$count->today = (object)[];
		// Création d'un tableau pour compter : 2 Colonnes ALL  et TODAY

		$count->all->user = DAO::count('utilisateur');
		$count->all->disk = DAO::count('disque');
		$count->all->tarif = DAO::count('tarif');
		$count->all->service = DAO::count('service');
		// complete le tableau avec les comptes du nombre d'utilisateur, dsique ,tarif, service

		$count->today->user = DAO::count('utilisateur', 'DAY(createdAt) = DAY(NOW())');
		$count->today->disk = DAO::count('disque', 'DAY(createdAt) = DAY(NOW())');
		// Affichage d'un indicateur nouveaux dans chaque parametre 


		$this->loadView('Admin/index.html', ['count' => $count]);
		// On charge la vue avec en parametre le tableau 
	}

	//TODO 2.2 1er partie
	public function users() {
		if(!$this->isAdmin())
			return false;

		$users = DAO::getAll('utilisateur');
		//On récupere les utilisateurs
		foreach($users as $user) {
			$user->countDisk = DAO::count('disque', 'idUtilisateur = '. $user->getId());
			$user->disks = DAO::getAll('disque', 'idUtilisateur = '. $user->getId());
			$user->diskTarif = 0;
			//On créer un attribut pour le nb de disques, tous les disques, tarif des disques

			foreach($user->disks as $disk) {
				$tarif = ModelUtils::getDisqueTarif($disk);
				if ($tarif != null)
					$user->diskTarif += $tarif->getPrix();
			}
			//Pour chaque disque, on recup son prix, Si un prix est different de rien il est ajouté 
			// à l'attribut disktarif du user
		}

		$this->loadView('Admin/user.html', ['users' => $users]);
		// On charge la vue 
	}
 	//todo 2.3
	public function disques($idUtilisateur = false) {
		if(!$this->isAdmin())
			return false;

		$users = ($idUtilisateur) ? [DAO::getOne('utilisateur', 'id = '. $idUtilisateur)] : DAO::getAll('utilisateur');

		$i = 0;
		foreach($users as $user) {
			if($user->getAdmin() == 0)
				$user->status = 'Utilisateur';
			elseif ($user->getAdmin() == 1)
				$user->status = 'Administrateur';
			// C'est pour la vue

			$user->disks = DAO::getAll('disque', 'idUtilisateur = '. $user->getId());
			//On recupere les disques de tous les utilisateurs

			if(empty($user->disks))
				unset($users[$i]);
			//Si user n'a pas de disque , on le supprime

			foreach($user->disks as $disk)
				$disk->tarif = ModelUtils::getDisqueTarif($disk);

			$i++;
		}

		$this->loadView('Admin/disques.html', ['users' => $users]);
		//on charge la vue
	}
}