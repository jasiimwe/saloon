<?php
session_start();

include('config.php');


if(!isset($_SESSION['first_name'])):
	header('location: login.php');
endif;

$get_service = mysqli_query($conn, "SELECT * FROM services");
$row = mysqli_fetch_assoc($get_service);

$get_role_query = mysqli_query($conn, "SELECT * FROM user_role");
//$s = mysqli_fetch_assoc($get_role);

$get_gender = mysqli_query($conn, "SELECT * FROM gender");


//register client 
if(isset($_POST['save_user'])){
	$phone = mysqli_real_escape_string($conn, $_POST['phone_number']);
  $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
  $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
  $gender = mysqli_real_escape_string($conn, $_POST['gender']);
  $password = mysqli_real_escape_string($conn, $_POST['password']);
  //$password_2 = mysqli_real_escape_string($conn, $_POST['password2']);
  $user_role = mysqli_real_escape_string($conn, $_POST['user_role']);

  if(!empty($fname) && !empty($lname) && !empty($phone) && !empty($user_role)){
    $password = md5($password);
    $user_check_query = "SELECT * FROM users WHERE phone_number ='$phone' LIMIT 1";
    $result = mysqli_query($conn, $user_check_query);
    $user = mysqli_fetch_assoc($result);
    if($user == 1){
      array_push($errors, "User Already exists");
    }
    
    //get role id
    $get_roleid_query = mysqli_query($conn, "SELECT role_id FROM user_role WHERE role_name = '$user_role'");
    $result = mysqli_fetch_assoc($get_roleid_query);
    $rid = $result['role_id'];

    
    $password = md5($password);//encrypt the password before saving in the database



    $query = "INSERT INTO users (role_id, first_name, last_name, gender, phone_number, password) 
          VALUES('$rid','$fname','$lname', '$gender','$phone','$password')";
    $result = mysqli_query($conn, $query);
    if($result){
      //$_SESSION['first_name'] = $fname;
      $_SESSION['success'] = "User Created successfully";
      header('location: show_users.php');
    }else{
      array_push($errors, "something went wrong" .$conn-> error);
    }
    
  
  }else{
    array_push($errors, "Fields cannot be empty");
  }

//check if user exists
    
}elseif (isset($_POST['update_user'])) {
  $id = trim($_POST['id']);
  $phone = mysqli_real_escape_string($conn, $_POST['phone_number']);
  $fname = mysqli_real_escape_string($conn, $_POST['first_name']);
  $lname = mysqli_real_escape_string($conn, $_POST['last_name']);
  $gender = mysqli_real_escape_string($conn, $_POST['gender']);
  $password = mysqli_real_escape_string($conn, $_POST['password']);
  $user_role = mysqli_real_escape_string($conn, $_POST['user_role']);

  if(!empty($fname) && !empty($lname) && !empty($phone) && !empty($user_role)){

    $get_roleid_query = mysqli_query($conn, "SELECT role_id FROM user_role WHERE role_name = '$user_role'");
    $result = mysqli_fetch_assoc($get_roleid_query);
    $rid = $result['role_id'];

    $update_service_query = mysqli_query($conn, "UPDATE users SET role_id = '$rid', first_name = '$fname', last_name = '$lname', gender = '$gender', password = '$password' WHERE user_id = '$id' ");
    if($update_service_query){
      $_SESSION['success'] = "User successfully Updated";
      $_SESSION['msg_type'] = 'success';  
      header('location: show_users.php');
    }else{
      array_push($errors, "something went wrong ".$conn-> error);
    }
  }else{
    array_push($errors, "Fields cannot be empty");
  }
}


