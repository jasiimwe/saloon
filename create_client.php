<?php
session_start();

include('config.php');


if(!isset($_SESSION['first_name'])):
	header('location: login.php');
endif;

$get_clients = mysqli_query($conn, "SELECT * FROM clients");
$row = mysqli_fetch_assoc($get_clients);

$get_gender = mysqli_query($conn, "SELECT * FROM gender");
//$s = mysqli_fecth_assoc($get_gender);


//register client 
if(isset($_POST['save_client'])){
	$full_name = trim($_POST['full_name']);
	$phone_number = trim($_POST['phone_number']);
	$gender = trim($_POST['gender']);

	if(!empty($full_name) && !empty($phone_number) && !empty($gender)){

		//check if client exists
		$check_client_query = mysqli_query($conn, "SELECT * FROM clients");
		$result = mysqli_fetch_assoc($check_client_query);
		if($result['full_name'] == $full_name || $result['phone_number'] == $phone_number){
			array_push($errors, "Client already exists");
		}else{
			$create_client_query = mysqli_query($conn, "INSERT INTO clients (full_name, gender, phone_number) VALUES ('$full_name', '$gender', '$phone_number')");
			if($create_client_query){
				$_SESSION['success'] = "Client successfully created";
				$_SESSION['msg_type'] = 'success';	
				header('location: show_clients.php');		
			}else{
				array_push($errors, "something went wrong" .$conn-> error);
			}
		}
	}else{
		array_push($errors, "Name/Phone/Gender cannpt be empty");
	}
}


?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>REGISTER CLIENT</title>
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
            <h1>Create Client</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Create Client</li>
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
                <h3 class="card-title">Create Client</h3>
              </div>
              <?php include('error.php'); ?>
              <!-- /.card-header -->
              <!-- form start -->
              <?php
                
                if(isset($_REQUEST['id'])):
                  $id = $_REQUEST['id'];
                  $result = mysqli_query($conn, "SELECT * FROM clients WHERE client_id = '$id'");
                  $row = mysqli_fetch_array($result);
                endif;

              ?>
              <form role="form" action="create_client.php" method="POST">
                <div class="card-body">
                  <div class="form-group">
                    <label >Full Names</label>
                    <input type="text" class="form-control" placeholder="Enter Full Name" 
                    name="full_name" value="<?php if(isset($_REQUEST['id'])): echo $row['full_name']; else: echo ""; endif; ?>">
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
                    <label >Phone Number</label>
                    <input type="text" class="form-control" placeholder="Enter Phone Number" 
                    name="phone_number" value="<?php if(isset($_REQUEST['id'])): echo $row['phone_number']; else: echo ""; endif; ?>">
                  </div>
                  
                  
                  </div>
                  
                  
                <!-- /.card-body -->

                <div class="card-footer">
                  <?php if(isset($_REQUEST['id'])){
                  echo "<button type='submit' name='update_client' class='btn btn-primary'>Update</button>";
                  echo "<a href='show_clients.php' class='btn btn-danger' style='float: right'>Cancel</a>";
                  }else{
                    echo "<button type='submit' name='save_client' class='btn btn-success'>Save</button>";
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
