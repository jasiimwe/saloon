<?php
session_start();
include 'config.php';
//include 'function.php';

if(!isset($_SESSION['first_name'])):
	header('location: login.php');
endif;

//$function = new isMultipleOf5();



$id = $_REQUEST['id'];
$get_client_query = mysqli_query($conn, "SELECT * FROM clients WHERE client_id = '$id'");
$row = mysqli_fetch_assoc($get_client_query);
$client_name = $row['full_name'];

$num_query = mysqli_query($conn,"SELECT * FROM service_client WHERE client_name = '$client_name' " );

$get_services_query = mysqli_query($conn, "SELECT * FROM services");
//$result = mysqli_fetch_array($get_services_query);
$get_users_query = mysqli_query($conn, "SELECT * FROM users WHERE role_id = 2");

if(isset($_POST['attach_service'])){
	$id = trim($_POST['id']);
	$client_name = trim($_POST['client_name']);
	$service_name = trim($_POST['service']);
	$free_service = trim($_POST['free_service']);
	if($free_service == ""):
		$free_service = 'paid';
	endif;
	$serviced_by = trim($_POST['serviced_by']);

	if(empty($client_name) && empty($service_name)){
		array_push($errors, "client name and service name can't be empty");
	}

	//check the service count i
	//if less than five, add service, if not, prompt the free service and clear the service count
		//insert into service_client
		$service_client_query = mysqli_query($conn, "INSERT INTO service_client (client_name, service_name, is_free, serviced_by) VALUES ('$client_name', '$service_name', '$free_service', '$serviced_by')");
		if($service_client_query){
			//update service count
			
			$_SESSION['message'] = "Service Successfully added";
			$_SESSION['msg_type'] = "success";
		}else{
			$_SESSION['message'] = "Something went wrong while adding service " .$conn-> error;
			$_SESSION['msg_type'] = "danger";
			
		}

	
	//update service count
	$num_query = mysqli_query($conn,"SELECT * FROM service_client WHERE client_name = '$client_name' " );
	$num = mysqli_num_rows($num_query);
			
	$update = mysqli_query($conn, "UPDATE clients SET service_count = $num WHERE full_name = '$client_name'");
	if($update){
		header("location: client_details.php?id=$id");
		exit();
	}else{
		$_SESSION['message'] = "Something went wrong " .$conn-> error;
		$_SESSION['msg_type'] = "danger";
	}
	
}

//show service cycle

