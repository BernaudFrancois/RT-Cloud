<?php
use micro\controllers\Controller;
use micro\utils\RequestUtils;
/**
 * Classe abstraite des contrôleurs Cloud
 * @author jcheron
 * @version 1.2
 * @package cloud.controllers
 */
abstract class BaseController extends Controller {

	protected $fil = []; // création variable pour le fil d'ariane, accessible par ses "enfants"

	public function __construct() {
		$this->fil = [get_called_class() => get_called_class() .'/']; //  recupère le nom du controller appelé
	}              //nom + lien

	public function initialize(){
		if(!RequestUtils::isAjax()){ // chargement de la vue avec le fil d'ariane
			$this->loadView("main/vHeader.html", array("infoUser"=>Auth::getInfoUser(), 'fil' => $this->fil));
		}
	}

	public function finalize(){
		if(!RequestUtils::isAjax()){
			$this->loadView("main/vFooter.html");
		}
	}
}