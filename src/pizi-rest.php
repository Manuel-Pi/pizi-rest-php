<?php
// Define error reporting (don't report warning)
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('UTC');

// Load libraries
require 'autoload.php';
use \Firebase\JWT\JWT;

// Get a Slim object
$app = new \Slim\Slim();	

// Get config from config.json file
$config;
try{
	$config = file_get_contents('config.json');
	$config = json_decode($config);
} catch(E_WARNING $e){
	$app->notFound();
}

// Check if the user is allowed to access to the specified store
function checkAccess($bdd, $store){
	global $config;
	global $app;
	$allowed = false;
	// Check if a restriction is defined for the store
	if($config->restrictions->$store != null){
		$headers = $app->request->headers;
		if($headers->get('Authorization')){
			list($jwt) = sscanf( $headers->get('authorization'), 'Bearer %s');
			try{
				$token = JWT::decode($jwt, $config->tokenKey, array('HS256'));
				$allowed = true;
			} catch(Exception $e){
			}
		} else {
			//echo "Need token!";
		}
	} else {
		$allowed = true;
	}
	if(!$allowed){
		$app->response->setStatus(500);
		$app->response()->header('Content-Type', 'application/json');
		print json_encode(array("message" => "Not allowed!"));
	}
	return $allowed;
}

// Check path is matching regex to avoid SQL injections
function checkPath($store, $id){
	$match = false;
	if(preg_match('/^(\w|_|-)+$/', $store.$id)){
		$match = true;
	} else {
		$app->notFound();
	}
	return preg_match('/^(\w|_|-)+$/', $store.$id);
}

if($config != null){

	// Create the connection to DB
	$bdd = new PDO('mysql:host='.$config->db->host.';dbname='.$config->db->name.';charset=utf8', $config->db->user, $config->db->password);
	// Force PDO to throw exception on SQL errors
	$bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
	// Define base url API
	$app->get('/', function() use($app) {
		$app->response->setStatus(200);
		echo "Pizi REST API";
	});
	
	// Define base url API
	$app->get('/token', function() use($app, $bdd, $config) {
		$token;
		$headers = $app->request->headers;
		if($headers->get('login') && $headers->get('password')){
			try{
				$query = $bdd->prepare('SELECT * FROM user where login = :login AND password = :password LIMIT 1' );
				$query->execute(array(
					':login' => $headers->get('login'),
					':password' => $headers->get('password')
				));
				$result = $query->fetchAll(PDO::FETCH_OBJ);
				if(sizeof($result) > 0){
					$time = time();
					$token = array(
						"iss" => "http://pizi-rest",
						"iat" => $time,
						"exp" => $time + 60,
						"user" => $headers->get('login'),
						"role" => $result[0]->role
					);
				}
			} catch(PDOException $e){
			}
		}
		if($token != null){
			$app->response->setStatus(200);
			$app->response()->header('Content-Type', 'application/json');
			print json_encode(array("jwt" => JWT::encode($token, $config->tokenKey)));
		} else {
			$app->response->setStatus(500);
			$app->response()->header('Content-Type', 'application/json');
			print json_encode(array("message" => "Provide credentials!"));
			//print_r($result);
		}
	});
	
	// Define get store
	$app->get('/:store', function($store) use($app, $bdd, $regEx) {
		if(checkPath($store) && checkAccess($bdd, $store)){
			try{
				$query = $bdd->prepare('SELECT * FROM '.$store);
				$query->execute();
				$results = $query->fetchAll(PDO::FETCH_OBJ);
				$app->response->setStatus(200);
				$app->response()->header('Content-Type', 'application/json');
				print json_encode($results);
			} catch(PDOException $e){
				$app->notFound();
			}
		}
	});
	
	// Define get object
	$app->get('/:store/:id', function($store, $id) use($app, $bdd, $regEx) {
		if(checkPath($store) && checkAccess($bdd, $store)){
			try{
				$query = $bdd->prepare("SHOW KEYS FROM $store WHERE Key_name = 'PRIMARY'");
				$query->execute();
				$key = $query->fetch(PDO::FETCH_OBJ);
				$key = $key->Column_name;
				$query = $bdd->prepare('SELECT * FROM '.$store.' WHERE '.$key.' = :login LIMIT 1');
				$query->execute(array(':login' => $id));
				$result = $query->fetchAll(PDO::FETCH_OBJ);
				if(sizeof($result) > 0){
					$app->response->setStatus(200);
					$app->response()->header('Content-Type', 'application/json');
					print json_encode($result[0]);
				}else{
					$app->notFound();
				}
			} catch(PDOException $e){
				$app->notFound();
			}
		}
	});
	
	// Define get object
	$app->put('/:store/:id', function($store, $id) use($app, $bdd, $regEx) {
		if(checkPath($store) && checkAccess($bdd, $store)){
			// Get and decode JSON request body
			$request = $app->request();
			$body = $request->getBody();
			// Parse json
			$attributes = json_decode($body);
			// Convert to array
			$attributes = get_object_vars($attributes);
			$values = "";
			foreach ($attributes as $key => $value){
				if(strlen($values) > 0){
					$values.= ", ";
				}
				$values.= $key." = ?";
			}
			try{
				$query = $bdd->prepare("SHOW KEYS FROM $store WHERE Key_name = 'PRIMARY'");
				$query->execute();
				$key = $query->fetch(PDO::FETCH_OBJ);
				$key = $key->Column_name;
				$query = $bdd->prepare("UPDATE ".$store." SET ".$values." WHERE ".$key." = '".$id."'");
				$query->execute(array_values($attributes));
				$last_id = $bdd->lastInsertId();
				$app->response->setStatus(200);
				$app->response()->header('Content-Type', 'application/json');
				print json_encode(array("id" => $last_id));
			} catch(PDOException $e){
				$app->notFound();
			}
		}
	});
	
	// Define add new object
	$app->post('/:store', function($store) use($app, $bdd, $regEx){
		if(checkPath($store) && checkAccess($bdd, $store)){
			// Get and decode JSON request body
			$request = $app->request();
			$body = $request->getBody();
			// Parse json
			$attributes = json_decode($body);
			if($attributes == null) $app->notFound();
			// Convert to array
			$attributes = get_object_vars($attributes);
			$columnString = implode(',', array_keys($attributes));
			$valueString = implode(',', array_fill(0, count($attributes), '?'));
			try{
				$query = $bdd->prepare("INSERT INTO ".$store." (".$columnString.") VALUES (".$valueString.");");
				$query->execute(array_values($attributes));
				$last_id = $bdd->lastInsertId();
				$app->response->setStatus(200);
				$app->response()->header('Content-Type', 'application/json');
				print json_encode(array("id" => $last_id));
			} catch(PDOException $e){
				$app->notFound();
			}
		}
	});
	
	// Define delete object
	$app->delete('/:store/:id', function($store, $id) use($app, $bdd, $regEx){
		if(checkPath($store) && checkAccess($bdd, $store)){
			try{
				$query = $bdd->prepare("SHOW KEYS FROM $store WHERE Key_name = 'PRIMARY'");
				$query->execute();
				$key = $query->fetch(PDO::FETCH_OBJ);
				$key = $key->Column_name;
				$query = $bdd->prepare('DELETE FROM '.$store.' WHERE '.$key.' = :login');
				$query->execute(array(':login' => $id));
				$app->response->setStatus(200);
				$app->response()->header('Content-Type', 'application/json');
				print json_encode(array('id' => $id));
			} catch(PDOException $e){
				$app->notFound();
			}
		}
	});
}

$app->run();