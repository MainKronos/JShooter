<?php

session_start();

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// STATUS CODE

// 100 => 'Continua',
// 101 => 'Protocolli di commutazione',
// 200 => 'OK',
// 201 => 'Creato',
// 202 => 'accettato',
// 203 => 'non autorevole Informazione ',
// 204 => 'No Content',
// 205 => 'Reset Content',
// 206 => 'Contenuto parziale',
// 300 => 'Scelte multiple',
// 301 => 'Spostato in modo permanente',
// 302 => 'trovato',
// 303 => 'vedere altri',
// 304 => 'Non modificato',
// 305 => 'Usa Proxy',
// 306 => '(non utilizzato)' ,
// 307 => 'Temporary Redirect',
// 400 => 'Bad Request',
// 401 => 'non autorizzata',
// 402 => 'pagamento richiesto',
// 403 => 'Forbidden',
// 404 => 'non trovato',
// 405 => 'Metodo non consentito' ,
// 406 => 'Non accettabile',
// 407 => 'Autenticazione proxy richiesta' ,
// 408 => 'Richiesta Timeout',
// 409 => 'conflitto',
// 410 => 'finiti',
// 411 => 'lunghezza desiderata',
// 412 => 'Condizione preliminare non riuscita',
// 413 => 'Entità richiesta troppo grande ',
// 414 => 'Request-URI Too Long',
// 415 => 'Tipo di supporto non supportato' ,
// 416 => 'richiesto intervallo Non satisfiable ',
// 417 => 'L'attesa non riuscita',
// 500 => 'Internal Server Error' ,
// 501 => 'Non Implementato',
// 502 => 'Bad Gateway',
// 503 => 'Servizio non disponibile',
// 504 => 'Gateway Timeout',
// 505 => 'HTTP Version Not Supported '



function db(){
	$host = "localhost";
	$db_name = "JShooter";
	$username = "root";
	$password = "root";

	// connessione al database
	$conn = null;
	try{
		$conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
		$conn->exec("set names utf8");
	}
	catch(PDOException $exception){
		echo "Errore di connessione: " . $exception->getMessage();
	}
	return $conn;
}


// rest API

function res_help(){
	return json_encode(
		array(
			array('/, /help'=>'Informazioni generali.'),
			// array()
		)
	);
}
function signup(){
	if($_SERVER["REQUEST_METHOD"] == "POST"){
		if(isset($_REQUEST['username']) && isset($_REQUEST['password'])){
			$user = filter_var($_REQUEST['username'], FILTER_SANITIZE_STRING);
			$pass = password_hash($_REQUEST['password'], PASSWORD_DEFAULT);
			$registrazione = date("Y-m-d H:i:s");
			try {
				db()->query("INSERT INTO User value ('$user', '$pass', '$registrazione');");
			} catch (PDOException $e) {
				if($e->getCode() == '23000'){
					return json_encode(array(
						'error' => 'Username non disponibile.'
					));
				}
				return json_encode(array(
					'error' => $e->getMessage()
				));
			}
			$_SESSION["username"] = $user;
			return json_encode(array(
				'error' => false
			));
		}
	}
	http_response_code(400);
	return;
}

function login(){

	// login
	if($_SERVER["REQUEST_METHOD"] == "POST"){
		if(isset($_REQUEST['username']) && isset($_REQUEST['password'])){
			$user = filter_var($_REQUEST['username'], FILTER_SANITIZE_STRING);
			$pass = filter_var($_REQUEST['password'], FILTER_SANITIZE_STRING);
			// $pass = password_hash($_REQUEST['password'], PASSWORD_DEFAULT);

			// echo password_hash($_REQUEST['password'], PASSWORD_DEFAULT);

			$result = db()->query("SELECT * FROM User WHERE username='$user';");
			if($result->rowCount() > 0){
				$result = $result->fetch();
				if(password_verify($pass, $result['pass'])){
					$_SESSION["username"] = $result['username'];
					// http_response_code(202); // 'Accettato'
					return json_encode(array(
						'error' => false
					));
				}
			}
			return json_encode(array(
				'error' => 'Credenziali non valide.'
			));
		}
	}
	http_response_code(400);
	return;
}

