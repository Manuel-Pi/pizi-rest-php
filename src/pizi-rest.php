<?php
// Define error reporting (don't report warning)
error_reporting(E_ERROR | E_PARSE);
date_default_timezone_set('UTC');

// Load libraries
require 'autoload.php';
use \Firebase\JWT\JWT;

// Get a Slim object
$app = new \Slim\App;

// Get config from config.json file
$config;
try{
	$config = file_get_contents('config.json');
	$config = json_decode($config);
} catch(E_WARNING $e){
	$app->notFound();
}

// Check path is matching regex to avoid SQL injections
function checkPath($store, $id = null){
	return preg_match('/^(\w|_|-)+$/', $store.$id);
}

// Send Json response
function jsonResponse($res, $code, $value){
	$jsonRes =$res->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($code)->withJson($value);
	return $jsonRes;
}
function notAllowed($res){
	return jsonResponse($res, 403, array("message" => "You are not allowed to proceed this request!"));
}
function serverError($res, $err){
	if($err == null){
		$err = "You are not allowed to proceed this request!";
	}
	return jsonResponse($res, 403, array("message" => $err));
}

// Check if the user is allowed to access to the specified store
function checkAccess($store, $type, $user){
	global $config;
	global $app;
	$allowed = false;
	// Check if a restriction is defined for the store
	$restriction = $config->restrictions->$store !== null ? $config->restrictions->$store : $config->restrictions->all;
	if($restriction->$type == null){
		$restriction->$type = $config->restrictions->all->$type;
	}
	if($restriction != null && $restriction->$type != null){
		if($user != null && $user['role'] == $restriction->$type){
			$allowed = true;
		}
	} else {
		$allowed = true;
	}
	return $allowed;
}

if($config != null){

	// Create the connection to DB
	$bdd = new PDO('mysql:host='.$config->db->host.';dbname='.$config->db->name.';charset=utf8', $config->db->user, $config->db->password);
	// Force PDO to throw exception on SQL errors
	$bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
	// Define base url API
	$app->get('/', function($req, $res) use($app) {
		return jsonResponse($res, 200, "Pizi REST API");
	});
	
	// Check token
	$app->add(function($req, $res, $next) use($config){
		$authorization = $req->getHeader('authorization')[0];
		$finalReq = $req;
		if($authorization){
			list($jwt) = sscanf($authorization, 'Bearer %s');
			try{
				$token = JWT::decode($jwt, $config->tokenKey, array('HS256'));
				$finalReq = $req->withAttribute('user', array("user" => $token->user, "role" => $token->role));
			} catch(Exception $e){
				//echo $e->getMessage();
			}
		}
		$res = $next($finalReq, $res);
		return $res;
	});
	
	//Get token
	$app->get('/token', function($req, $res) use($bdd, $config) {
		$token = null;
		$login = $req->getHeader('login')[0];
		$password = $req->getHeader('password')[0];
		if($login && $password){
			try{
				$query = $bdd->prepare('SELECT * FROM user where login = :login AND password = :password LIMIT 1' );
				$query->execute(array(
					':login' => $login,
					':password' => $password
				));
				$result = $query->fetchAll(PDO::FETCH_OBJ);
				if(sizeof($result) > 0){
					$time = time();
					$token = array(
						"iss" => "http://pizi-rest",
						"iat" => $time,
						"exp" => $time + (60*60),
						"user" => $login,
						"role" => $result[0]->role
					);
				}
			} catch(PDOException $e){
			}
		}
		if($token != null){
			return jsonResponse($res, 200, array("jwt" => JWT::encode($token, $config->tokenKey)));
		} else {
			return serverError($res, "Provide credentials!");
		}
	});
	
	// Define get store
	$app->get('/{store}', function($req, $res, $args) use($bdd) {
		$store = $args['store'];
		if(checkPath($store) && checkAccess($store, "get", $req->getAttribute('user'))){
			try{
				$query = $bdd->prepare('SELECT * FROM '.$store);
				$query->execute();
				$results = $query->fetchAll(PDO::FETCH_OBJ);
				return jsonResponse($res, 200, $results);
			} catch(PDOException $e){
				return serverError($res, "Error with db!");
			}
		} else {
			return notAllowed($res);
		}
	});
	
	// Define get object
	$app->get('/{store}/{id}', function($req, $res, $args) use($bdd) {
		$store = $args['store'];
		$id = $args['id'];
		if(checkPath($store, $id) && checkAccess($store, "get", $req->getAttribute('user'))){
			try{
				$query = $bdd->prepare("SHOW KEYS FROM $store WHERE Key_name = 'PRIMARY'");
				$query->execute();
				$key = $query->fetch(PDO::FETCH_OBJ);
				$key = $key->Column_name;
				$query = $bdd->prepare('SELECT * FROM '.$store.' WHERE '.$key.' = :login LIMIT 1');
				$query->execute(array(':login' => $id));
				$result = $query->fetchAll(PDO::FETCH_OBJ);
				if(sizeof($result) > 0){
					return jsonResponse($res, 200, $result[0]);
				}else{
					return serverError($res, "No item!");
				}
			} catch(PDOException $e){
				return serverError($res, "Error with db!");
			}
		} else {
			return notAllowed($res);
		}
	});
	
	// Define get object
	$app->put('/{store}/{id}', function($req, $res, $args) use($app, $bdd, $config) {
		$store = $args['store'];
		$id = $args['id'];
		if(checkPath($store, $id) && checkAccess($store, "put", $req->getAttribute('user'))){
			// Get and decode JSON request body
			$attributes = $req->getParsedBody();
			// Convert to array
			$values = "";
			$attributesRestrictions = $config->restrictions->$store != null ? $config->restrictions->$store : $config->restrictions->all;
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
				return jsonResponse($res, 200);
			} catch(PDOException $e){
				return serverError($res, "Error with db!");
			}
		} else {
			return notAllowed($res);
		}
	});
	
	// Define add new object
	$app->post('/{store}', function($req, $res, $args) use($bdd){
		$store = $args['store'];
		if(checkPath($store) && checkAccess($store, "post", $req->getAttribute('user'))){
			// Get and decode JSON request body
			$attributes = $req->getParsedBody();
			// Get value and key for db request
			$columnString = implode(',', array_keys($attributes));
			$valueString = implode(',', array_fill(0, count($attributes), '?'));
			try{
				$query = $bdd->prepare("INSERT INTO ".$store." (".$columnString.") VALUES (".$valueString.");");
				$query->execute(array_values($attributes));
				$last_id = $bdd->lastInsertId();
				return jsonResponse($res, 200, array("id" => $last_id));
			} catch(PDOException $e){
				return serverError($res, "Error with db! ".$e->getMessage());
			}
		} else {
			return notAllowed($res);
		}
	});
	
	// Define delete object
	$app->delete('/{store}/{id}', function($req, $res, $args) use($bdd){
		$store = $args['store'];
		$id = $args['id'];
		if(checkPath($store) && checkAccess($store, "delete", $req->getAttribute('user'))){
			try{
				$query = $bdd->prepare("SHOW KEYS FROM $store WHERE Key_name = 'PRIMARY'");
				$query->execute();
				$key = $query->fetch(PDO::FETCH_OBJ);
				$key = $key->Column_name;
				$query = $bdd->prepare('DELETE FROM '.$store.' WHERE '.$key.' = :login');
				$query->execute(array(':login' => $id));
				return jsonResponse($res, 200);
			} catch(PDOException $e){
				return serverError($res, "Error with db!");
			}
		} else {
			return notAllowed($res);
		}
	});
}

$app->run();