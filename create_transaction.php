<?php
session_start();

include('config.php');


if(!isset($_SESSION['first_name'])):
	header('location: login.php');
endif;

$get_service = mysqli_query($conn, "SELECT * FROM services");
//$row = mysqli_fetch_assoc($get_service);

$get_staff = mysqli_query($conn, "SELECT * FROM users WHERE role_id = 2");
//$s = mysqli_fecth_assoc($get_gender);

$get_client = mysqli_query($conn, "SELECT * FROM clients");





//register client 
if(isset($_POST['save_transaction'])){
  //$id = trim($_POST['id']);
  $client_name = trim($_POST['client_name']);
  $service_name = trim($_POST['service_name']);
  $free_service = trim($_POST['free_service']);
  if($free_service == ""):
    $free_service = 'paid';
  endif;
  $serviced_by = trim($_POST['serviced_by']);

  if(empty($client_name) && empty($service_name) && empty($serviced_by)){
    array_push($errors, "client name and service name and serviced by can't be empty");
  }

  //check the service count i
  //if less than five, add service, if not, prompt the free service and clear the service count
    //insert into service_client
    $service_client_query = mysqli_query($conn, "INSERT INTO service_client (client_name, service_name, is_free, serviced_by) VALUES ('$client_name', '$service_name', '$free_service', '$serviced_by')");
    if($service_client_query){
      //update service count
      
      $_SESSION['message'] = "Transaction Successfully added";
      $_SESSION['msg_type'] = "success";
    }else{
      $_SESSION['message'] = "Something went wrong while adding transaction " .$conn-> error;
      $_SESSION['msg_type'] = "danger";
      
    }

  
  //update service count
  $num_query = mysqli_query($conn,"SELECT * FROM service_client WHERE client_name = '$client_name' " );
  $num = mysqli_num_rows($num_query);
      
  $update = mysqli_query($conn, "UPDATE clients SET service_count = $num WHERE full_name = '$client_name'");
  if($update){
    header("location: show_transactions.php");
    exit();
  }else{
    $_SESSION['message'] = "Something went wrong " .$conn-> error;
    $_SESSION['msg_type'] = "danger";
  }
  
}

?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>NEW TRANSACTION</title>
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
            <h1>Create Trasnsaction</h1>
          </div>
          <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
              <li class="breadcrumb-item"><a href="#">Home</a></li>
              <li class="breadcrumb-item active">Create Transaction</li>
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
                <h3 class="card-title">New Transaction</h3>
              </div>
              <?php include('error.php'); ?>
              <?php if (isset($_SESSION['message'])): ?>
                  <div style="padding-top: 10px;" class="alert alert-<?php echo $_SESSION['msg_type']; ?>" role="alert">
                    <?php 
                      echo $_SESSION['message']; 
                      unset($_SESSION['message']);
                    ?>
                  </div>
                <?php endif ?>
              <!-- /.card-header -->
              <!-- form start -->
              <?php
                
                if(isset($_REQUEST['id'])):
                  $id = $_REQUEST['id'];
                  $result = mysqli_query($conn, "SELECT * FROM service_client WHERE sc_id = '$id'");
                  $row = mysqli_fetch_array($result);
                endif;

              ?>
              <form role="form" action="create_transaction.php" method="POST">
                <div class="card-body">
                  <div class="form-group">
                    <input type="hidden" name="id" value="<?php echo $row['sc_id']?>">
                    <label >Service Name</label>
                    <select class="form-control" name="service_name" id="service_name">
                          <?php
                          if(isset($_REQUEST['id'])){
                            $es = $row['service_name'];
                            while($r = $get_service->fetch_assoc()) {
                            //Display Customer Info
                              if($es == $r['service_name']  ){
                                $selected = "selected";
                              }else{
                                $selected = "";
                              }
                              $output = '<option value="'.$r['service_name'].'" '.$selected.'>'.$r['service_name'].' </option>';

                              //Echo output
                              echo $output;                   
                            }
                          }else{
                             while ($r = mysqli_fetch_array($get_service)) { 
                          $output = '<option value="'.$r['service_name'].'" '.$selected.'>'.$r['service_name'].' </option>';

                              //Echo output
                              echo $output;
                        
                          }
                        }
                          ?>
                        </select>
                  </div>
                  
                  <div class="form-group">
                    <label >Client Name</label>
                    <select class="form-control" name="client_name" id="name">
                          <?php
                          if(isset($_REQUEST['id'])){
                            $es = $row['client_name'];
                            while($r = $get_client->fetch_assoc()) {
                            //Display Customer Info
                              if($es == $r['full_name']  ){
                                
                                $selected = "selected";
                              }else{
                                $selected = "";
                              }
                              //check count for service
                              $count = $r['service_count'];
                              if($count != 0 && $count % 5 == 0):
                                $msg = " - This service is free";
                              else:
                                $msg = "";
                              endif;

                              $output = '<option value="'.$r['full_name'].'" '.$selected.'>'.$r['full_name'].' '.$msg.' </option>';

                              //Echo output
                              echo $output;                   
                            }
                          }else{
                             while ($r = mysqli_fetch_array($get_client)) { 
                              $count = $r['service_count'];
                              if($count != 0 && $count % 5 == 0):
                                $msg = " - This service is free";
                              else:
                                $msg = "";
                              endif;
                              
                              
                              
                            $output = '<option value="'.$r['full_name'].'" '.$selected.'>'.$r['full_name'].'  '.$msg.' </option>';

                              //Echo output
                              echo $output;
                        
                          }
                        }
                          ?>
                        </select>
                  </div>
                  <div class="form-group">
                    <label >Serviced By</label>
                    <select class="form-control" name="serviced_by" >
                          <?php
                          if(isset($_REQUEST['id'])){
                            $es = $row['serviced_by'];
                            while($r = $get_staff->fetch_assoc()) {
                            //Display Customer Info
                              if($es == $r['first_name']  ){
                                $selected = "selected";
                              }else{
                                $selected = "";
                              }
                              $output = '<option value="'.$r['first_name'].'" '.$selected.'>'.$r['first_name'].' </option>';

                              //Echo output
                              echo $output;                   
                            }
                          }else{
                             while ($r = mysqli_fetch_array($get_staff)) { 
                          $output = '<option value="'.$r['first_name'].'" '.$selected.'>'.$r['first_name'].' </option>';

                              //Echo output
                              echo $output;
                        
                          }
                        }
                          ?>
                        </select>
                  </div>
                  <div class="icheck-primary">
                    <input type="checkbox" id="remember" name="free_service" value="free">
                    <label for="remember">
                        Free Service
                    </label>
                </div>
                  
                  
                  </div>
                  
                  
                <!-- /.card-body -->

                <div class="card-footer">
                  <?php if(isset($_REQUEST['id'])){
                  echo "<button type='submit' name='update_transaction' class='btn btn-primary'>Update Transaction</button>";
                  echo "<a href='show_services.php' class='btn btn-danger' style='float: right'>Cancel</a>";
                  }else{
                    echo "<button type='submit' name='save_transaction' class='btn btn-success'>Enter Transaction</button>";
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
<script type="text/javascript">
  $document.ready(function(){
    $("#name").change(function(){
      var client_name = $(this).val();
      //var dataString = "client_name"+cname;

      $.ajax({
        type: "POST",
        url: "create_transaction.php",
        data: {client_name:client_name},
        success: function(result){
          $("#show").html(result);
        }
      });
    });
  });
</script>
</body>
</html>
