<?php
use micro\orm\DAO;
class Admin extends \BaseController {

	private function isAdmin() {
		if(Auth::isAuth()) {
			if(Auth::isAdmin()) {
				return true;
			}
		}
		$msg = new DisplayedMessage();
		$msg->setContent('Accès à une ressource non autorisée')
				->setType('danger')
				->setDismissable(false)
				->show($this);
		return false;
	}

	public function index() {
		if(!$this->isAdmin())
			return false;

		$count = (object)[];
		$count->all = (object)[];
		$count->today = (object)[];

		$count->all->user = DAO::count('utilisateur');
		$count->all->disk = DAO::count('disque');
		$count->all->tarif = DAO::count('tarif');
		$count->all->service = DAO::count('service');

		$count->today->user = DAO::count('utilisateur', 'DAY(createdAt) = DAY(NOW())');
		$count->today->disk = DAO::count('disque', 'DAY(createdAt) = DAY(NOW())');


		$this->loadView('Admin/index.html', ['count' => $count]);
	}

	public function users() {
		if(!$this->isAdmin())
			return false;

		$users = DAO::getAll('utilisateur');
		foreach($users as $user) {
			$user->countDisk = DAO::count('disque', 'idUtilisateur = '. $user->getId());
			$user->disks = DAO::getAll('disque', 'idUtilisateur = '. $user->getId());
			$user->diskTarif = 0;

			foreach($user->disks as $disk) {
				$tarif = ModelUtils::getDisqueTarif($disk);
				if ($tarif != null)
					$user->diskTarif += $tarif->getPrix();
			}
		}

		$this->loadView('Admin/user.html', ['users' => $users]);
	}

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

			$user->disks = DAO::getAll('disque', 'idUtilisateur = '. $user->getId());

			if(empty($user->disks))
				unset($users[$i]);

			foreach($user->disks as $disk)
				$disk->tarif = ModelUtils::getDisqueTarif($disk);

			$i++;
		}

		$this->loadView('Admin/disques.html', ['users' => $users]);
	}
}