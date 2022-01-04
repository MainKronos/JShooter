<?php
require $_SERVER['DOCUMENT_ROOT'].'/php/db.php';
function leaderboard(){
	if($_SERVER["REQUEST_METHOD"] == "GET"){
		// prende lo score
		$result = db()->query("CALL GetScore();");

		$res = array();

		while ($row = $result->fetch()) {
			$user = $row['username'];
			$score = $row['score'];
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
?>