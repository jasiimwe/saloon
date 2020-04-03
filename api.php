<?php
ini_set('mysql.connect_timeout', 300);
ini_set('default_socket_timeout', 300);
ini_set('max_execution_time', 0);

include 'config.php';
include 'function.php';

$saloon = new saloon();


$json = file_get_contents("php://input");

$currentTime = date("Y-m-d H:i:s");
$data_object = json_decode($json, true);

//$log_api = mysqli_query($conn, "insert into api_logs (remote_host, request_body, log_date) values ('".$_SERVER['REMOTE_ADDR']."', '".mysqli_real_escape_string($conn, $json)."', '".$currentTime."')");
  //if($log_api)
  //{
	  //$log_inserted_id = mysqli_insert_id($conn);
  //}
  //else
  //{
	  //echo json_encode(array("status"=>0,"message"=>"Please try again!"));
	  //exit;
  //}


 
$response = array();
if(isset($data_object['action'])){
	switch ($data_object['action']) {
		case 'login': if(isset($data_object['phone'],$data_object['password'])){
			$phone = mysqli_real_escape_string($conn, $data_object['phone']);
			$password = mysqli_real_escape_string($conn, $data_object['password']);
			$interface = mysqli_real_escape_string($conn, $data_object['interface']);

			$hash = md5($password);


			$a = mysqli_query($conn, "SELECT u.*, r.role_name FROM users as u INNER JOIN user_role as r ON u.role_id = r.role_id WHERE phone_number = '$phone' AND password = '$hash'" );

			if($a && mysqli_num_rows($a) > 0){
				$b = mysqli_fetch_assoc($a);
				$token = bin2hex(random_bytes(32));
				$uid = $b['user_id'];
				$token_ex = date("Y-m-d H:i:s", strtotime("+30 minutes"));

				$store_token = mysqli_query($conn, "INSERT INTO tokens (user_id, token, token_expiry) VALUES ('$uid', '$token', '$token_ex') ");
				if($store_token){
					if(isset($data_object['interface']) && $data_object['interface'] == "web"){
					
						$response = array("status" => 1 , "message"=>"login successful", "token"=>$token, "user_id" => $b['user_id'], "username"=>$b['first_name'], "role_name"=>$b['role_name'],  ); 
					}else{

						$response = array("status" => 1 , "message"=>"login successful", "token"=>$token, "user_id" => $b['user_id'], "username"=>$b['first_name']." ".$b['last_name'], "role_name"=>$b['role_name'], "expiry" => $token_ex  ); 
					}
				}else{
						$response = array("status" => 0, "message" => "something went wrong ".$conn->error );
					}
				}else{
					$response = array("status" => 0, "message" => "wrong phone/password");
				}

				}else{
					$response = array('status' => 0 , 'message' => 'username/password are required' );
				}
			
		break;

		//registration
		case 'registration': if (isset($data_object['phone_number'],$data_object['first_name'],$data_object['last_name'],$data_object['password'], $data_object['gender'],$data_object['role'],$data_object['interface'],)) {
			
			$phone = mysqli_real_escape_string($conn, $data_object['phone_number']);
			$first_name = mysqli_real_escape_string($conn, $data_object['first_name']);
			$last_name = mysqli_real_escape_string($conn, $data_object['last_name']);
			$password = mysqli_real_escape_string($conn, $data_object['password']);
			$user_role = mysqli_real_escape_string($conn, $data_object['role']);
			$gender = mysqli_real_escape_string($conn, $data_object['gender']);

			//check if user exists
				$user_check_query = mysqli_query($conn, "SELECT * FROM users WHERE phone_number = '$phone'");
				$result = mysqli_num_rows($user_check_query);
				if($result > 0):
					$response = array("status" => 0 , "message"=>"user already exists" );
				else:
					//get_role_id
					$get_roleid_query = mysqli_query($conn, "SELECT role_id FROM user_role WHERE role_name = '$user_role'");
				  	$result = mysqli_fetch_assoc($get_roleid_query);
				  	$rid = $result['role_id'];

				  	$password = md5($password);

				  	//insert user
				  	$query = mysqli_query($conn, "INSERT INTO users (role_id, first_name, last_name, gender, phone_number, password) 
  			  		VALUES('$rid','$first_name','$last_name','$gender','$phone','$password')");
  			  		if($query){
  			  			$response = array("status" => 1, "message"=> "user registration successful" );
  			  		}else{
  			  			$response = array("status" => 0, "message"=>"couldn't register user". $conn->error);
  			  		}
  			  	endif;
		}else{
			$response = array("status" => 0 , "message"=>"all fields are required" );
		}
		break;
		//show users
		case 'show_users': if(isset($data_object['token'])){
			$token = mysqli_real_escape_string($conn, $data_object['token']);
			//check token expiry: 
			$check_token_expiry = mysqli_query($conn, "SELECT * FROM tokens WHERE token = '$token'");
			$res = mysqli_fetch_assoc($check_token_expiry);
			if($res['token_expiry'] <= $currentTime):
				$response = array("status" => 0, "message" => "Token has expired, please login again" );
			else:
				$uid = $res['user_id'];
				$get_role_name = mysqli_query($conn, "SELECT r.role_name FROM user_role r INNER JOIN users u ON r.role_id = u.role_id WHERE user_id = $uid");
				$res = mysqli_fetch_assoc($get_role_name);
				if($res['role_name'] != "admin"):
					$response = array("status" => 1 ,"message"=>"You don't have permission for this action" );
				else:
					$get_users = mysqli_query($conn, "SELECT u.*, r.role_name FROM users u INNER JOIN user_role r ON u.role_id = r.role_id ");
					$result = mysqli_fetch_assoc($get_users);
					if($result):
						do{
						$response['users'][] = array( 
						"first_name:" => $result['first_name'],
						"last_name" => $result['last_name'],
						"phone" => $result['phone_number'],
						"gender" => $result['gender'],
						"role_name" => $result['role_name'] );
						}
						while($result = mysqli_fetch_assoc($get_users));
					else:
						$response = array("status" => 0, "message" => "someting went wrong ".$conn->error );
					endif;
				endif;
			endif;

		}
		break;

		case 'clients':
			if(isset($data_object['method'], $data_object['token'],$data_object['full_name'], $data_object['gender'], $data_object['phone_number'])){
				$token = mysqli_real_escape_string($conn, $data_object['token']);
				$name = mysqli_real_escape_string($conn, $data_object['full_name']);
				$gender  = mysqli_real_escape_string($conn, $data_object['gender']);
				$phone = mysqli_real_escape_string($conn, $data_object['phone_number']);
				switch ($data_object['method']) {
					case 'create_client':
							//check expiry date 
							$check_expiry = $saloon->checkTokenExpiry($token);
							if($check_expiry):
								$response = array("status" => 0, "message"=>"token has expired, login again" );
							else:
								//check if client exists
								$client_exists_query = mysqli_query($conn, "SELECT * FROM clients WHERE phone_number = '$phohe'");
								$res =mysqli_num_rows($client_exists_query);
								if($res > 0):
									$response = array("status" => 0, "message" => "client already exists" );
								else:
									$create_client_query = mysqli_query($conn, "INSERT INTO clients (full_name, gender, phone_number) VALUES ('$name', '$gender', '$phone')");
									if($create_client_query):
										$response = array("status" => 1 , "message"=>"Client registered" );
									else:
										$response = array("status" => 0, "message" => "someting went wrong ".$conn->error );
									endif;
								endif;
								
							endif;
						
					break;
					case 'show_clients':

						$check_expiry = $saloon->checkTokenExpiry($token);
						if($check_expiry):
							$response = array("status" => 0 , "message" => "token has expired" );
						else:
							$get_clients = mysqli_query($conn, "SELECT * FROM clients");
							$results = mysqli_fetch_assoc($get_clients);
							do{
								$response['clients'][] = array("client_id" => $results['client_id'] ,
								"client_name"=>$results['full_name'], 
								"gender"=>$results['gender'],
								"phone_number"=>$results['phone_number'],
								"service_count"=>$results['service_count']  );
							}
							while ($results = mysqli_fetch_assoc($get_clients));
						endif; 


						
						break;
					
					default:
						$response = array("status" => 0, "message"=>"no method defined" );
						break;
				}
			}else{
				$response = array("status" => 0, "message" => "fields can't be empty" );
			}
		break;
		
		default:
			$response = array('status' => 0 , 'message' => 'No action defined1' );
			break;
	}
}else{
	$response = array('status' => 0 , 'message' => 'No action defined2' );
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
header("Content-Type: application/json");
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP/1.1
header("Expires: 0"); // Date in the past
echo json_encode($response);
  exit;

?>