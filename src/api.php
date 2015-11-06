<?
$headers = apache_request_headers();
if(isset($headers["key"])){
	if($headers["key"] == "theMagicKey"){
		if(preg_match("/^\/api\/\w+(\/\d+)?$/", $_SERVER['REQUEST_URI'])){
			list($store, $key) = explode("/", substr($_SERVER['REQUEST_URI'], 5));
			echo $store." ".$key;
			switch ($_SERVER['REQUEST_METHOD']){
				case 'GET':
					echo 'Get';
					break;
				case 'PUT':
					echo 'Update';
					break;
				case 'POST':
					echo 'Create';
					break;
				case 'DELETE':
					echo 'Delete';
					break;
			}
		} else {
			echo 'Bad URL!';
		}
	} else {
		echo 'Bad key!';
	}
} else if(isset($headers["login"]) && isset($headers["password"])){
	if($headers["login"] == "Manuel-Pi" && $headers["password"] == "pizi"){
		echo "theMagicKey";
	} else {
		echo 'Bad login!';
	}
} else {
	echo "Need to login!";
}






