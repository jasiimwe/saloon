<?php


//include 'config.php';

class saloon 
{
	
	

	function checkTokenExpiry($token){
		$currentTime = date("Y-m-d H:i:s");

		$query = mysqli_query($conn, "SELECT * FROM tokens WHERE token = '$token' " );
		$result = mysqli_fetch_assoc($query);

		if($result['token_expiry'] <= $currentTime):
			$isOkay = false;
		else:
			$isOkay = true;
		endif;

		return $isOkay;
	}
}

?>