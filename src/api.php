<?php
// Load libraries
require 'autoload.php';

// Get a Slim object
$app = new \Slim\Slim();	


$headers = $app->request->headers;

// Create the connection to DB
$bdd = new PDO('mysql:host=localhost;dbname=pizi;charset=utf8', 'root', '');
// Force PDO to throw exception on SQL errors
$bdd->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Define base url API
$app->get('/', function() use($app) {
    $app->response->setStatus(200);
    echo "Pizi REST API";
});

// Define get store
$app->get('/:store', function($store) use($app, $bdd) {
	// Check store name to avoid sql injections
	if(preg_match('/^(\w|_)+$/', $store) == 0) $app->notFound();
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
});

// Define get object
$app->get('/:store/:id', function($store, $id) use($app, $bdd) {
	// Check store name to avoid sql injections
	if(preg_match('/^(\w|_)+$/', $store.$id) == 0) $app->notFound();
	try{
		$query = $bdd->prepare("SHOW KEYS FROM $store WHERE Key_name = 'PRIMARY'");
		$query->execute();
		$key = $query->fetch(PDO::FETCH_OBJ);
		$key = $key->Column_name;
		print $key;
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
});

// Define add new object
$app->post('/:store', function($store) use($app, $bdd){
	// Check store name to avoid sql injections
	if(preg_match('/^(\w|_)+$/', $store) == 0) $app->notFound();
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
});

// Define delete object
$app->delete('/:store/:id', function($store, $id) use($app, $bdd){
	// Check store name to avoid sql injections
	if(preg_match('/^(\w|_)+$/', $store.$id) == 0) $app->notFound();
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
});

$app->run();