function logout(){
	if(isset($_SESSION["username"])){
		session_destroy();
		return json_encode(array(
			'error' => false
		));
	}
	http_response_code(403);
	return;
}

function account($args){
	if(count($args)==0){
		if($_SERVER["REQUEST_METHOD"] == "GET"){
			if(isset($_SESSION["username"])){
				return json_encode(array(
					'error' => false,
					'data' => array('username' => $_SESSION["username"])
				));
			}else{
				return json_encode(array(
					'error' => 'Utente non loggato.'
				));
			}
		}
	}elseif ($args[0] == 'score') {
		if($_SERVER["REQUEST_METHOD"] == "POST"){
			if(isset($_SESSION["username"])){

				$data = json_decode(file_get_contents('php://input'), true);
				$gameID = $data['gameID'];
				if(isset($_SESSION[$gameID])){

					$ricezione = round(microtime(true) * 1000);

					$session = $_SESSION[$gameID];
					$_SESSION[$gameID] = null;

					$score = base64_decode($data['score']);
					$score = substr($score, $score[0], substr($score, -1));

					// se la durata della partita inviata dal client è inferiore di 1 secondi alla differenza della durata calcolata dal server
					if(($ricezione - $session['start'] - $score <= 1000) && ($ricezione - $session['start'] - $score > 0)){
						$username = $_SESSION['username'];
						$map = $session['map'];
						db()->exec("CALL UpdateScore('$username', '$map', $score);");
						return json_encode(array(
							'error' => false
						));
					}
					error_log('Errore trasmissione dati.');
					return json_encode(array(
						'error' => 'Errore trasmissione dati.'
					));
				}
				return json_encode(array(
					'error' => 'Partita non precedentemente inizializzata.'
				));
			}
			return json_encode(array(
				'error' => 'Utente non loggato.'
			));
		}
	}
	http_response_code(400);
	return;
}

function leaderboard(){
	if($_SERVER["REQUEST_METHOD"] == "GET"){
		// prende lo score
		$result = db()->query("CALL GetScore();");

		$res = array();

		while ($row = $result->fetch()) {
			$user = $row['username'];
			$score = ($row['score'] == null)? null : $row['score']/1000;
			$matches = $row['matches'];
			array_push($res, array('user'=>$user , 'score'=>$score, 'matches'=>$matches));
		}
		return json_encode(array(
			'error' => false,
			'data' => $res
		));

	}
	http_response_code(400);
	return;
}

function map(){
	if($_SERVER["REQUEST_METHOD"] == "GET"){
		if(isset($_SESSION["username"])){
			$result = db()->query('SELECT * FROM Map;');
			$lenght = $result->rowCount();
			if($lenght > 0){
				$rnd = rand(0,$lenght-1);
				$res = $result->fetchAll()[$rnd];

				$gameID = uniqid();

				$_SESSION[$gameID] = array('map'=>$res['mapID'], 'start'=>round(microtime(true) * 1000));
				
				// sleep(5);
				return json_encode(array(
					'error' => false,
					'data' => array(
						'map' => json_decode($res['map']),
						'id' => $gameID
					)
				));
			}
			http_response_code(500);
			return;
		}
		http_response_code(403);
		return;
	}
	http_response_code(400);
	return;
}



function API($ulr){
	$endpints = explode( '/', parse_url(str_replace('/api/','',$ulr), PHP_URL_PATH));
	// foreach($endpints as $x=>$elm){
	// 	echo "$x: $elm<br>";
	// }
	// $response = NULL;


	switch ($endpints[0]) {
		case '':
		case 'help':
			return res_help();
			break;
		case 'login':
			return login();
			break;
		case 'logout':
			return logout();
			break;
		case 'signup':
			return signup();
			break;
		case 'account':
			return account(array_slice($endpints,1));
			break;
		case 'leaderboard':
			return leaderboard();
			break;
		case 'map':
			return map();
			return;
		default:
			http_response_code(404);
	}
}




echo API($_SERVER['REQUEST_URI']);


?>