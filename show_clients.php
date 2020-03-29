<?php
session_start();

include('config.php');

if(!isset($_SESSION['first_name'])):
	header('location: login.php');

endif;

$get_all_clients = mysqli_query($conn, "SELECT * FROM clients");


?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  
  <title>SHOW CLIENTS</title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <!-- Ionicons -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  <!-- Google Font: Source Sans Pro -->
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
  <!-- Navbar -->
  
  <!-- /.navbar -->

  <!-- Main Sidebar Container -->
  <?php include('aside.php')?>
  
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm-6">
            <h1>Manage Client</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Show client</li>
            </ol>
          </div>
        </div>
      </div><!-- /.container-fluid -->
    </section>

    <!-- Main content -->
    <section class="content">
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Show Client</h3>
              <a class="btn btn-info float-right" name="new_equipment" style="color: white;" href="create_client.php">New Client</a>
              <?php if (isset($_SESSION['success'])): ?>
                  <div class="alert alert-success" role="alert">
                    <?php 
                      echo $_SESSION['success']; 
                      unset($_SESSION['success']);
                    ?>
                  </div>
                <?php endif ?>
            </div>
            <!-- Modal -->
            
            <!-- /.card-header -->
            <div class="card-body">
              <table id="example1" class="table table-bordered table-striped">
                <thead>
                <tr>
                  <th>Full Name</th>
                  <th>Phone</th>
                  <th>Gender</th>
                  <th>Service Count</th>
                  
                  <th>Action</th>
                </tr>
                </thead>
                <tbody>
                  <?php while ($row = mysqli_fetch_array($get_all_clients)) { ?>
                   
                <tr>
                  <td><a href="#" ><?php echo $row['full_name'] ?></a></td>
                  <td><?php echo $row['phone_number'] ?>
                  </td>
                  <td><?php echo $row['gender'] ?></td>
                  <td> <?php echo $row['service_count'] ?>
                  	<?php
				         if($row['service_count'] != 0 && $row['service_count'] % 5 == 0 ){?>
				         <span class=" badge bg-warning"><?php echo "Next Service - free " ?></span>
				         <?php } ?>
                  </td>
                  
                   
                    <td class="project-actions text-center">
                          <a class="btn btn-primary btn-sm" href="client_details.php?id=<?php echo $row['client_id']?>" class="btn btn-primary">
                              <i class="fas fa-folder">
                              </i>
                              View
                          </a>
                          <a class="btn btn-info btn-sm" href="create_client.php?id=<?php echo $row['client_id']?>">
                              <i class="fas fa-pencil-alt">
                              </i>
                              Edit
                          </a>
                          <a class="btn btn-danger btn-sm" href="create_client.php?delete_equipment=<?php echo $row['client_id']?>">
                              <i class="fas fa-trash">
                              </i>
                              Delete
                          </a>
                      </td>

                    
                  
                </tr>
                <?php } ?>
                </tbody>
                <tfoot>
                <tr>
                  <th>Full Name</th>
                  <th>Phone</th>
                  <th>Gender</th>
                  <th>Service Count</th>
                  <th>Action</th>
                </tr>
                </tfoot>
              </table>
            </div>
            <!-- /.card-body -->
          </div>
          <!-- /.card -->
          <!--modal-->
          
          <!-- /.card -->
        </div>
        <!-- /.col -->
      </div>
      <!-- /.row -->
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
<!-- DataTables -->
<script src="plugins/datatables/jquery.dataTables.js"></script>
<script src="plugins/datatables-bs4/js/dataTables.bootstrap4.js"></script>
<!-- AdminLTE App -->
<script src="dist/js/adminlte.min.js"></script>
<!-- AdminLTE for demo purposes -->
<script src="dist/js/demo.js"></script>
<!-- page script -->
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