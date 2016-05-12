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

	protected $fil = [];

	public function __construct() {
		$this->fil = [get_called_class() => get_called_class() .'/'];
	}

	public function initialize(){
		if(!RequestUtils::isAjax()){
			$this->loadView("main/vHeader.html", array("infoUser"=>Auth::getInfoUser(), 'fil' => $this->fil));
		}
	}

	public function finalize(){
		if(!RequestUtils::isAjax()){
			$this->loadView("main/vFooter.html");
		}
	}
}