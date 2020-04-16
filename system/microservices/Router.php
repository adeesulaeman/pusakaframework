<?php 
namespace Pusaka\Microservices;

use Pusaka\Core\Loader;
use Pusaka\Http\Response;
use ReflectionClass;
use ReflectionMethod;

class Router {

	public static function middleware( $middleware, $method, $env ) {

		if( method_exists( $middleware, $method ) ) {

			$rm_mid 	= new ReflectionMethod($middleware, $method);

			$arg_value 	= [];

			foreach($rm_mid->getParameters() AS $arg) {

				$arg_class = $arg->getClass();

				if($arg_class !== NULL) {
					$arg_class = $arg_class->getName();
				}

				switch ($arg_class) {
					
					case 'Pusaka\\Core\\Loader' :
							$arg_value[] 	= $env['loader'];
						break;

				}

			}

			$rm_mid->invokeArgs($middleware, $arg_value);

		}

	}

	public static function handle( $class ) {

		if(!class_exists($class)) {
			
			http_response_code(404);
			die("Page not found. (404)");

		}

		//---------------------------------------------------------
		// create instance
		//---------------------------------------------------------
		$path_info 	 	= $_SERVER['PATH_INFO'] ?? '/';

		$path_info 		= str_replace_first('/', '', $path_info, 1);

		$segments 		= explode('/', $path_info);

		if( $segments[0] === "" ) {
			
			http_response_code(404);
			die("Page not found - S. (404)");

			return;

		}

		$method = $segments[0];

		if( !method_exists($class, $method) ) {
			
			http_response_code(404);
			die("Page not found - M. (404)");

			return;
		
		}
		
		array_shift($segments);

		//---------------------------------------------------------
		// set environtment
		//---------------------------------------------------------
		$reflector 		= new \ReflectionClass($class);

		$env 			= [
			'segments' 	=> $segments,
			'directory'	=> path(dirname($reflector->getFileName()))
		];

		$env['loader'] 	= new Loader($env);

		//---------------------------------------------------------
		// get anotation class
		//---------------------------------------------------------
		$rc 	= new ReflectionClass($class);
		$doc 	= $rc->getDocComment();
		//---------------------------------------------------------

		//---------------------------------------------------------
		// get anotation method
		//---------------------------------------------------------
		$doc_ov = $rc->getMethod($method)->getDocComment();
		unset($rc);
		//---------------------------------------------------------

		//---------------------------------------------------------
		// middleware
		//---------------------------------------------------------
		$preg 		= preg_match('/@middleware\s(.+)/', $doc, $match);

		$mid_dir 	= ROOTDIR . 'app/middleware/';

		$middleware = NULL;

		if(count($match) > 0) {

			$middleware = trim($match[1]);

		}

		$preg 		= preg_match('/@middleware\s(.+)/', $doc_ov, $match);

		if(count($match) > 0) {

			$middleware = trim($match[1]);

		}

		if( $middleware !== NULL ) {

			$mid_file 	= $mid_dir . $middleware . '.mw.php';
			
			if(!file_exists($mid_file)) {
				echo "Error 03 - Middleware file not found.";
				return;
			}

			include( $mid_file );

			$mid_class 	= ucfirst(basename($middleware)) . 'Middleware';

			if(!class_exists($mid_class)) {
				echo "Error 04 - Middleware class not found.";
				return;
			}

			$middleware = new $mid_class;

		}

		Response::header()->compile([$doc, $doc_ov]);

		//---------------------------------------------------------
		// middleware::begin()
		//---------------------------------------------------------
		if( $middleware !== NULL ) {

			self::middleware( $middleware, 'begin', $env );

		}

		//---------------------------------------------------------
		// create instance
		//---------------------------------------------------------
		$controller 	= new $class($env);
		
		$rm 			= new ReflectionMethod($controller, $method);

		$output 		= $rm->invokeArgs($controller, $segments);

		Response::out( $output );

		//---------------------------------------------------------
		// middleware::end()
		//---------------------------------------------------------
		if( $middleware !== NULL ) {

			self::middleware( $middleware, 'end', $env );

		}

	}

}