?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>NEW USER</title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <!-- Ionicons -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <!-- Google Font: Source Sans Pro -->
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  
  <?php include('aside.php')?>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Create User</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Create User</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <!-- left column -->
          <div class="col-md-6">
            <!-- general form elements -->
            <div class="card card-primary">
              <div class="card-header">
                <h3 class="card-title">Create User</h3>
              </div>
              <?php include('error.php'); ?>
              <!-- /.card-header -->
              <!-- form start -->
              <?php
                
                if(isset($_REQUEST['id'])):
                  $id = $_REQUEST['id'];
                  $result = mysqli_query($conn, "SELECT * FROM users WHERE user_id = '$id'");
                  $row = mysqli_fetch_array($result);
                endif;

              ?>
              <form role="form" action="create_users.php" method="POST">
                <div class="card-body">
                  <div class="form-group">
                    <input type="hidden" name="id" value="<?php echo $row['user_id']?>">
                    <label >Frist Name</label>
                    <input type="text" class="form-control" placeholder="Enter First Name" 
                    name="first_name" value="<?php if(isset($_REQUEST['id'])): echo $row['first_name']; else: echo ""; endif; ?>">
                  </div>
                  <div class="form-group">
                    <label >Last Name</label>
                    <input type="text" class="form-control" placeholder="Enter Last Name" 
                    name="last_name" value="<?php if(isset($_REQUEST['id'])): echo $row['last_name']; else: echo ""; endif; ?>">
                  </div>
                  <div class="form-group">
                    <label >Phone Number</label>
                    <input type="text" class="form-control" placeholder="Enter Phone Number" 
                    name="phone_number" value="<?php if(isset($_REQUEST['id'])): echo $row['phone_number']; else: echo ""; endif; ?>">
                  </div>
                  <div class="form-group">
                      <label>Gender</label>

                        <select class="form-control" name="gender" id="gender">
                          <?php
                          if(isset($_REQUEST['id'])){
                            $es = $row['gender'];
                            while($r = $get_gender->fetch_assoc()) {
                            //Display Customer Info
                              if($es == $r['gender_type']  ){
                                $selected = "selected";
                              }else{
                                $selected = "";
                              }
                              $output = '<option value="'.$r['gender_type'].'" '.$selected.'>'.$r['gender_type'].' </option>';

                              //Echo output
                              echo $output;                   
                            }
                          }else{
                             while ($r = mysqli_fetch_array($get_gender)) { 
                          $output = '<option value="'.$r['gender_type'].'" '.$selected.'>'.$r['gender_type'].' </option>';

                              //Echo output
                              echo $output;
                        
                          }
                        }
                          ?>
                        </select>
                          
                  </div>
                  <div class="form-group">
                    <label >Password</label>
                    <input type="password" class="form-control" placeholder="Enter Initial Password" 
                    name="password" value="<?php if(isset($_REQUEST['id'])): echo $row['password']; else: echo ""; endif; ?>">
                  </div>
                  <div class="form-group">
                    <label >User Role</label>
                    <select class="form-control" name="user_role" >
                      <?php
                          $rid = $row['role_id'];
                          $get_role = mysqli_query($conn, "SELECT * FROM user_role WHERE role_id = $rid");
                          $gr = mysqli_fetch_assoc($get_role);
                          if(isset($_REQUEST['id'])){
                            

                            $es = $gr['role_name'];
                            while($r = $get_role_query->fetch_assoc()) {
                            //Display Customer Info
                              if($es == $r['role_name']  ){
                                $selected = "selected";
                              }else{
                                $selected = "";
                              }
                              $output = '<option value="'.$r['role_name'].'" '.$selected.'>'.$r['role_name'].' </option>';

                              //Echo output
                              echo $output;                   
                            }
                          }else{
                             while ($r = mysqli_fetch_assoc($get_role_query)) { 
                                $output = '<option value="'. $r['role_name'].'" '.$selected.'>'. $r['role_name'].' </option>';

                              //Echo output
                              echo $output;
                        
                          }
                        }
                      ?>

                    </select>
                  </div>
                  
                </div>
                  
                  
                <!-- /.card-body -->

                <div class="card-footer">
                  <?php if(isset($_REQUEST['id'])){
                  echo "<button type='submit' name='update_user' class='btn btn-primary'>Update</button>";
                  echo "<a href='show_services.php' class='btn btn-danger' style='float: right'>Cancel</a>";
                  }else{
                    echo "<button type='submit' name='save_user' class='btn btn-success'>Save</button>";
                  }
                        

              ?>
                </div>
              </form>
            </div>
            <!-- /.card -->

            <!-- Form Element sizes -->
            
            <!-- /.card -->

            
            <!-- /.card -->

            <!-- Input addon -->
            
            <!-- /.card -->
            <!-- Horizontal Form -->
            
            <!-- /.card -->

          </div>
          <!--/.col (left) -->
          <!-- right column -->
          <div class="col-md-6">
            <!-- general form elements disabled -->
            
            <!-- /.card -->
            <!-- general form elements disabled -->
            
            <!-- /.card -->
          </div>
          <!--/.col (right) -->
        </div>
        <!-- /.row -->
      </div><!-- /.container-fluid -->
    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->
  <footer class="main-footer">
    <div class="float-right d-none d-sm-block">
      <b>Version</b> 1.0.3-pre
    </div>
    <strong>Copyright &copy; 2020 <a href="#">techavenueug</a>.</strong> All rights
    reserved.
  </footer>

  <!-- Control Sidebar -->
  <aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
  </aside>
  <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- bs-custom-file-input -->
<script src="plugins/bs-custom-file-input/bs-custom-file-input.min.js"></script>
<!-- AdminLTE App -->
<script src="dist/js/adminlte.min.js"></script>
<!-- AdminLTE for demo purposes -->
<script src="dist/js/demo.js"></script>
<script type="text/javascript">
$(document).ready(function () {
  bsCustomFileInput.init();
});
</script>
</body>
</html>
