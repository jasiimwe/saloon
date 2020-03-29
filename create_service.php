<?php
session_start();

include('config.php');


if(!isset($_SESSION['first_name'])):
	header('location: login.php');
endif;

$get_service = mysqli_query($conn, "SELECT * FROM services");
$row = mysqli_fetch_assoc($get_service);

//$get_gender = mysqli_query($conn, "SELECT * FROM gender");
//$s = mysqli_fecth_assoc($get_gender);


//register client 
if(isset($_POST['save_service'])){
	$service_name = trim($_POST['service_name']);
	$service_description = trim($_POST['service_description']);
	$price = trim($_POST['service_price']);

	if(!empty($service_name) && !empty($service_description) && !empty($price)){

		//check if client exists
		$check_service_query = mysqli_query($conn, "SELECT service_name FROM services WHERE service_name = '$service_name' ");
		$result = mysqli_num_rows($check_service_query);
		if($result == 1 && $result != 0){
			array_push($errors, "Service already exists");
      exit();
		}else{
			$create_client_query = mysqli_query($conn, "INSERT INTO services (service_name, service_description, service_cost) VALUES ('$service_name', '$service_description', '$price')");
			if($create_client_query){
				$_SESSION['success'] = "Service successfully created";
				$_SESSION['msg_type'] = 'success';	
				header('location: show_services.php');		
			}else{
				array_push($errors, "something went wrong" .$conn-> error);
        exit();
			}
		}
	}else{
		array_push($errors, "Service Name/Service Description cannpt be empty");
    exit();
	}
}elseif (isset($_POST['update_service'])) {
  $id = trim($_POST['id']);
  $service_name = trim($_POST['service_name']);
  $service_description = trim($_POST['service_description']);
  $price = trim($_POST['service_price']);

  if(!empty($service_name) && !empty($service_description) && !empty($price)){

    $update_service_query = mysqli_query($conn, "UPDATE services SET service_name = '$service_name', service_description = '$service_description', service_cost = '$price' WHERE service_id = '$id' ");
    if($update_service_query){
      $_SESSION['success'] = "Service successfully Updated";
      $_SESSION['msg_type'] = 'success';  
      header('location: show_services.php');
    }else{
      array_push($errors, "something went wrong ".$conn-> error);
    }
  }else{
    array_push($errors, "Service Name/Service Description/Service Price cannot be empty");
  }
}


?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>NEW SERVICE</title>
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
            <h1>Create Service</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Create Service</li>
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
                <h3 class="card-title">Create Service</h3>
              </div>
              <?php include('error.php'); ?>
              <!-- /.card-header -->
              <!-- form start -->
              <?php
                
                if(isset($_REQUEST['id'])):
                  $id = $_REQUEST['id'];
                  $result = mysqli_query($conn, "SELECT * FROM services WHERE service_id = '$id'");
                  $row = mysqli_fetch_array($result);
                endif;

              ?>
              <form role="form" action="create_service.php" method="POST">
                <div class="card-body">
                  <div class="form-group">
                    <input type="hidden" name="id" value="<?php echo $row['service_id']?>">
                    <label >Service Name</label>
                    <input type="text" class="form-control" placeholder="Enter Full Name" 
                    name="service_name" value="<?php if(isset($_REQUEST['id'])): echo $row['service_name']; else: echo ""; endif; ?>">
                  </div>
                  
                  <div class="form-group">
                    <label >Service Description</label>
                    <textarea class="form-control" name="service_description" placeholder="Enter Service Description"><?php if(isset($_REQUEST['id'])): echo $row['service_description']; else: echo ""; endif; ?></textarea>
                    
                  </div>
                  <div class="form-group">
                    <label >Service Price</label>
                    <input type="text" class="form-control" name="service_price" placeholder="Enter Service Price" value="<?php if(isset($_REQUEST['id'])): echo $row['service_cost']; else: echo ""; endif; ?>"></textarea>
                    
                  </div>
                  
                  
                  </div>
                  
                  
                <!-- /.card-body -->

                <div class="card-footer">
                  <?php if(isset($_REQUEST['id'])){
                  echo "<button type='submit' name='update_service' class='btn btn-primary'>Update</button>";
                  echo "<a href='show_services.php' class='btn btn-danger' style='float: right'>Cancel</a>";
                  }else{
                    echo "<button type='submit' name='save_service' class='btn btn-success'>Save</button>";
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
