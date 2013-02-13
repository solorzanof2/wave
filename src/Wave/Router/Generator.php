<?php

namespace Wave\Router;
use \Wave\Router, 
	\Wave;

class Generator {
	
	public static function generate(){
		$reflector = new Wave\Reflector(Wave\Config::get('wave')->path->controllers);
		$reflected_options = $reflector->execute();

		$all_actions = self::buildRoutes($reflected_options);

		foreach($all_actions as $domain => $actions){					
			$route_node = new Node();
			foreach($actions as $action){
				foreach($action->getRoutes() as $route){
					$route_node->addChild($route, $action);
				}
			}
			
			Wave\Cache::store(Router::getCacheName($domain), $route_node);
		}
	}
	
	public static function buildRoutes($controllers){
		
		$compiled_routes = array();
		// iterate all the controllers and make a tree of all the possible path
		foreach($controllers as $controller){
			$base_route = new Action();
			// set the route defaults from the Controller annotations (if any)
			foreach($controller['class']['annotations'] as $annotation){
				$annotation->apply($base_route);
			}
			
			foreach($controller['methods'] as $method){
				$route = clone $base_route; // copy from the controller route
				
				if($method['visibility'] == Wave\Reflector::VISIBILITY_PUBLIC){
					foreach($method['annotations'] as $annotation)
						$annotation->apply($route);
				}
				
				$route->setAction($controller['class']['name'] . '.' . $method['name']);
				
				if($route->hasRoutes())
					$compiled_routes[$base_route->getBaseURL()][] = $route;
			}
		}
		return $compiled_routes;	
	}

}


?>