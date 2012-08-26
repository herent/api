<?php defined('C5_EXECUTE') or die('Access Denied');

class ApiRouter {

	public $requestedPath;

	public $requestedRoute;

	public function __construct() {
		$self = self::get();
		$self->parseRequest();
	}

	public static funtion get() {
		static $req;
		if (!isset($req)) {
			$req = new ApiRouter();
		}
		return $req;
	}

	public function parseRequest() {

		$pk = Package::getByHandle(C5_API_HANDLE);
		if(!$pk->config('ENABLED')) {
			return; //if we arn't enabled, kill it. should we render an api response instead?
		}
		$req = Request::get();
		$path = $req->getRequestPath();

		$path = trim($path, '/');

		$basepath = trim(BASE_API_PATH, '/');

		if (substr($path, 0, strlen($basepath)) == $basepath) {
			$dirrel = strlen(DIR_REL.'/'.DISPATCHER_FILENAME);
			if(substr($_SERVER['REQUEST_URI'], 0, $dirrel) == DIR_REL.'/'.DISPATCHER_FILENAME) { //pretty url hack
				$path = DIR_REL.'/'.DISPATCHER_FILENAME.'/'.BASE_API_PATH;
			} else {
				$path = DIR_REL.'/'.BASE_API_PATH;
			}
			//This is a path like /derp/thing/ha?params=1
			$this->requestedPath =  trim(str_replace($path, '', $_SERVER['REQUEST_URI']), array('/', ' ', '%20'));

			$request = $this->requestedPath;
			if(($pos = strpos($request, '?')) !== false) {
            	$request =  trim(substr($request, 0, $pos), array('/', ' ', '%20'));
        	}
        	$this->requestedRoute = $request;
			$this->dispatch();
		}
	}

	public function dispatch() {
		$txt = Loader::helper('text');
		$error = false;
		$route = ApiRouteList::getRouteByPath($this->requestedRoute);
		if(is_object($route) && !$route->internal) { //valid route
			$class = $txt->camelcase($route->route);
			try {
				require_once($route->file);
				if(class_exists($class)) {
					$cl = new $class;
					$cl->setupAndRun();
				}
			} catch(Exception $e) {
				$error = 500;
			}

		} else {
			$error = 400;
		}

		if($error) {
			switch($error) {
				case "500":
					$route = ApiRouteList::getRouteByPath('server_error');
					$class = $txt->camelcase($route->route);
					require_once($route->file);
					$cl = new $class;
					$cl->setupAndRun();
					break;
				case "400":
					$route = ApiRouteList::getRouteByPath('bad_request');
					$class = $txt->camelcase($route->route);
					require_once($route->file);
					$cl = new $class;
					$cl->setupAndRun();
					break;
			}
		}
		
	}

}