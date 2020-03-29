<?php
//session_start();
date_default_timezone_set('Africa/Kampala');
//database connection
function OpenCon()
 {
 $dbhost = "localhost";
 $dbuser = "root";
 $dbpass = "root";
 $db = "salon_loyalty";
 $conn = new mysqli($dbhost, $dbuser, $dbpass,$db) or die("Connect failed: %s\n". $conn -> error);
 
 return $conn;
 }
 
//function CloseCon($conn)
 //{
 //$conn -> close();
 //}

 //register user
$phone = "";
$email = "";
$first_name = "";
$last_name = "";
$user_role = "";
$errors = array();

//variables for registering equipment
$ser_number = "";
$field_office = "";

//connect to database
 $conn = OpenCon();


 if(isset($_POST['reg_user'])){
 	$phone = mysqli_real_escape_string($conn, $_POST['phone_number']);
 	$fname = mysqli_real_escape_string($conn, $_POST['first_name']);
 	$lname = mysqli_real_escape_string($conn, $_POST['last_name']);
 	$password_1 = mysqli_real_escape_string($conn, $_POST['password1']);
 	$password_2 = mysqli_real_escape_string($conn, $_POST['password2']);
 	$user_role = mysqli_real_escape_string($conn, $_POST['user_role']);

 	//form validation 
 	if (empty($phone)) { array_push($errors, "Phone is required"); }
  	//if (empty($email)) { array_push($errors, "Email is required"); }
 	if (empty($password_1)) { array_push($errors, "Password is required"); }
	if ($password_1 != $password_2) {
		array_push($errors, "The two passwords do not match");
	  }
	if(empty($user_role)){ array_push($errors, "User role can't be empty"); }

//check if user exists
	  $password = md5($password_1);
	  $user_check_query = "SELECT * FROM users WHERE phone_number ='$phone' AND password = '$password' LIMIT 1";
	  $result = mysqli_query($conn, $user_check_query);
	  $user = mysqli_fetch_assoc($result);

	if ($user) { // if user exists
	    if ($user['phone_number'] === $phone) {
	      array_push($errors, "Phone number already exists");
	    }

	    //if ($user['email'] === $email) {
	      //array_push($errors, "email already exists");
	    //}
  	}
  	//get role id
  	$get_roleid_query = mysqli_query($conn, "SELECT role_id FROM user_role WHERE role_name = '$user_role'");
  	$result = mysqli_fetch_assoc($get_roleid_query);
  	$rid = $result['role_id'];

  	if (count($errors) == 0) {
  	$password = md5($password_1);//encrypt the password before saving in the database



  	$query = "INSERT INTO users (role_id, first_name, last_name, phone_number, password) 
  			  VALUES('$rid','$fname','$lname','$phone','$password')";
  	$result = mysqli_query($conn, $query);
  	if($result){
  		//$_SESSION['first_name'] = $fname;
  		$_SESSION['success'] = "Registration successful";
  		header('location: login.php');
  	}else{
  		array_push($errors, "something went wrong" .$conn-> error);
  	}
  	
  }


 }

//login user

 if(isset($_POST['login'])){
 	$phone = mysqli_real_escape_string($conn, $_POST['phone']);
 	$password = mysqli_real_escape_string($conn, $_POST['password']);

 	if(empty($phone) || empty($password)){
 		array_push($errors, "phone and password is required");
  	}

  	if(count($errors) == 0){
  		$password = md5($password);
  		$query = "SELECT * FROM users WHERE phone_number ='$phone' AND password = '$password' ";
  		$results = mysqli_query($conn, $query);
  		if(mysqli_num_rows($results) == 1){

  			$get_user_row = mysqli_fetch_assoc($results);
  			unset($get_user_row['password']);

  			$_SESSION = $get_user_row;

  			//$_SESSION['success'] = "you are now logged in";
  			header('location: index.php');
  			exit;
  		}else{
  			array_push($errors, "Wrong phone/password combination");
  		}
  	}
 }

 //register equipment
 
 

 
?>