?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title> SALOON LOYALTY | CLIENT DETAILS</title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <!-- Ionicons -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <!-- Google Font: Source Sans Pro -->
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
</head>
<body class="hold-transition sidebar-mini">
<!-- Site wrapper -->
<div class="wrapper">
  
  
  <?php include 'aside.php'; ?>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Clients Details</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Client Details</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">

      <!-- Default box -->
      <div class="card">
        <div class="card-header">
        	
          <h3 class="card-title">Details for : <b><?php echo $row['full_name']?></b></h3>
          <?php if (isset($_SESSION['message'])): ?>
                  <div class="alert alert-<?php echo $_SESSION['msg_type']; ?>" role="alert">
                    <?php 
                      echo $_SESSION['message']; 
                      unset($_SESSION['message']);
                    ?>
                  </div>
                <?php endif ?>
          <div class="card-tools">
          	<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#modal-lg">
                  Add Service
            </button>
            <!--<button type="button" class="btn btn-tool" data-card-widget="collapse" data-toggle="tooltip" title="Collapse">
              <i class="fas fa-minus"></i></button>
            <button type="button" class="btn btn-tool" data-card-widget="remove" data-toggle="tooltip" title="Remove">
              <i class="fas fa-times"></i></button>-->
          </div>
        </div>
        <div class="card-body">

          <div class="row">
				          <div class="col-md-6">           
				            <dl class="row" style="padding-bottom: 10px;">
				              <dd class="col-sm-4">Full Name:</dd>
				                <dt class="col-sm-8"><?php echo $row['full_name']?></dt>
				                <dd class="col-sm-4">Gender:</dd>
				                <dt class="col-sm-8"><?php echo $row['gender']?></dt>              
				            </dl>
				          </div>
				          <div class="col-md-6">
				            <dl class="row" style="padding-bottom: 10px;">                
				                <dd class="col-sm-4">Phone Number:</dd>
				                <dt class="col-sm-8"><?php echo $row['phone_number']?></dt>
				                <dd class="col-sm-4">Service Count:</dd>
				                <dt class="col-sm-8"><?php echo $row['service_count']?>
				                <?php
				                	if($row['service_count'] != 0 && $row['service_count'] % 5 == 0){?>
				                		<span class=" badge bg-warning"><?php echo "Next Service - free " ?></span>
				                	<?php } ?>

				                </dt>             
				            </dl>
				          </div>
		  </div>
        </div>
        <!-- /.card-body -->
        <!--<div class="card-footer">
          Footer
        </div>-->
        <!-- /.card-footer-->
      </div>
      <!-- /.card -->

     <!--data model-->
     	<div class="modal fade" id="modal-lg">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h4 class="modal-title">Add Service</h4>
              <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
              
            </div>
            <div class="modal-body">
              
              <form role="form"  action="client_details.php" method="POST" >
              	<div class="card-body">
              		<div class="form-group">
              			<input type="hidden" name="id" value="<?php echo $row['client_id']; ?>">
              			<label>Client name</label>
              			<input class="form-control" type="text" name="client_name" value="<?php echo $row['full_name']; ?>"/>
              		</div>
              		<div class="form-group">
              			<label>Service</label>
              			<select class="form-control" name="service">
	              			<?php while ($st = mysqli_fetch_array($get_services_query)) { ?>
	                          <option value="<?php echo $st['service_name']; ?>" ><?php echo $st['service_name']; ?></option>
	                        <?php } ?>
              			</select>
              		</div>
              		<div class="form-group">
              			<label>Serviced By</label>
              			<select class="form-control" name="serviced_by">
	              			<?php while ($st = mysqli_fetch_array($get_users_query)) { ?>
	                          <option value="<?php echo $st['first_name']; ?>" ><?php echo $st['first_name']; ?></option>
	                        <?php } ?>
              			</select>
              		</div>
              		<div class="col-8">
				        <div class="icheck-primary">
				            <input type="checkbox" id="remember" name="free_service" value="free">
				            <label for="remember">
				                Free Service
				            </label>
				        </div>
          			</div>
              	</div>
              	<div class="card-footer justify-content-between">
              		
              		<button type="submit" class="btn btn-primary" name="attach_service">Add Service</button>
              	</div>
              </form>
              
            </div>
            <!--<div class="modal-footer justify-content-between">
              <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
              <button type="button" class="btn btn-primary" name="attach_service">Add Service</button>
            </div>-->
            
          </div>
          <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
      </div>


     <!-- end data model -->

     <!--show service counte-->
     <div class="card">
     	<div class="card-header">
     		<h1 class="card-title">Client service details</h3>
     	</div>
     	<div class="card-body">
              <table id="example1" class="table table-bordered table-striped">
                <thead>
                <tr>
                  <th>Client Name</th>
                  <th>Service Name</th>
                  <th>Serviced By</th>
                  <th>Nature of service</th>
                  <th>Service Date</th>
                </tr>
                </thead>
                <tbody>

                  <?php while ($row = mysqli_fetch_array($num_query)) { ?>
                   
                <tr>
                  <td><a href="#" ><?php echo $row['client_name'] ?></a></td>
                  <td><?php echo $row['service_name'] ?></td>
                  <td><?php echo $row['serviced_by'] ?></td>
                  <td><?php echo $row['is_free'] ?></td>
                  <td><?php echo $row['service_date'] ?></td>
                </tr>
                <?php } ?>
                </tbody>
                <tfoot>
                <tr>
                  <th>Client Name</th>
                  <th>Service Name</th>
                  <th>Serviced By</th>
                  <th>Nature of service</th>
                  <th>Service Date</th>
                </tr>
                </tfoot>
              </table>
     	</div>
     </div>
     

     <!--end service count-->

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
<!-- AdminLTE App -->
<script src="dist/js/adminlte.min.js"></script>
<!-- AdminLTE for demo purposes -->
<script src="dist/js/demo.js"></script>
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<script>
  $(function () {
    $("#example1").DataTable();
    $('#example2').DataTable({
      "paging": true,
      "lengthChange": false,
      "searching": false,
      "ordering": true,
      "info": true,
      "autoWidth": false,
    });
  });
</script>
</body>
</html>
