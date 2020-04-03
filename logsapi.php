<?php
 ini_set('mysql.connect_timeout', 300);
 ini_set('default_socket_timeout', 300);
 ini_set('max_execution_time', 0);
?>
<?php date_default_timezone_set("Africa/Kampala"); ?>
<?php require_once("../Connections/FuelDB.php"); ?>
<?php require_once("../functions.php"); $api = new tracking(); ?>
<?php
  //Json
  $json = file_get_contents("php://input");
  
  //Maintenance Mode
  if(file_exists("maintenance.txt") && file_get_contents("maintenance.txt") == "1")
  {
  	$response = array("status"=>0,"message"=>"MAINTENANCE MODE");
	goto app_end;
  }
  
  if(isset($_GET) && sizeof($_GET) > 0)
  {
	 $temp = array();
	 $keys = array_keys($_GET);
	 foreach($keys as $item)
	 {
		 $temp[$item] = $_GET[$item];
	 }
	 $json = json_encode($temp);
  }
      
  $currentTime = date("Y-m-d H:i:s");
  $data_object = json_decode($json, true);
  
  //Blocking Vs Non Blocking Requests
  if(isset($data_object['requestType']) && $data_object['requestType'] == "async" && $data_object['action'] != 'query_async')
  {
	  //Log Request
	  $data_object['requestType'] = NULL;
	  unset($data_object['requestType']);

	  //Delay Minutes
	  $delay = (isset($data_object['delay_minutes']) && is_numeric($data_object['delay_minutes'])) ? $data_object['delay_minutes'] : 0;
	  
	  $logR = mysqli_query($db, "insert into async_request (remote_host, request_body, log_date, to_execute_date) values ('".$_SERVER['REMOTE_ADDR']."', '".mysqli_real_escape_string($db, json_encode($data_object))."', '".$currentTime."', date_add('".date("Y-m-d H:i:s")."', interval ".$delay." minute))");
	  
	  if($logR)
	  {
		  $insertID = mysqli_insert_id($db);
		  $response = array("status"=>1,"message"=>"Please wait for SMS Confirmation . . .","async_id"=>$insertID);
	  }
	  else
	  {
	  	$response = array("status"=>0,"message"=>"Please try again later.");
	  }
	  goto app_end;
  }
  
  
  //@mail("joseph@thinvoid.com","test",sizeof($data_object));
 $log_api = mysqli_query($db, "insert into api_logs (remote_host, request_body, log_date) values ('".$_SERVER['REMOTE_ADDR']."', '".mysqli_real_escape_string($db, $json)."', '".$currentTime."')");
  if($log_api)
  {
	  $log_inserted_id = mysqli_insert_id($db);
  }
  else
  {
	  echo json_encode(array("status"=>0,"message"=>"Please try again!"));
	  exit;
  }
  
  $response = array();
  if(isset($data_object['action']))
  {
		switch($data_object['action'])
		{
			case 'login': if(isset($data_object['phone'],$data_object['password']))
						  {
							  $a = mysql_query("select * from `login_user` where (phone = '".mysql_real_escape_string($api->format_number($data_object['phone']))."' or username = '".mysql_real_escape_string($data_object['phone'])."') and `password` = '".mysql_real_escape_string(sha1($data_object['password']))."' and global_status = '1'".((isset($data_object['device']) && trim($data_object['device']) != "" && $data_object['phone'] != "9191") ? " and id in (select distinct(`user`) from login_user_device where imei = '".mysql_real_escape_string($data_object['device'])."')" : ""));
							  if($a && mysql_num_rows($a) > 0)
							  {
								  
								  $b = mysql_fetch_assoc($a);
								  $token = $api->token($b['id']);
								  if($token != "")
								  {
								  		if(isset($data_object['interface']) && $data_object['interface'] == 'web')
										{
											$auth_token = rand(00000,99999);
											
											//Send SMS with Token
											$api->send_sms('TAMBULA',$b['phone'],"SMS Authentication: ".$auth_token,'normal');
											$api->send_email($b['name'],$b['email'],'Tambula SMS Token','Login Token: '.$auth_token);
											
											$response = array("status"=>1,"message"=>"login succesful","user_id"=>$b['id'],"token"=>$token,"sms_auth"=>(sha1(sha1(md5($auth_token)))),"access_level"=>$b['access_level'],"web_status"=>$b['web_status'],"username"=>($b['username']),"phone"=>$b['phone'],"name"=>$b['name'],"timezone"=>$b['timezone']);
										}
										else
										{
											$response = array("status"=>1,"message"=>"login succesful","user_id"=>$b['id'],"token"=>$token,"access_level"=>$b['access_level'],"web_status"=>$b['web_status'],"name"=>$b['name'],"timezone"=>$b['timezone']);
										}
								  }
								  else
								  {
									  $response = array("status"=>0,"message"=>"unspecified error occurred");
								  }
							  }
							  else
							  {
								$response = array("status"=>0,"message"=>"invalid phone/password","token"=>"");  
							  }
						  }
						  else
						  {
							  $response = array("status"=>0,"message"=>"invalid phone number or password parameters");
						  }
						  break;
			
			case 'reset_pin': if(isset($data_object['phone']))
						  {
							  $a = mysql_query("select * from `login_user` where phone = '".mysql_real_escape_string($api->format_number($data_object['phone']))."' and `password` = '".mysql_real_escape_string(sha1($data_object['password']))."' and global_status = '1'");
							  if($a && mysql_num_rows($a) > 0)
							  {
								  
								  $b = mysql_fetch_assoc($a);
								  $token = $api->token($b['id']);
								  if($token != "")
								  {
								  	$response = array("status"=>1,"message"=>"login succesful","token"=>$token,"access_level"=>$b['access_level'],"web_status"=>$b['web_status']);
								  }
								  else
								  {
									  $response = array("status"=>0,"message"=>"unspecified error occurred");
								  }
							  }
							  else
							  {
								$response = array("status"=>0,"message"=>"invalid phone/password","token"=>"");  
							  }
						  }
						  else
						  {
							  $response = array("status"=>0,"message"=>"invalid phone number or password parameters");
						  }
						  break;
			
			case 'sos_message': if(isset($data_object['phone'],$data_object['token']))
							 	{
						    		$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
									if($user_id != "")
									{
										$customer_details = $api->get_records2("loan_client",array("id","fname","lname"),"where phone = '".$data_object['phone']."' or phone2 = '".$data_object['phone']."' and status = '1'");
										
										$customer_id = $customer_details[0]['id'] == "" ? "null" : "'".mysql_real_escape_string($customer_details[0]['id'])."'";
										//print_r($customer_details); exit;
										$go = mysql_query("insert into `sos` (phone, customer, `log_date`) values ('".mysql_real_escape_string($data_object['phone'])."', ".$customer_id.", '".date("Y-m-d H:i:s")."')");
										if($go)
										{
											//Records
											$contacts = $api->get_records2("loan_client_contact",array("name","phone"),"where customer = '".$customer_details[0]['id']."' and status = '1'");
											foreach($contacts as $msg)
											{
												if(trim($msg['phone']) != "")
												{
													$api->send_sms('TAMBULA',$msg['phone'],'EMMERGENCY ALERT | KALANGO:\n\r'.trim($customer_details[0]['fname']).' '.trim($customer_details[0]['lname']).' NEEDS YOUR HELP. PLEASE GET IN TOUCH ON +'.$data_object['phone']."\n\r---\n\r".trim($customer_details[0]['fname']).' '.trim($customer_details[0]['lname']).' YEETAGA OBUYAMBI BWO. MU KUBIIRE SSIMU KU +'.$data_object['phone'],'normal');
												}
											}
											
											$response = array("status"=>1,"message"=>"We have sent your SOS message.");
										}
										else
										{
											error_log(mysql_error());
											$response = array("status"=>0,"message"=>mysql_error());
										}
									}
									else
									{
										$response = array("status"=>0,"message"=>"invalid user token");
									}
							 }
								else
								{
									$response = array("status"=>0,"message"=>"invalid parameters submitted");
								}
								break;
			
			case 'add_sos_contact': if(isset($data_object['phone'],$data_object['contact'],$data_object['order'],$data_object['token']))
							 		{
											$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$customer_details = $api->get_records2("loan_client",array("id","fname","lname"),"where phone = '".$data_object['phone']."' or phone2 = '".$data_object['phone']."' and status = '1'");
												
												//print_r($customer_details); exit;
												if($data_object['phone'] != $data_object['contact'])
												{
													$order_record = $api->get_record("loan_client_contact","id","where customer = '".$customer_details[0]['id']."' and status='1' order by id asc limit ".($data_object['order']-1).",1");
													
													$contact_limit = 4;
													if($api->get_record("loan_client_contact","count(*)","where customer = '".$customer_details[0]['id']."' and status = '1'") == $contact_limit || $order_record != "")
													{
														goto update_contact;
													}
													
													$go = mysql_query("insert into `loan_client_contact` (customer, phone, `log_date`) values ('".$customer_details[0]['id']."', '".mysql_real_escape_string($data_object['contact'])."', '".date("Y-m-d H:i:s")."')");
													if($go)
													{
														//Insert
														$response = array("status"=>1,"message"=>"Contact has been added.");
													}
													else
													{
														//Update
														update_contact:
														$id = $api->get_record("loan_client_contact","id","where customer = '".$customer_details[0]['id']."' and status='1' order by id asc limit ".($data_object['order']-1).",1");
														
														$go = mysql_query("update loan_client_contact set phone = '".mysql_real_escape_string($data_object['contact'])."', name=null, log_date = '".date("Y-m-d H:i:s")."' where customer = '".$customer_details[0]['id']."' and id = '".$id."' limit 1");
														
														if(mysql_affected_rows() > 0)
														{
															$response = array("status"=>1,"message"=>"Update succesfull.");
														}
														else
														{
															$response = array("status"=>0,"message"=>mysql_error());
														}
													}
												}
												else
												{
													$response = array("status"=>0,"message"=>"emmergency contact and your phone number should be different.");
												}
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
									 }
									else
									{
										$response = array("status"=>0,"message"=>"invalid parameters submitted");
									}
									break;
			
			case 'lost_bike': if(isset($data_object['bike_reg'],$data_object['theft_date'],$data_object['description'],$data_object['contact_phone'],$data_object['token']))
							 {
								
						    	$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
								if($user_id != "")
								{
									$go = mysql_query("insert into `lost_bike_report` (reg_number, theft_date, `description`, contact_phone, `user`, log_date) values ('".mysql_real_escape_string($data_object['bike_reg'])."', '".mysql_real_escape_string($data_object['theft_date'])."', '".mysql_real_escape_string($data_object['description'])."', '".mysql_real_escape_string($data_object['contact_phone'])."', '".$user_id."', '".date("Y-m-d H:i:s")."')");
									if($go)
									{
										$response = array("status"=>1,"message"=>"bike information has sucessfully been logged.");
									}
									else
									{
										error_log(mysql_error());
										$response = array("status"=>0,"message"=>mysql_error());
									}
								}
								else
								{
									$response = array("status"=>0,"message"=>"invalid user token");
								}
							 }
							 else
							 {
									$response = array("status"=>0,"message"=>"invalid parameters submitted");
							 }
							 break;
			case 'lost_password': if(isset($data_object['phone']))
								   {
										$newpass = $api->random_password_numeric();
										$go = $api->get_record("login_user","phone","where phone = '".mysql_real_escape_string($api->format_number($data_object['phone']))."'");
										if($go != "")
										{
											mysql_query("insert into customer_query (phone, issue_desc, `date`) values ('".mysql_real_escape_string($data_object['phone'])."', 'STATION PIN RESET REQUEST', '".date("Y-m-d H:i:s")."')");
											
											$response = array("status"=>1,"message"=>"We have taken note of your request and will respond shortly.");
										}
										else
										{
											$response = array("status"=>0,"message"=>mysql_error());
										}
										 
								   }
								   else
								   {
									   $response = array("status"=>0,"message"=>"invalid parameters");
								   }
								   break;
			case 'account_balance': if(isset($data_object['token']))
						 		   	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
												if(!isset($data_object['balance_type']))
												{
													//Collections Balance
													$balance = $api->fuel_agent_bal($user_id);
												}
												else
												{
													$balance = $api->fuel_agent_bal($user_id,array('fuel'));
												}
												
												if($access_level == 'gas-station' || $access_level == 'partner')
												{
													//Pre-paid Balance
													//Post-paid Balance
												}
																								
												$msg = 'Tambula Balance: '.number_format($balance);
												/*$phone_number = $api->get_record("login_user","phone","where id = '".$user_id."'");
												
												if(!isset($data_object['no_sms']))
												{
													$api->send_sms('TAMBULA',$api->format_number($phone_number),$msg,'normal');
												}*/
												$response = array("status"=>1,"message"=>$balance);
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		   	else
						 		   	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		    }
						     	   	break;
									
			case 'account_balance_summary': if(isset($data_object['token']))
						 		   			{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												
																								
												$msg = 'Tambula Balance: '.number_format($balance);
												
												$phone_number = $api->get_record("login_user","phone","where id = '".$user_id."'");
												
												if(!isset($data_object['no_sms']))
												{
													$api->send_sms('TAMBULA',$api->format_number($phone_number),$msg,'normal');
												}
												
												$response = array("status"=>1,"message"=>$balance);
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		   			else
						 		   			{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		    }
						     	   			break;
									
			case 'sp_leaderboard':  if(isset($data_object['token']))
						 		   	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$response = array();
												$n = mysql_query("select id, name from login_user where access_level = 'gas-station'");
												$n2 = mysql_fetch_assoc($n);
												do
												{
													$response[] = array("provider_id"=>$n2['id'],"provider_name"=>$n2['name'],"float"=>"","pending_count"=>$api->get_record("loan","count(*)","where loan.service_provider = '".$n2['id']."' and loan.voucher is not null and loan.id in (select loan_status.loan from loan_status where loan_status.`status` = 'approved') and loan.id in (select distinct(payment.loan) from payment having sum(payment.amount) >= loan.total_amount)"),"pending_amount"=>$api->get_record("loan","loan.total_amount","where loan.service_provider = '".$b2['id']."' and loan.voucher is not null and loan.id in (select loan_status.loan from loan_status where loan_status.`status` = 'approved') and loan.id in (select distinct(payment.loan) from payment having sum(payment.amount) >= loan.total_amount)"));
												} while ($n2 = mysql_fetch_assoc($n));
												
												goto app_end;
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		   	else
						 		   	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		    }
						     	   	break;
			case 'card_balance':    if(isset($data_object['token'],$data_object['user_phone']))
						 		   	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
												$customer_id = $api->get_record("loan_client","id","where phone = '".$api->format_number($data_object['user_phone'])."'".($access_level == 'admin' || $access_level == 'overide' ? "" : " and reg_user = '".$user_id."'"));
												$response = array("status"=>1,"message"=>$api->customer_card_bal($customer_id));
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		   	else
						 		   	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		    }
						     	   	break;
									
			case 'purchase_float': //Purchase Float
								   if(isset($data_object['user_phone'],$data_object['amount'],$data_object['token']))
						 		  	{
						    			$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
										if($user_id != "")
										{
											$ref = sha1(date("Y-m-d H:i:s"));
											$url = 'https://tango.tambula.net/beyonic/mmPayment.php?phone='.$data_object['user_phone'].'&amount='.$data_object['amount'].'&transact_id='.$ref.'&type=purchase_float';
											$mm_ref = $api->sendRequest($url);

											if($mm_ref != "" && is_numeric($mm_ref))
											{
													//Generate Async Request
													$request_response = $api->post(json_encode(array("ref"=>$mm_ref, "action"=>"suspense_status","token"=>$data_object['token'],"requestType"=>"async")),$api->fuel_api);

													$response = array("status"=>1,"message"=>$request_response);	
											}
											else
											{
												$response = array("status"=>0,"message"=>"Error with mobile money");
											}
										}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		   	else
						 		   	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		    }
								   break;
			case 'list_cashouts': //List-cashout requests
								  if(isset($data_object['token']))
						 		  	{
						    			$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
										if($user_id != "")
										{
											$response = array("status"=>1,"requests");
											//List Cashouts
											$go = mysql_query("select * from cash_out where id is not null ".(isset($data_object['from'],$data_object['to']) ? "and substr(log_date,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : ""));
											$goRows = mysql_fetch_assoc($go);
											do
											{
												$response['requests'][] = array("id"=>$goRows['id'],"user_id"=>$goRows['user'],"name"=>$api->get_record("login_user","name","where id = '".$goRows['user']."'"),"collections"=>$goRows['collections'],"deficit"=>$goRows['deficit'],"amount"=>$goRows['amount'],"status"=>$goRows['status'],"log_date"=>$goRows['log_date'],"modified_by"=>$goRows['modified_by'],"modified_name"=>$api->get_record("login_user","name","where id = '".$goRows['modified_by']."'"),"modify_date"=>$goRows['modify_date']);
											} while ($goRows = mysql_fetch_assoc($go));
										}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
									}
									else
							 		{
								 		$response = array("status"=>0,"message"=>"invalid parameters set");
							 		}
									break;
			case 'suspense_status': //Suspense table is being used to track beyonic payments.
									//Check Status of those transactions
								    if(isset($data_object['ref'],$data_object['token']))
						 		  	{
						    			$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
										if($user_id != "")
										{
											//Retrieve Json
											$requestobj = json_decode($api->get_record("suspense","comments","where id = '".mysql_real_escape_string($data_object['ref'])."' and pay_source='beyonic' and comments2 is null"),true);
											
											$purcahse_float = $api->get_record("suspense","source_ref_1","where id = '".mysql_real_escape_string($data_object['ref'])."' and pay_source='beyonic' and comments2 is null");

											if($requestobj['id'] != "")
											{
												require( '../beyonic/lib/Beyonic.php' );
												Beyonic::setApiVersion("v2");
												/* Set the API Key to be used in all requests */
												Beyonic::setApiKey( '4f2b0380d00b70cdbc0ec4042ea61288ef3628de' );
												$collection = json_encode(Beyonic_Collection_Request::get($requestobj['id']));

												$collection = json_decode($collection, true);

												switch($collection['status'])
												{
												  case 'pending': //Try again
												  					$request_response = $api->post(json_encode(array("ref"=>$data_object['ref'], "action"=>"suspense_status","token"=>$data_object['token'],"requestType"=>"async","delay_minutes"=>5)),$api->fuel_api);
												  					$response = array("status"=>1,"message"=>"pending");
												  				    goto app_end;
												  case 'successful': //All Good
												  					 if($purcahse_float == 'float-'.$data_object['ref'])
																	 {
																	 	$request_response = json_decode($api->post(json_encode(array("user_phone"=>preg_replace("/[^0-9]/","",$collection['phonenumber']),"amount"=>round($collection['amount']),"requestType"=>"async","action"=>"transfer_float","token"=>$data_object['token'])),$api->fuel_api), true);
																	 }
																	 else
																	 {
																		 $request_response = json_decode($api->post(json_encode(array("user_phone"=>preg_replace("/[^0-9]/","",$collection['phonenumber']),"amount"=>round($collection['amount']),"payment_mode"=>"tambula_app","comments"=>"USSD Transaction","requestType"=>"async","action"=>"make_payment","token"=>$data_object['token'])),$api->fuel_api), true);
																	 }
												  					 //Update Suspense
												  					 mysql_query("update suspense set comments2 = '".mysql_real_escape_string(json_encode($collection))."' where id = '".mysql_real_escape_string($data_object['ref'])."'");
												  					 $response = array("status"=>1,"message"=>"succesful");
												  					 goto app_end;
												  case 'failed': mysql_query("update suspense set comments2 = '".mysql_real_escape_string(json_encode($collection))."' where id = '".mysql_real_escape_string($data_object['ref'])."'");
												  				 $sms = 'Dear customer, your recent mobile money transasction on Tambula Failed: '.$collection['error_message'];
												  				 $api->send_sms('Tambula',$collection['phonenumber'],$sms);
												  				 $response = array("status"=>1,"message"=>"failed");
												  				 goto app_end;
												  default: //All Else
												  		   $response = array("status"=>1,"message"=>$collection['status']);
												  		   goto app_end;
												}

											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid id");
											}
										}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
									}
									else
							 		{
								 		$response = array("status"=>0,"message"=>"invalid parameters set");
							 		}
							     	break;

			case 'make_payment': if(isset($data_object['user_phone'],$data_object['amount'],$data_object['token']))
						 		  {
						    		$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
									if($user_id != "")
									{
										//Amount To Offeset
										if(trim($data_object['amount']) == "" && isset($data_object['reference']) && $data_object['reference'] != "")
										{
											$data_object['amount'] = $api->get_record("suspense","amount","where id = '".mysql_real_escape_string($data_object['reference'])."'");
										}
										
										if($api->get_record("login_user","phone","where id = '".$user_id."'") != $data_object['user_phone'])
										{
											//if(trim($api->get_record("loan_client","user_is_agent","where phone = '".$api->format_number($data_object['user_phone'])."'")) == "")
											//{
											$mode = (isset($data_object['payment_mode']) && trim($data_object['payment_mode']) != "") ? $data_object['payment_mode'] : "tambula_app";
											
											if($mode == "tambula_app" || $mode == "card_debit")
											{	
												$float_type = 'agent';
												
													if($api->get_record("loan_client","count(*)","where phone = '".$api->format_number($data_object['user_phone'])."' and `status` = '1'") == 1)
													{
														//Card Debit.
														if(isset($data_object['payment_mode']) && $data_object['payment_mode'] == 'card_debit' && $data_object['customer_id'] != "")
														{
															if($data_object['amount'] <= $api->customer_card_bal($data_object['customer_id']))
															{
																$float_type = 'customer';
																$n = mysql_query("insert into customer_float (`customer`, loan, amount, remarks, transact_date, ref) values ('".$data_object['customer_id']."', '".$data_object['loan']."', '-".mysql_real_escape_string($data_object['amount'])."', '".mysql_real_escape_string($data_object['description'])."', '".date("Y-m-d H:i:s")."', '".sha1($user_id.$api->format_number($data_object['user_phone']).$data_object['amount'].$data_object['description'].date("Y-m-d H:"))."')");
																goto debit;
															}
															else
															{
																
																$response = array("status"=>0,"message"=>"Insufficient funds on card to complete transaction.");
																goto app_end;
															}
														}
														
														//Fuel Agent
														if($api->get_record("login_user","phone","where id = '".$user_id."'") != $data_object['user_phone'])
														{
															if($api->fuel_agent_bal($user_id) >= $data_object['amount'])
															{
																$n = mysql_query("insert into user_float (`user`, amount, `comments`, date, signature) values ('".$user_id."', '-".mysql_real_escape_string($data_object['amount'])."', '".mysql_real_escape_string($data_object['description'])."', '".date("Y-m-d H:i:s")."', '".sha1($user_id.$api->format_number($data_object['user_phone']).$data_object['amount'].$data_object['description'].date("Y-m-d H:"))."')");
															}
															else
															{
																$response = array("status"=>0,"message"=>"insufficient funds");
																goto app_end;
															}
														}
														else
														{
															$response = array("status"=>0,"message"=>"Error: you cannot offset your own account.");
															goto app_end;
														}
														
														debit:
														if($n)
														{
															$insert_od = mysql_insert_id();
															$xml = '<?xml version="1.0"?> 
															 <Request> 
																<Parameter> 
																	<Method>PaymentNotification</Method> 
																	<Phone>'.$api->format_number($data_object['user_phone']).'</Phone> 
																	<AmountPaid>'.$data_object['amount'].'</AmountPaid> 
																	<Reference>'.$float_type.'-'.$insert_od.'</Reference> 
																	<TimeStamp>'.date("Ymdhis").'</TimeStamp>
																	<LoanID>'.(isset($data_object['loan']) ? $data_object['loan'] : "").'</LoanID> 
																	<ReturnFormat>JSON</ReturnFormat> 
																	</Parameter> 
															</Request>';
														    $json = $api->post($xml, 'https://tango.tambula.net/paygate/payment');
												
												try
												{
													$object = json_decode($json, true);
													if($object['Parameter']['Status'] == '1')
													{
														$response = array("status"=>1,"message"=>"Payment was succesfully logged: ".$object['Parameter']['Info']);
													}
													else
													{
														//Rollback
													 	if($float_type == 'agent')
														{
															mysql_query("insert into user_float (`user`, amount, `comments`, date) values ('".$user_id."', '".mysql_real_escape_string($data_object['amount'])."', 'TRANSACTION REVERSAL', '".date("Y-m-d H:i:s")."')");
														}
														else
														{
															mysql_query("insert into customer_float (`customer`, loan, amount, remarks, transact_date, ref) values ('".$data_object['customer_id']."', '".$data_object['loan']."', '".mysql_real_escape_string($data_object['amount'])."', 'TRANSACTION REVERSAL', '".date("Y-m-d H:i:s")."', '".sha1($user_id.$api->format_number($data_object['user_phone']).$data_object['amount'].$data_object['description'].date("Y-m-d H:i:s"))."')");	
														}
														
														$response = array("status"=>0,"message"=>"payment couldn't be logged: ".$json);
														goto app_end;
													}
												} 
												catch(Exception $solo)
												{
													$response = array("status"=>0,"message"=>$solo->getMessage());
												}
											}
														else
														{
															$response = array("status"=>0,"message"=>"Possible duplicate transaction: ".mysql_error());
														}
													}
													else
													{
														$response = array("status"=>0,"message"=>"Invalid customer phone number.");
													}
										
											}
											else
											{
												switch($mode)
												{
													case 'mobile_money': $ref = sha1(date("Y-m-d H:i:s"));
																		 $url = 'https://tango.tambula.net/beyonic/mmPayment.php?phone='.$data_object['user_phone'].'&amount='.$data_object['amount'].'&transact_id='.$ref;
																		 $mm_ref = $api->sendRequest($url);

																		if($mm_ref != "" && is_numeric($mm_ref))
																		{
																			//Generate Async Request
																			$request_response = $api->post(json_encode(array("ref"=>$mm_ref, "action"=>"suspense_status","token"=>$data_object['token'],"requestType"=>"async")),$api->fuel_api);

																			$response = array("status"=>1,"message"=>$request_response);	
																		}
																		else
																		{
																			$response = array("status"=>0,"message"=>"Error with mobile money");
																		}
																		break;
													
																		default: $response = array("status"=>0,"message"=>"payment mode is not defined.");
										
												}
											}
											//}
											//else
											//{
												//$response = array("status"=>0,"message"=>"agents are only cleared by admin users.");
											//}
										}
										else
										{
											$response = array("status"=>0,"message"=>"this user cannot be cleared by you.");
										}
									}
									else
									{
										$response = array("status"=>0,"message"=>"invalid user token");
									}
						 		  }
						 		  else
						 		  {
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		  break;
			
			case 'load_fuel_float': if(isset($data_object['user_phone'],$data_object['amount'],$data_object['receipt'],$data_object['receipt_info'],$data_object['token']))
						 		  {
						    		$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
									if($user_id != "")
									{
										
											$n = mysql_query("insert into user_float (`user`, amount, `comments`, float_type, date, signature) values ('".$user_id."', '".mysql_real_escape_string($data_object['amount'])."', '".mysql_real_escape_string($data_object['description'])."', 'fuel', '".date("Y-m-d H:i:s")."', '".sha1($user_id.$api->format_number($data_object['user_phone']).date("Y-m-d H:"))."')");
											if($n)
											{
												$response = array("status"=>1,"message"=>"Fuel float load was succesful");
											}
											else
											{
												$response = array("status"=>0,"message"=>"Possible duplicate transaction: ".mysql_error());
											}
										
									}
									else
									{
										$response = array("status"=>0,"message"=>"invalid user token");
									}
						 		  }
						 		  else
						 		  {
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		  break;
			
			case 'transfer_float': if(isset($data_object['user_phone'],$data_object['amount'],$data_object['token']))
						 		  {
						    		$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
									if($user_id != "")
									{
																				
										if($api->fuel_agent_bal($user_id) >= $data_object['amount'])
										{
											$n = mysql_query("insert into user_float (`user`, amount, `comments`, date, signature) values ('".$user_id."', '-".mysql_real_escape_string($data_object['amount'])."', '".mysql_real_escape_string($data_object['description'])."', '".date("Y-m-d H:i:s")."', '".sha1($user_id.$api->format_number($data_object['user_phone']).$data_object['amount'].$data_object['description'].date("Y-m-d H:"))."')");
											if($n)
											{
												$entry_id = mysql_insert_id();
												$transfer = mysql_query("insert into user_float (`user`, amount, `comments`, date, signature) values ('".$api->get_record("login_user","id","where (phone = '".$api->format_number($data_object['user_phone'])."' or `username` = '".$api->format_number($data_object['user_phone'])."')")."', '".mysql_real_escape_string($data_object['amount'])."', '".mysql_real_escape_string($data_object['description'])."', '".date("Y-m-d H:i:s")."', '".sha1($entry_id.$api->format_number($data_object['user_phone']).$data_object['amount'].$data_object['description'].date("Y-m-d H:"))."')");
												if($transfer)
												{
													$response = array("status"=>1,"message"=>"float transfer was succesful");
												}
												else
												{
													//Rollback
													mysql_query("insert into user_float (`user`, amount, `comments`, date) values ('".$user_id."', '".mysql_real_escape_string($data_object['amount'])."', 'TRANSACTION REVERSAL', '".date("Y-m-d H:i:s").$entry_id."')") or die(mysql_error());
													$response = array("status"=>0,"message"=>"transfer failed. please try again");
												}
											}
											else
											{
												$response = array("status"=>0,"message"=>"Possible duplicate transaction: ".mysql_error());
											}
										}
										else
										{
											$response = array("status"=>0,"message"=>"insufficient funds");
										}
									}
									else
									{
										$response = array("status"=>0,"message"=>"invalid user token");
									}
						 		  }
						 		  else
						 		  {
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		  break;
			case 'query_rider_status': if(isset($data_object['phone'],$data_object['token']))
						 		   	   {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$rider_id = $api->get_record("loan_client","id","where `status` = '1' and phone = '".$api->format_number($data_object['phone'])."'");
												if($rider_id != "")
												{
													$response = array("status"=>1,"user_id"=>$rider_id,"transactions"=>$api->rider_fuel_statement($rider_id));
												}
												else
												{
													$response = array("status"=>0,"message"=>"invalid rider information.");
												}
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		   	   else
						 		   	   {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	   	   break;
			 case 'print_card': if(isset($data_object['customer'],$data_object['token']))
						 		   	   {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$m = mysql_query("update client_qr_code set card_printed = '1' where loan_client = '".mysql_real_escape_string($data_object['customer'])."' order by when_attached desc limit 1");
												if($m && mysql_affected_rows() > 0)
												{
													$response = array("status"=>1,"message"=>"card labelled");
												}
												else
												{
													$response = array("status"=>0,"message"=>"labelling failed");
												}
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		   	   else
						 		   	   {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	   	   break;
			 case 'change_card_status': if(isset($data_object['phone'],$data_object['change_type'],$data_object['token']))
						 		   	   {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$customer_id = $api->get_record("loan_client","id","where phone = '".$api->format_number($data_object['phone'])."' and `status` = '1'");
												$language = $api->get_record("loan_client","language","where id = '".$customer_id."' and `status` = '1'");
												$name = $api->get_record("loan_client","concat(trim(fname),' ',trim(lname))","where id = '".$customer_id."' and `status` = '1'");
												$last_card_update = $api->get_record("client_qr_code","status_change_date","where loan_client = '".$customer_id."' and `status` = '1'");
												
												//$station_list  = $api->get_records("login_user","name","where access_level = 'gas-station' and global_status = '1'");
												/*$station = "";
												foreach($station_list as $item)
												{
													$station .= $item.", ";
												}
												
												$station = substr(trim($station),0,-1);*/
												
												if(trim($customer_id) != "")
												{
													switch($data_object['change_type'])
													{
														case 'activate': $n = mysql_query("update client_qr_code set card_printed = '1', `status` = '1', status_change_date='".date("Y-m-d H:i:s")."' where loan_client = '".$customer_id."' order by when_attached desc limit 1");
																		 if($n && mysql_affected_rows() > 0)
																		 {
																			 //Activated, {date}, {time}, {name}, {stations}
																			 //Send SMS on first time activation
																			 //Send SMS if never activated before
																			 //if(trim($last_card_update) == "")
																			 //{
																			 	switch($language)
																				{
																					case 'eng': $message = $api->get_record("message_template","english","where msg_id = 'activated_card' limit 1");
																								 break;
																				    case 'lug': $message = $api->get_record("message_template","luganda","where msg_id = 'activated_card' limit 1");
																								 break;
																					case 'swa':
																					case 'kinya':
																				}
																				
																			 	$message = str_replace("{name}",$name,$message);
																			 	$message = str_replace("{date}",date("D-jS-M Y"),$message);
																			 	$message = str_replace("{time}",date("g:ia"),$message);
																			 	//$message = str_replace("{stations}",$station,$message);
																			 
																			 	$api->send_sms('TAMBULA',$api->format_number($data_object['phone']),$message);
																			 //}
																			 $response = array("status"=>1,"message"=>"card activated");
																		 }
																		 else
																		 {
																			 $response = array("status"=>0,"message"=>"activation failed");
																		 }
																		 break;
														case 'deactivate': $n = mysql_query("update client_qr_code set `status` = '0', status_change_date='".date("Y-m-d H:i:s")."' where loan_client = '".$customer_id."' order by when_attached desc limit 1");
																		 if($n && mysql_affected_rows() > 0)
																		 {
																			 //Activated, {date}, {time}, {name}, {stations}
																			 if(trim($last_card_update) == "")
																			 {
																			 	$message = $api->get_record("message_template","english","where msg_id = 'deactivated_card' limit 1");
																			 	$message = str_replace("{name}",$name,$message);
																				$message = str_replace("{date}",date("D-jS-M Y"),$message);
																			 	$message = str_replace("{time}",date("g:ia"),$message);
																				 //$message = str_replace("{stations}",$station,$message);
																			 
																			 	$api->send_sms('TAMBULA',$api->format_number($data_object['phone']),$message);
																			 }
																			 $response = array("status"=>1,"message"=>"card deactivated");
																		 }
																		 else
																		 {
																			 $response = array("status"=>0,"message"=>"deactivation failed");
																		 }
																		 break;
														default: $response = array("status"=>0,"message"=>"invalid change type");
																 break;
													}
												}
												else
												{
													$response = array("status"=>0,"message"=>"invalid customer id");
												}
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 				}		
						 		   	   else
						 		   	   {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	   	   break;
			 
			 case '2factor_auth_change': if(isset($data_object['customer'],$data_object['token'],$data_object['status']))
						 		   	   {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$m = mysql_query("update client_qr_code set 2factor_auth = '".mysql_real_escape_string($data_object['status'])."' where loan_client = '".mysql_real_escape_string($data_object['customer'])."' and `status` = '1' order by when_attached desc limit 1");
												if($m && mysql_affected_rows() > 0)
												{
													$response = array("status"=>1,"message"=>"2factor auth has been activated");
												}
												else
												{
													$response = array("status"=>0,"message"=>"2factor auth activation failed");
												}
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		   	   else
						 		   	   {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	   	   break;
			 
			 case 'send_sms': if(isset($data_object['message'], $data_object['token']))
						 	  {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$phones = array();
												
												if(substr_count($data_object['phone'],",") > 0)
												{
													$phoneBits = explode(",",$data_object['phone']);
													foreach($phoneBits as $item)
													{
														$phones[] = $item;
													}
												}
												else
												{
													$phones[] = (isset($data_object['phone']) && trim($data_object['phone']) != "") ? $data_object['phone'] : "";
												}
												if(isset($data_object['group']) && $data_object['group'] != "")
												{
													switch($data_object['group'])
													{
														case 'fuel': $query = mysql_query("select phone from loan_client where id in (select distinct(`client`) from loan where voucher is not null) and `status` = '1'");
																	 if(mysql_num_rows($query) > 0)
																	 {
																		 $temp = mysql_fetch_assoc($query);
																		 do
																		 {
																			 $phones[] = $temp['phone'];
																		 } while($temp = mysql_fetch_assoc($query));
																	 }
																	 break;
														case 'all': $query = mysql_query("select phone from loan_client where `status` = '1'");
																	 if(mysql_num_rows($query) > 0)
																	 {
																		 $temp = mysql_fetch_assoc($query);
																		 do
																		 {
																			 $phones[] = $temp['phone'];
																		 } while($temp = mysql_fetch_assoc($query));
																	 }
																	 break;
														case 'active':
														case 'inactive':
													}
												}
												
												if(sizeof($phones) > 0)
												{
													foreach($phones as $toSend)
													{
														$api->send_sms('TAMBULA',$toSend,$data_object['message']);
													}
													
													$response = array("status"=>1,"message"=>sizeof($phones)." messages sent");
												}
												else
												{
													$response = array("status"=>0,"message"=>"no messages sent");
												}
												
												
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 				}		
						 	  else
						 	  {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						      break; 
			 
			 case '_list_customers': if(isset($data_object['token']))
						 		 	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$customer_query_id = isset($data_object['customer_id']) ? $data_object['customer_id'] : "";
												
												$limit = isset($data_object['results']) ? $data_object['results'] : 50;
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
																																				
												$response = array("status"=>1,"customers"=>array());
												$all_agents = mysql_query("select id, concat(trim(fname),' ',trim(lname)) as `name`, phone from loan_client where `status` = '1'".($access_level != "admin" ? " and reg_user = '".$user_id."'" : " and reg_user is not null").($customer_query_id != "" ? " and id = '".$customer_query_id."'" : "")." order by concat(fname,' ',lname) asc limit ".$limit);
												$all_agents_info = mysql_fetch_assoc($all_agents);
												do
												{
													$response["customers"][] = array("id"=>$all_agents_info['id'],"name"=>$all_agents_info['name'],"phone"=>$all_agents_info['phone']);
												} 
												while ($all_agents_info = mysql_fetch_assoc($all_agents));
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 	else
						 		 	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 	break;
			 
			 case 'send_airtime': //Send Airtime
			 					  if(isset($data_object['token'],$data_object['amount'],$data_object['recipient']))
						 		  {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												//Agent Balance
												$bal = $api->fuel_agent_bal($user_id,array('advance','cash'));
												if($bal >= $data_object['amount'])
												{
													//Africa's Talking
													require_once('../ivr/AfricasTalkingGateway.php');
													// Specify your login credentials
													$username   = "thinvoid";
													$apikey     = "371e87ee68892c350e9b7860c52ce2e74f2d95d6ebe45e4e04b41bb699e2c376";
													$from = "+256312319500";
													$gateway = new AfricasTalkingGateway($username, $apikey);
													
													$recipients = array(array("phoneNumber"=>$data_object['recipient'], "amount"=>"UGX ".$data_object['amount']));
													
												   try
												   {
														$results = $gateway->sendAirtime(json_encode($recipients));
														foreach($results as $result) 
														{
															//Log Request
															$log = mysql_query("insert into airtime_logs (`user`, recipient_phone, amount, discount, `status`, `comments`, log_date) values ('".$user_id."', '".$result->phoneNumber."', '".$data_object['amount']."', '".$result->discount."', '".$result->status."', 'AFRICAS TALKING ID: ".$result->requestId." ERROR MESSAGE: ".mysql_real_escape_string($result->errorMessage)."', '".date("Y-m-d H:i:s")."')");
															if($log && (trim($result->errorMessage) == "" || trim($result->errorMessage) == "None") && ($result->status == "Success" || $result->status == "Sent"))
															{
																//Debit User
																$n = mysql_query("insert into user_float (`user`, amount, `comments`, float_type, `date`, signature) values ('".$user_id."', '-".$data_object['amount']."', 'AIRTIME TOP-UP OF ".$data_object['amount']." TO ".$result->phoneNumber."', 'cash', '".date("Y-m-d H:i:s")."', '".sha1(date("Y-m-d H:i:s"))."')");
																if(!$n)
																{
																	$api->send_email("Tambula Support","support@tambula.net","URGENT: CRITICAL ERROR ALERT","USER ID ".$user_id." JUST SENT AIRTIME OF ".$data_object['amount']." TO ".$result->phoneNumber." AND DEBIT FAILED. PLEASE INVESTIGATE.");
																}
																
																$response = array("status"=>1,"message"=>"Airtime has been sent!");
															}
															else
															{
																$response = array("status"=>0,"message"=>"Error in sending airtime. Please try again later.","error"=>$result->errorMessage);
															}
      												 	}
												   }
												   catch(AfricasTalkingGatewayException $e)
												   {
													   $response = array("status"=>0,"message"=>"An error occurred. Please try again later or contact the administrator.","error"=>$e->getMessage());
												   }
													//Agent Commission
												}
												else
												{
													$response = array("status"=>0,"message"=>"Insufficient funds to purchase airtime of ".number_format($data_object['amount']).". You need at least ".number_format($data_object['amount'] - $bal));
												}
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		  else
						 		  {
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						     	  break;
			 
			 case 'list_msg_templates': if(isset($data_object['token']))
						 		 		{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$response = array("status"=>1,"template"=>array());
												$all_templates = mysql_query("select * from message_template");
												$all_template_info = mysql_fetch_assoc($all_templates);
												do
												{
													$response["template"][] = array("id"=>$all_template_info['msg_id'],"eng"=>$all_template_info['english'],"lug"=>$all_template_info['luganda']);
												} 
												while ($all_template_info = mysql_fetch_assoc($all_templates));
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 		else
						 		 		{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   		}
						     	 		break;
			 
			 case 'add_device': if(isset($data_object['imei'],$data_object['device_id'],$data_object['device_phone'],$data_object['agent'],$data_object['token']))
						 		  		{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "" && $api->get_record("login_user","access_level","where id = '".$user_id."'") == 'admin')
											{
													$go = mysql_query("insert into login_user_device (user, imei, imei_1, imei_2, device_phone, other_info, when_added) values ('".mysql_real_escape_string($data_object['agent'])."', '".mysql_real_escape_string($data_object['device_id'])."', '".mysql_real_escape_string($data_object['imei'])."', '".mysql_real_escape_string($data_object['imei2'])."', '".mysql_real_escape_string($api->format_number($data_object['device_phone']))."', '".mysql_real_escape_string($data_object['other_info'])."', '".mysql_real_escape_string($data_object['when_added'])."')");
													if($go)
													{
														$response = array("status"=>1,"message"=>"device has successfully been added");
													}
													else
													{
														//Update
														$n = mysql_query("update login_user_device set imei='".mysql_real_escape_string($data_object['device_id'])."', imei_1='".mysql_real_escape_string($data_object['imei'])."', imei_2='".mysql_real_escape_string($data_object['imei2'])."', device_phone='".mysql_real_escape_string($api->format_number($data_object['device_phone']))."', other_info='".mysql_real_escape_string($data_object['other_info'])."', when_updated='".date("Y-m-d H:i:s")."' where `user` = '".$data_object['agent']."' order by id desc limit 1");
														if(mysql_affected_rows() > 0)
														{
															$response = array("status"=>1,"message"=>"device has successfully been updated");
														}
														else
														{
															$response = array("status"=>0,"message"=>"device add failed");
														}
														
													}
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 		  		}
						 		  		else
						 		  		{
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		  		break;
			 
			 case 'register_customer':
			 
			 case 'register_rider': if(isset($data_object['name'],$data_object['phone'],$data_object['gender'],$data_object['id_type'],$data_object['pic_id'],$data_object['pic_portrait'],$data_object['dob'],$data_object['location_home'],$data_object['location_work'],$data_object['preferred_language'],$data_object['token']))
						 		  		{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												//User ID Overide
												if(isset($data_object['agent']) && $data_object['agent'] != "" && $api->get_record("login_user","count(*)","where id = '".$data_object['agent']."'") > 0)
												{
													$user_id = $data_object['agent'];
												}
												
												$kin_info = "";
												
												//Kin Information
												if(isset($data_object['kin_name'],$data_object['kin_phone'],$data_object['kin_relationship']))
												{
													$kin_info = $data_object['kin_name']." (".$data_object['kin_relationship'].") - ".$data_object['kin_phone'];
												}
												
												$fname = substr($data_object['name'],0,strpos($data_object['name']," "));
												$lname = substr($data_object['name'],strpos($data_object['name']," "));
												if(trim($fname) != "" && trim($lname) != "")
												{
													//Numbers don't exist anywhere
													if($api->get_record("loan_client","count(*)","where (phone in ('".$api->format_number($data_object['phone'])."','".$api->format_number($data_object['phone2'])."') or phone2 in ('".$api->format_number($data_object['phone'])."','".$api->format_number($data_object['phone2'])."')) and `status` = '1'") == 0)
													{
														$go = mysql_query("insert into loan_client (fname, lname, id_type, id_pic, portrait_pic, phone, phone2, sex, dob, address_1, address_2, language, device_display, info, reg_date, reg_user, activation_site) values ('".mysql_real_escape_string($fname)."', '".mysql_real_escape_string($lname)."', '".mysql_real_escape_string($data_object['id_type'])."', '".mysql_real_escape_string($data_object['pic_id'])."', '".mysql_real_escape_string($data_object['pic_portrait'])."', '".mysql_real_escape_string($api->format_number($data_object['phone']))."', ".(isset($data_object['phone2']) && trim($data_object['phone2']) != "" ? "'".mysql_real_escape_string($api->format_number($data_object['phone2']))."'" : "null").", '".mysql_real_escape_string($data_object['gender'])."', '".mysql_real_escape_string($data_object['dob'])."', '".mysql_real_escape_string($data_object['location_work'])."', '".mysql_real_escape_string($data_object['location_home'])."', '".mysql_real_escape_string($data_object['preferred_language'])."', '".(isset($data_object['bike_reg']) ? mysql_real_escape_string($data_object['bike_reg']) : "")."', '".mysql_real_escape_string($kin_info."Others: ".$data_object['info'])."', '".date("Y-m-d H:i:s")."', '".$user_id."', ".(isset($data_object['activation_site']) && trim($data_object['activation_site']) != "" ? "'".$data_object['activation_site']."'" : "null").")");
														if($go)
														{
															$new_rider = mysql_insert_id();
															//User Stage
															if(isset($data_object['stage_name'],$data_object['chairman_name'],$data_object['chairman_phone'],$data_object['association_name'],$data_object['association_contact']))
															{
																mysql_query("insert into customer_location (customer, stage_name, chairman_name, chairman_phone, association_name, association_contact, when_added) values ('".$new_rider."', '".mysql_real_escape_string($data_object['stage_name'])."', '".mysql_real_escape_string($data_object['chairman_name'])."', '".mysql_real_escape_string($api->format_number($data_object['chairman_phone']))."', '".mysql_real_escape_string($data_object['association_name'])."', '".mysql_real_escape_string($data_object['association_contact'])."', '".date("Y-m-d H:i:s")."')");
															}
														
														if(isset($data_object['product']))
														{
															mysql_query("insert into loan_client_product (customer, product, min_amt, max_amt, when_added) values ('".$new_rider."', '".mysql_real_escape_string($data_object['product'])."', '10000', '10000', '".date("Y-m-d H:i:s")."')");
															
															//Auto Activate User Hack
															if($api->get_record("product","auto_activate_user","where id = '".$data_object['product']."'") == "1")
															{
																//Activate
																mysql_query("update loan_client set `status` = '1' where id = '".$new_rider."'");
															}
														}
														
														//Sign up charge
														/*if(isset($data_object['sign_up_charge']) && $data_object['sign_up_charge'] > 0)
														{
															//Create Loan Entry
															$go2 = mysql_query("insert into loan (client, total_amount, min_amount, start_date, duration, payment_interval, desc_1, created_by, when_added, priority) values ('".$new_rider."', '".$data_object['sign_up_charge']."', '".$data_object['sign_up_charge']."', '".date("Y-m-d H:i:s")."', '1', 'day', 'FUEL SIGNUP FEE', '".$user_id."', '".date("Y-m-d H:i:s")."', '1')");
															//Approve Loan
															if($go2)
															{
																$inserted_id = mysql_insert_id();
																$loan_status = mysql_query("update loan_status set `status` = 'approved' where loan = '".$inserted_id."'");
																if(!$loan_status || mysql_affected_rows() <= 0)
																{
																	@mail("joseph@thinvoid.com","Fuel: Loan Status Update Failed","Please check loan id: ".$inserted_id);
																}
															}
															else
															{
																@mail("joseph@thinvoid.com","Fuel: Loan insert fail","We failed to update the Loan Status customer ".$new_rider." ".mysql_error());
															}
														}*/
														
														//SMS
														$api->fuel_welcome_msg($api->format_number($data_object['phone']),$data_object['name'],$data_object['preferred_language']);
														
														$response = array("status"=>1,"message"=>"rider was succesfully created","user_id"=>$new_rider);
													}
														else
														{
													$response = array("status"=>0,"message"=>mysql_error(),"user_id"=>"");
												}
													}
													else
													{
														$response = array("status"=>0,"message"=>"phone number is already registered","user_id"=>"");
													}
												}
												else
												{
													$response = array("status"=>0,"message"=>"both first name and last name required","user_id"=>"");
												}
										}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 		  		}
						 		  		else
						 		  		{
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		  		break;
										
			  
			  case 'register_credit': if(isset($data_object['customer'],$data_object['product'],$data_object['amount'],$data_object['paid'],$data_object['start_date'],$data_object['duration'],$data_object['agent'],$data_object['duration_type'],$data_object['min_amount'],$data_object['token'],$data_object['comments']))
						 		  		{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												
														$start_date = date("Y-m-d H:i:s", strtotime($data_object['start_date']));
														$amt_owed = $data_object['amount'];
														
																												
														$go = mysql_query("insert into loan (`client`, product, service_provider, total_amount, min_amount, start_date, duration, payment_interval, desc_1, created_by, when_added) values ('".mysql_real_escape_string($data_object['customer'])."', '".mysql_real_escape_string($data_object['product'])."', '".mysql_real_escape_string($data_object['agent'])."', '".mysql_real_escape_string($amt_owed)."', '".mysql_real_escape_string($data_object['min_amount'])."', '".mysql_real_escape_string($start_date)."', '".mysql_real_escape_string($data_object['duration'])."', '".mysql_real_escape_string($data_object['duration_type'])."', '".mysql_real_escape_string($data_object['comments'])."', '".$user_id."', '".date("Y-m-d H:i:s")."')");
														if($go)
														{
															$new_credit_facility = mysql_insert_id();
															
															//Auto Approve Loans with initial payment
															if($data_object['paid'] != "" && $data_object['paid'] > 0)
															{
																//Reduce Agent Float
																if($api->fuel_agent_bal($data_object['agent']) > $data_object['paid'])
																{
																	$userFloat = mysql_query("insert into user_float (`user`, amount, comments, `date`, signature) values ('".$data_object['agent']."', '".$data_object['paid']."', 'INITIAL DEPOSIT', '".date("Y-m-d H:i:s")."', '".sha1('initial'.$data_object['agent'].date("Y-m-d H:i:s"))."')");
																	if($userFloat)
																	{
																		$ref = mysql_insert_id();
																		$pay = mysql_query("insert into payment (loan, client, amount, pay_source, source_ref_1, pay_date) values ('".$new_credit_facility."', '".$data_object['customer']."', '".$data_object['paid']."', 'tambula_app', 'agent-".$ref."', '".$start_date."')");
																		if($pay)
																		{
																			$ref2 = mysql_insert_id();
																			$update = mysql_query("update repayment_breakdown set amt_paid = '".$data_object['paid']."', when_paid = '".$start_date."', payment_ref = '".$ref2."' where loan  = '".$new_credit_facility."' order by next_payment_date desc");
																			
																			if(mysql_affected_rows($update) > 0)
																			{
																				$schedule = mysql_query("insert into repayment_breakdown (loan, balance, next_payment_date) values ('".$new_credit_facility."', '".$api->loan_balance($new_credit_facility)."', DATE_ADD('".$start_date."', INTERVAL ".$data_object['duration']." ".$data_object['duration_type']."))");
																				if($schedule)
																				{
																					//Approve
																					$n = mysql_query("update loan_status set `status` = 'approved' where loan = '".$new_credit_facility."'");
																				}
																			}
																		}
																	}
																}
																//Log Payment
																//Log Repayment Schedule
																
															}
															
															//SMS
															$response = array("status"=>1,"message"=>"","loan_id"=>$new_credit_facility);
														}
														else
														{
																$response = array("status"=>0,"message"=>mysql_error(),"user_id"=>"");
														}
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 		  		}
						 		  		else
						 		  		{
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		  		break;
			
			 case 'register_system_user':  if(isset($data_object['name'],$data_object['phone'],$data_object['email'],$data_object['access_level'],$data_object['timezone'],$data_object['token']))
						 		  		{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
														$pass = (isset($data_object['password']) && trim($data_object['password']) != "") ? trim($data_object['password']) : $api->random_password_numeric(4);
														//$api->send_sms('TAMBULA',$api->format_number($data_object['phone']),'Tambula PIN: '.$pass,'normal');
														
														$go = mysql_query("insert into login_user (`username`, name, phone, email, password, when_added, access_level, other_info, web_status, global_status, timezone) values ('".mysql_real_escape_string($data_object['username'])."', '".mysql_real_escape_string($data_object['name'])."', '".$api->format_number($data_object['phone'])."', ".(isset($data_object['email']) && $data_object['email'] != "" ? "'".mysql_real_escape_string($data_object['email'])."'" : "null").", '".sha1($pass)."', '".date("Y-m-d H:i:s")."', '".mysql_real_escape_string($data_object['access_level'])."', '".mysql_real_escape_string($data_object['other_info'])."', '".$data_object['web_status']."', '1', '".mysql_real_escape_string($data_object['timezone'])."')");
														if($go)
														{
															$response = array("status"=>1,"message"=>"rider was succesfully created","user_id"=>$new_rider);
														}
														else
														{
															$response = array("status"=>0,"message"=>mysql_error(),"user_id"=>"");
														}
													
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 		  		}
						 		  		else
						 		  		{
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		  		break;
			 
			 
			 case 'register_stage': if(isset($data_object['stage_name'],$data_object['chairman_name'],$data_object['chairman_phone'],$data_object['location_lat'],$data_object['location_lng'],$data_object['token']))
						 		  	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$go = mysql_query("insert into landmark (name, icon, lat, lng, info, when_added, who_added) values ('".mysql_real_escape_string($data_object['stage_name'])."', 'https://tango.tambula.net/fuel/icons/stage_icon.png', '".mysql_real_escape_string($data_object['location_lat'])."', '".mysql_real_escape_string($data_object['location_lng'])."', '".mysql_real_escape_string("Chairman: ".$data_object['chairman_name']." - ".$data_object['chairman_phone'])."', '".date("Y-m-d H:i:s")."', '".$user_id."')");
												if($go)
												{
													$response = array("status"=>1,"message"=>"stage was succesfully added.");
												}
												else
												{
													$response = array("status"=>0,"message"=>mysql_error());
												}
												
										}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 		  	}
						 		  	else
						 		  	{
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		  	break;
			 case 'list_sms': if(isset($data_object['token']))
						 		 {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$results = isset($data_object['results']) ? $data_object['results'] : "100";
												
												$response = array("status"=>1,"sms"=>array());
												$all_sms = mysql_query("select * from sms where 1 order by id desc limit ".$results);
												$all_sms_info = mysql_fetch_assoc($all_sms);
												do
												{
													$response["sms"][] = array("id"=>$all_sms_info['id'],"recipient"=>$all_sms_info['recipient'],"message"=>$all_sms_info['message'],"send_date"=>$all_sms_info['send_time']);
													
												} while ($all_sms_info = mysql_fetch_assoc($all_sms));
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		 else
						 		 {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		 }
						     	 break;
			  
			  case 'list_stages': if(isset($data_object['token']))
						 		 {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$response = array("status"=>1,"stages"=>array());
												$all_agents = mysql_query("select * from landmark where icon = 'https://tango.tambula.net/fuel/icons/stage_icon.png' and `status` = '1'");
												$all_agents_info = mysql_fetch_assoc($all_agents);
												do
												{
													$response["stages"][] = array("id"=>$all_agents_info['id'],"name"=>$all_agents_info['name'],"lat"=>$all_agents_info['lat'],"lng"=>$all_agents_info['lng'],"info"=>$all_agents_info['info']);
												} while ($all_agents_info = mysql_fetch_assoc($all_agents));
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		 else
						 		 {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 break;
			 
			 case 'attach_rider': if(isset($data_object['token'],$data_object['attachment_type'],$data_object['user_phone'],$data_object['id']))
						 		 {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$customer_id = $api->get_record("loan_client","id","where phone = '".mysql_real_escape_string($api->format_number($data_object['user_phone']))."' and `status` in ('1','0')");
												switch($data_object['attachment_type'])
												{
													case 'stage': $n = mysql_query("insert into customer_location (customer, location, `description`, when_added) values ('".$customer_id."', '".mysql_real_escape_string($data_object['id'])."', '".date("Y-m-d H:i:s")."')");
																  if($n)
																  {
																	  $response = array("status"=>1,"message"=>"stage attachment successful");
																  }
																  else
																  {
																	  $response = array("status"=>0,"message"=>"stage attachment failed or user was previously attached.");
																  }
																  break;
													case 'qr_code': $qr_code_id = $api->get_record("qr_code","id","where salt = '".sha1(md5(mysql_real_escape_string($data_object['id'])))."'");
																	$n = mysql_query("insert into client_qr_code (loan_client, qr_code, who_attached, when_attached, card_printed) values ('".$customer_id."', '".$qr_code_id."', '".$user_id."', '".date("Y-m-d H:i:s")."', '1')");
																	if($n)
																	{
																		$insertID = mysql_insert_id();
																		//Previous State
																		$status = $api->get_record("client_qr_code","status","where qr_code != '".$qr_code_id."' and loan_client = '".$customer_id."' order by when_attached desc limit 1");

																		if(trim($status) != "")
																		{
																			mysql_query("update client_qr_code set status = '".$status."', status_change_date='".date("Y-m-d H:i:s")."' where id = '".$insertID."'");
																		}
																		
																		mysql_query("update client_qr_code set `status` = '".md5(date("Y-m-d H:i:s"))."' where qr_code != '".$qr_code_id."' and loan_client = '".$customer_id."'");
																		
																		$response = array("status"=>1,"message"=>"code has successfully been attached.");
																	}
																	else
																	{
																		error_log(mysql_error());
																		$response = array("status"=>0,"message"=>"invalid qr code id or client phone entered");
																	}
																	break;
													case 'qr_code_serial': $qr_code_id = $api->get_record("qr_code","id","where serial_no = '".mysql_real_escape_string($data_object['id'])."'");
																		   $n1 = mysql_query("update client_qr_code set `status` = '".md5(date("Y-m-d H:i:s").$customer_id)."' where loan_client = '".$customer_id."' and `status` in ('1','0')");
																		   
																		   $n = mysql_query("insert into client_qr_code (loan_client, qr_code, who_attached, when_attached) values ('".$customer_id."', '".$qr_code_id."', '".$user_id."', '".date("Y-m-d H:i:s")."')");
																		   if($n)
																		   {
																				$insertID = mysql_insert_id();
																				//Previous State
																				$status = $api->get_record("client_qr_code","status","where qr_code != '".$qr_code_id."' and loan_client = '".$customer_id."' order by when_attached desc limit 1");

																				if(trim($status) != "")
																				{
																					mysql_query("update client_qr_code set status = '".$status."', status_change_date='".date("Y-m-d H:i:s")."' where id = '".$insertID."'");
																				}
																				
																				mysql_query("update client_qr_code set `status` = '".md5(date("Y-m-d H:i:s"))."' where qr_code != '".$qr_code_id."' and loan_client = '".$customer_id."'");
																						
																				
																				$response = array("status"=>1,"message"=>"code has successfully been attached.");
																			}
																			else
																			{
																				error_log(mysql_error());
																				$response = array("status"=>0,"message"=>"invalid qr code id or client phone entered: ".mysql_error());
																			}
																			break;
													default: $response = array("status"=>0,"message"=>"invalid attachment type");
															 break;
												}
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		 else
						 		 {
							 			$response = array("status"=>0,"message"=>"An error occurred, please contact Tambula.");
						 		 }
						     	 break;
			 
			 case 'product_stats': if(isset($data_object['token'],$data_object['product_id']))
						 		   {
						    		  $user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
												
												$start_date = isset($data_object['from']) ? $data_object['from'] : date("Y-m-d");
												$end_date = isset($data_object['to']) ? $data_object['to'] : date("Y-m-d");
												
												$response = array("status"=>1,"count"=>"");
												
												$stats = mysql_query("SELECT DISTINCT(user_float.`user`), COUNT(*) FROM payment 
JOIN user_float ON (payment.`source_ref_1` = user_float.`id`) GROUP BY user_float.`user` where user_float.user in (select login_user.id from login_user where login_user.access_level = 'gas-station') ".($access_level == "admin" ? "" : "and user_float.user = '".$user_id."'"));
												
												$stats_info = mysql_fetch_assoc($stats);
												do
												{
													$response["products"][] = array("id"=>$all_products_info['id'],"name"=>$all_products_info['product_name']);
												} while ($stats_info = mysql_fetch_assoc($stats));
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		   else
						 		   {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	   break;
			 
			 case 'list_products': if(isset($data_object['token']))
						 		 {
						    		  $user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
												$api_version = isset($data_object['api_version']) ? $data_object['api_version'] : "";
												$response = array("status"=>1,"products"=>array());
												
												if($api_version == '2.0')
												{
													$all_products = mysql_query("select * from product where ".($access_level == "admin" ? "`status` is not null" : "id in (select product from user_product where ".($access_level == "admin" ? "1" : "station = '".$user_id."'").") or id in (select distinct(product) from loan where `client` in (select id from loan_client where reg_user = '".$user_id."'))")." and `status` = '1' and `version` = '2.0'");
												}
												else
												{
													$all_products = mysql_query("select * from product where ".($access_level == "admin" ? "`status` is not null" : "id in (select product from user_product where ".($access_level == "admin" ? "1" : "station = '".$user_id."'").") or id in (select distinct(product) from loan where `client` in (select id from loan_client where reg_user = '".$user_id."'))")." and `status` = '1' /*and `version` != '2.0'*/");
												}
												
												$all_products_info = mysql_fetch_assoc($all_products);
												do
												{
													$response["products"][] = array("id"=>$all_products_info['id'],"name"=>$all_products_info['product_name']);
												} while ($all_products_info = mysql_fetch_assoc($all_products));
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		 else
						 		 {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 break;
			 
			 case 'vendor_status': if(isset($data_object['token']))
						 		 {
						    		  $user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
												$response = array("status"=>1,"vendor"=>array());
												
												$all_vendors = mysql_query("select * from customer_device where `status` = '1'");
												$all_vendors_info = mysql_fetch_assoc($all_vendors);
												do
												{
													$response["vendor"][] = array("id"=>$all_vendors_info['id'],"customer"=>$all_vendors_info['customer'],"imei"=>$all_vendors_info['imei'],"lat"=>$all_vendors_info['lat'],"lng"=>$all_vendors_info['lng'],"speed"=>$all_vendors_info['speed'],"date"=>$all_vendors_info['date'],"time"=>$all_vendors_info['time'],"action"=>$all_vendors_info['action'],"status"=>$all_vendors_info['status']);
												} while ($all_vendors_info = mysql_fetch_assoc($all_vendors));
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		 else
						 		 {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 break;
			 
			 case 'sale_confirmation': if(isset($data_object['token'],$data_object['loan_id']))
						 			   {
										   $n = mysql_query("update loan set sale_confirm = '1', confirm_date = '".date("Y-m-d H:i:s")."'");
										   $response = array("status"=>1,"message"=>"Thank you for using Tambula.");
									   }
									   else
						 		 	   {
							 			  $response = array("status"=>0,"message"=>"invalid parameters set");
						 		   	   }
						     	 	   break;
			 
			 case 'read_qr_code': if(isset($data_object['token'],$data_object['salt']))
						 		 {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											
											if($user_id != "")
											{
												if($api->get_record("login_user","global_status","where id = '".$user_id."'") == "1")
												{
													$salt = sha1(md5($data_object['salt']));
													
													$customer = $api->get_record("client_qr_code","loan_client","where qr_code = '".$api->get_record("qr_code","id","where `salt` = '".mysql_real_escape_string($salt)."' and `status` = '1'")."' and `status` = '1' and loan_client in (select id from loan_client where `status` = '1')");
													
													if(trim($customer) != "")
													{
														$res = mysql_query("select id, concat(fname,' ',lname) as name, phone from loan_client where id = '".$customer."'");
									  					if($res && mysql_num_rows($res) == 1)
									  					{
										  					$info = mysql_fetch_assoc($res);
										  					//Loans
										  					$loans = mysql_query("SELECT * from voucher where customer = '".$customer."' and repayment is null and id in (select voucher from loan where voucher is not null and id in (select loan from loan_status where `status` = 'approved'))");
										  					$loan_info = mysql_fetch_assoc($loans);
															if(mysql_num_rows($loans) == 0)
															{
																$product_list = array();
																$temp = array();
															
																//User Specific Products
																if(isset($data_object['api_version']) && $data_object['api_version'] == "2.0")
																{
																	$products2 = mysql_query("select product.id as product_id,product.product_name as `product_name`,loan_client_product.min_amt as `min`,loan_client_product.max_amt as `max` from loan_client_product join product on (loan_client_product.product = product.id) where loan_client_product.customer = '".$customer."' and product.status = '1' and product.version = '2.0' order by product.product_name asc");
																}
																else
																{
																	$products2 = mysql_query("select product.id as product_id,product.product_name as `product_name`,loan_client_product.upfront_payment as `collect`,loan_client_product.cost as `cost` from loan_client_product join product on (loan_client_product.product = product.id) where loan_client_product.customer = '".$customer."' and product.status = '1' and product.version != '2.0' order by product.product_name asc");
																}
															
																if(mysql_num_rows($products2) > 0)
																{
																	$rows2 = mysql_fetch_assoc($products2);
																	do
																	{
																		if(!in_array($rows2['product_id'],$temp))
																		{
																			if(isset($data_object['api_version']) && $data_object['api_version'] == "2.0")
																			{
																				$product_list[] = array("product_id"=>$rows2['product_id'],"product_name"=>$rows2['product_name'],"min"=>$rows2['min'],"max"=>$rows2['max']);
																			}
																			else
																			{
																				$product_list[] = array("product_id"=>$rows2['product_id'],"product_name"=>$rows2['product_name'],"upfront_payment"=>($rows2['collect'] == 0 ? "ENTER AMOUNT" : $rows2['collect']));
																			}
																		}																	
																	} while ($rows = mysql_fetch_assoc($products));
															}
															
															//If customer has defined products, there's no need to display the others.
															if(sizeof($product_list) == 0)
															{
																//Station Products
																$products = mysql_query("select product.id as product_id,product.product_name as `product_name`,user_product.upfront_payment as `collect`,user_product.cost as `cost` from user_product join product on (user_product.product = product.id) where user_product.station = '".$user_id."' and product.status = '1' order by product.product_name asc");
																if(mysql_num_rows($products) > 0)
																{
																$rows = mysql_fetch_assoc($products);
																do
																{
																	if(!in_array($rows['product_id'],$temp))
																	{
																		$product_list[] = array("product_id"=>$rows['product_id'],"product_name"=>$rows['product_name'],"upfront_payment"=>($rows['collect'] == 0 ? "ENTER AMOUNT" : $rows['collect']));
																		$temp[] = $rows['product_id'];
																	}
																} 
																while ($rows = mysql_fetch_assoc($products));
															}
															}
															
															$response = array("status"=>1,"message"=>$info['name'],"phone"=>$info['phone'],"auth_token"=>"","products"=>$product_list);
														
															} 
															else
															{
																$diff = abs(strtotime(date("Y-m-d H:i:s")) - strtotime($loan_info['date']));
																 /*//10 Minutes
																 if($diff <= 60*10)
																 {
																	$response = array("status"=>0,"error_code"=>"900","message"=>"THIS CARD WAS SCANNED ".round(abs($diff/60))." MINUTES AGO. PLEASE CHECK PHONE FOR ".$info['name']." (".$info['phone'].") TO SEE IF THEY RECEIVED MESSAGE.");
																 }
																 else*/
																 {											 
																	$response = array("status"=>0,"error_code"=>"200","message"=>"NOT AUTHORISED UNTIL THEY CLEAR PENDING BALANCE.\nDO NOT FUEL ".strtoupper($info['name'])." (".$info['phone'].")\nLast Scan: ".date("D-jS-M Y, g:ia", strtotime($loan_info['date'])),"phone"=>$info['phone'],"amount"=>$api->loan_balance($api->get_record("loan","id","where voucher = '".$loan_info['id']."'")));
																	goto app_end;
																 }
															  }
														  } 
									  					else
									  					{
															$response = array("status"=>0,"error_code"=>"300","message"=>"USER ACCOUNT IS INACTIVE.");
									  					}
														//echo $json; exit;
													}
													else
													{
															$customer = $api->get_record("client_qr_code","loan_client","where qr_code = '".$api->get_record("qr_code","id","where `salt` = '".mysql_real_escape_string($salt)."' and `status` = '1'")."' and `status` = '0' and loan_client in (select id from loan_client where `status` = '1')");
															
															if($customer != "")
															{
																//Null last_change_date
																mysql_query("update client_qr_code set status_change_date = null where loan_client = '".$customer."' order by when_attached desc limit 1");	

																//Card is in need of activation
																$cust_phone = $api->get_record("loan_client","phone","where id = '".$customer."' and `status` = '1'");
																if(substr($cust_phone,0,3) == "256")
																{
																	//Send Activation Call
																	require_once('../ivr/AfricasTalkingGateway.php');
																	$username   = "thinvoid";
																	$apikey     = "371e87ee68892c350e9b7860c52ce2e74f2d95d6ebe45e4e04b41bb699e2c376";
																	$from = "+256312319500";
																	$gateway = new AfricasTalkingGateway($username, $apikey);
																	$gateway->call($from, $cust_phone);
																}
																															
																$response = array("status"=>0,"error_code"=>"00","message"=>"THIS CARD IS NOT ACTIVE!\nTo activate, please advise ".$api->get_record("loan_client","concat(fname,' ',lname)","where id = '".$customer."' and `status` = '1'")." to dial *270*072#, select 1 and then 5 using the phone number (".$cust_phone.") they registered with us.");
															}
															else
															{
																
																if($api->get_record("qr_code","id","where `salt` = '".mysql_real_escape_string($salt)."' and `status` = '1'") != "")
																{
																	$response = array("status"=>0,"error_code"=>"300","message"=>"USER ACCOUNT IS LOCKED.");
																}
																else
																{
																	$response = array("status"=>0,"error_code"=>"999","message"=>"INVALID CARD");
																}
															}
													}
												}
												else
												{
													$response = array("status"=>0,"message"=>"AGENT ACCOUNT IS INACTIVE. PLEASE LOGOUT AND LOGIN AGAIN.");
												}
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 else
						 		 {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 break;
			 case 'use_qr_code':  //Issuing Fuel
			 					  if(isset($data_object['phone'],$data_object['token']))
						 		  {
						    		$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
									
									if(($user_id != "" && $api->get_record("login_user","access_level","where id = '".$user_id."'") == "gas-station") || $user_id == "27")
									{
										$account_debited = "";
										$api_version = isset($data_object['api_version']) ? $data_object['api_version'] : "";
										
										$customer_id = $api->get_record("loan_client","id","where phone = '".$api->format_number($data_object['phone'])."' and `status` = '1'");
										$product_id = (isset($data_object['product_id']) && $data_object['product_id'] != "") ? $data_object['product_id'] : 1;
										
										//Product and User are Aligned
										if($api_version	== "2.0" && $api->get_record("loan_client_product","count(*)","where product = '".$product_id."' and customer = '".$customer_id."' and min_amt <= '".$data_object['amount']."' and max_amt >= '".$data_object['amount']."'") != 1 && $data_object['transaction_type'] == 'credit')
										{
											//Error
											$response = array("status"=>0,"error_code"=>500,"message"=>"Invalid amount tendered or Product / User Mismatch");
											goto app_end;
										}
										
										
										if($api_version	== "2.0")
										{
											//5% of tendered amount
											$upfront_payment = $data_object['amount']*$api->get_record("loan_client","service_charge","where id = '".$customer_id."'");;
											//Extras
										}
										else
										{
											$amount = (isset($data_object['upfront_payment']) && trim($data_object['upfront_payment']) != "") ? $data_object['upfront_payment'] : 500;
										}
										
										if($api_version	== "2.0")
										{
											//New API
											if($api->get_record("voucher","count(*)","where customer = '".$customer_id."' and `status` = 'used' and repayment is null and id in (select voucher from loan where voucher is not null and id in (select loan from loan_status where `status` = 'approved'))") == 0 /*|| $data_object['transaction_type'] == 'cash'*/)
											{
												switch($data_object['transaction_type'])
												{
													case 'credit': //Station has Float?
																   if($api->fuel_agent_bal($user_id,array('fuel')) >= $data_object['amount'])
																   {
																   		credit:
																		//Number of times to prepay for
																		$prepays = $api->get_record("loan_client","prepay_count","where id = '".$customer_id."'");
																		$period_days = $api->get_record("loan_client","prepay_duration_days","where id = '".$customer_id."'");;
																		
																		//Check if Customer has balance on card
																   		if($api->customer_card_bal($customer_id) >= $upfront_payment)
																   		{
																		   //Debit Card
																		   $debitCard = mysql_query("insert into customer_float (customer, station, product, amount, remarks, transact_date, ref) values ('".$customer_id."', '".$user_id."', '".$product_id."', '-".$upfront_payment."', 'CREDIT TRANSACTION', '".date("Y-m-d H:i:s")."', '".sha1($customer_id.date("Y-m-d H:i"))."')");
																		   if($debitCard)
																		   {
																			   $debit_type = 'card';
																			   voucher:
																			   //Voucher Entry
																			   $voucher = $api->generate_voucher();
																			   $voucher = mysql_query("insert into voucher (product, customer, service_provider, voucher, amount, date, status, `comments`, reference) values ('".$product_id."', '".$customer_id."', '".$user_id."', '".($customer_id.' '.$voucher)."', '".$data_object['amount']."', '".date("Y-m-d H:i:s")."', 'used', 'CREDIT', '".sha1($voucher)."')");
																			   if($voucher)
																			   {
																				   $voucher_id = mysql_insert_id();
																				   //Debit Fuel Station Account
																				   $fuelDebit = mysql_query("insert into user_float (`user`, amount, `comments`, float_type, date, signature) values ('".$user_id."', '-".$data_object['amount']."', 'FUEL DEBIT', 'fuel', '".date("Y-m-d H:i:s")."', '".sha1(date("Y-m-d H:i:s"))."')");
																				   if($fuelDebit)
																				   {
																					   $userData = mysql_fetch_assoc(mysql_query("select fname, lname, phone, language from loan_client where id = '".$customer_id."' and status = '1'"));
																				   		$language = $userData['language'];
																				   
																				   		$msg = "";
																				   		//Send SMS to Rider
																				    	switch($language)
																						{
																						case 'eng': $msg = $api->get_record("message_template","english","where msg_id = 'voucher'");
																									break;
																						case 'lug': $msg = $api->get_record("message_template","luganda","where msg_id = 'voucher'");
																									break;
																					}
																					
																						$msg = str_replace("{name}",$userData['fname']." ".$userData['lname'],$msg);
																						$msg = str_replace("{fuel_amt}",number_format($data_object['amount']),$msg);
																						$msg = str_replace("{charge}",$upfront_payment,$msg);
																						$msg = str_replace("{date}",date("D-jS-M Y"),$msg);
																						$msg = str_replace("{time}",date("g:ia"),$msg);
																					
																						$api->send_sms('TAMBULA',$userData['phone'],$msg,'normal');
																						
																						//Please Fuel
																		   		   		$response = array("status"=>1,"amt_to_collect"=>($debit_type == 'station' ? $upfront_payment*$prepays : 0),"loan_id"=>$api->get_record("loan","id","where voucher = '".$voucher_id."'"),"message"=>"NAME: ".$api->get_record("loan_client","concat(fname,' ',lname,' - ',phone)","where id = '".$customer_id."'")." \nFUEL TO ISSUE: ".number_format($data_object['amount'])."\n\nAMOUNT TO COLLECT: ".($debit_type == 'station' ? number_format($upfront_payment*$prepays) : 0)."\n\nPLEASE REMEMBER TO GIVE CUSTOMER TAMBULA STICKER.");
																				   }
																				   else
																				   {
																		   			 mysql_query("update voucher set `status` = 'not-taken' where id = '".$voucher_id."'");
																					 //Error
																		   		 	 $response = array("status"=>0,"error_code"=>500,"message"=>"Failed to debit station account.");
																				   }
																				   
																				   goto app_end;
																			   }
																			   else
																			   { 
																		   		 //Error
																		   		 $response = array("status"=>0,"error_code"=>500,"message"=>"Error in logging new voucher: ".mysql_error());
																			   }
																		   }
																	   	   else
																	   	   {
																		   //Error
																		   $response = array("status"=>0,"error_code"=>500,"message"=>"Error in debiting customer: ".mysql_error());
																	   		}
																   		}
																   		else
																   		{
																			//Collect Money
																			if($api->fuel_agent_bal($user_id) >= ($upfront_payment*$prepays))
																			{
																				$stationDebit = mysql_query("insert into user_float (`user`, amount, `comments`, date, signature) values ('".$user_id."', '-".($upfront_payment*$prepays)."', 'COLLECTED FROM CUSTOMER ".$customer_id."', '".date("Y-m-d H:i:s")."', '".sha1($user_id.date("Y-m-d H:i:"))."')");
																				if($stationDebit)
																				{
																					$ref = 'agent-'.mysql_insert_id();
																					//Log Payment
																					$collection = mysql_query("insert into payment (`client`, amount, transact_fees, pay_source, source_ref_1, pay_date) values ('".$customer_id."', '".($upfront_payment*$prepays)."',0, 'tambula_app', '".$ref."', '".date("Y-m-d H:i:s")."')");
																					if($collection)
																					{
																						$payment_ref = mysql_insert_id();
																						//Load Balance to Card
																						$balance = ($upfront_payment*$prepays) - $upfront_payment;
																						$logBal = mysql_query("insert into customer_float (customer, amount, remarks, valid_until, transact_date, ref) values ('".$customer_id."', '".$balance."', 'PRE-PAYMENT', DATE_ADD('".date("Y-m-d H:i:s")."', INTERVAL ".$period_days." DAY), '".date("Y-m-d H:i:s")."', '".$payment_ref."')");
																						if($logBal)
																						{
																							$debit_type = 'station';
																							goto voucher;
																						}
																						else
																						{
																							//General Error
																	   						$response = array("status"=>0,"error_code"=>"500","message"=>"Error in crediting customer account: ".mysql_error());
																							goto app_end;
																					
																						}
																					}
																					else
																					{
																						//General Error
																	   					$response = array("status"=>0,"error_code"=>"500","message"=>"Error in debiting agent account.");
																						goto app_end;
																					}
																				}
																				else
																				{
																					//General Error
																	   				$response = array("status"=>0,"error_code"=>"500","message"=>"Error in debiting agent account.");
																					goto app_end;
																				}
																			}
																			else
																			{
																				//Insufficient Station Funds
																	   			$response = array("status"=>0,"error_code"=>"700","message"=>"INSUFFICIENT TRANSACTIONAL/COLLECTIONS FLOAT.");
																			}
																   		}
																   }
																   else
																   {
																	   //Mobile Money, Check if Cash exists.
																   	   if($api->get_record("login_user","cash_out","where id = '".$user_id."'") == '1' /*&& $api->beyonic_balance() >= $data_object['amount']*/)
																   	   {
																   	   	 goto credit;
																   	   }
																   	   else
																   	   {
																	   	  //No Station Float
																	   	  $response = array("status"=>0,"error_code"=>"600","message"=>"INSUFFICIENT STATION FLOAT FOR THIS TRANSACTION.");
																	   }
																   }
																   break;
													case 'cash': //Regular Cash Transaction
																 $response = array("status"=>0,"error_code"=>"500","message"=>"DO NOT FUEL.");
																 /*$cashTransaction = mysql_query("insert into loyalty (customer, service_provider, amount, service_date) values ('".$customer_id."', '".$user_id."', '".$data_object['amount']."', '".date("Y-m-d H:i:s")."')");
																 if($cashTransaction)
																 {
																	 $response = array("status"=>1,"message"=>"TRANSACTION WAS SUCCESFULLY CAPTURED.");
																 }
																 else
																 {
																	 $response = array("status"=>0,"error_code"=>"500","message"=>"Please try again later.");
																 }*/
																 break;
													case 'prepaid': //Station Float
																	if($api->fuel_agent_bal($user_id,array('fuel')) >= $data_object['amount'])
																   {
																	   //Check Card
																	   if($api->customer_card_bal($customer_id) >= $data_object['amount'])
																	   {
																		   $debitCard = mysql_query("insert into customer_float (customer, amount, remarks, transact_date, ref) values ('".$customer_id."', '".$data_object['amount']."', 'PREPAID CARD FUELING', '".date("Y-m-d H:i:s")."', '".$payment_ref."')");
																	   }
																	   else
																	   {
																	 		$response = array("status"=>0,"error_code"=>"5000","message"=>"INSUFFICIENT FUNDS ON CUSTOMER ACCOUNT");
																	   }
																   }
																   else
																   {
																	   //No STaiton Float
																	   $response = array("status"=>0,"error_code"=>"600","message"=>"INSUFFICIENT STATION FLOAT FOR THIS TRANSACTION.");
																   }
																   break;
													default: $response = array("status"=>0,"error_code"=>"9000","message"=>"Transaction type is not defined. Acceptable values: credit, cash, prepaid.");
															  break;
																 
												}
												
												goto app_end;
											}
											else
											{
												$response = array("status"=>0,"error_code"=>"200","message"=>"DO NOT FUEL ".$api->get_record("loan_client","concat(fname,' ',lname)","where id = '".$customer_id."'")." REASON: CUSTOMER HAS TO CLEAR PENDING BALANCE FIRST.");
											}
										}
										else
										{
											//Old API
											if($amount > 0)
											{										
												//No pending Fuel Loan
												if($api->get_record("voucher","count(*)","where customer = '".$customer_id."' and `status` = 'used' and repayment is null and id in (select voucher from loan where voucher is not null and id in (select loan from loan_status where `status` = 'approved'))") == 0)
												{
												//Loan Facility
												if($api->fuel_agent_bal($user_id) >= $amount)
												{
												$account_debited = "agent";
												$n = mysql_query("insert into user_float (`user`, amount, `comments`, date, signature) values ('".$user_id."', '-".mysql_real_escape_string($amount)."', 'FUEL VOUCHER', '".date("Y-m-d H:i:s")."', '".sha1($user_id.$api->format_number($data_object['phone']).$amount.'Fuel-Station'.date("Y-m-d H:i"))."')");
												voucher_transaction:
												if($n)
												{
													$insert_od = mysql_insert_id();
													try
													{
														$xml = '<?xml version="1.0"?> 
																 <Request> 
																	<Parameter> 
																		<Method>Voucher</Method> 
																		<Phone>'.$api->format_number($data_object['phone']).'</Phone> 
																		<Agent>'.$user_id.'</Agent>
																		<Product>'.$product_id.'</Product>
																		<AmountPaid>'.$amount.'</AmountPaid> 
																		<Reference>'.$account_debited.'-'.$insert_od.'</Reference> 
																		<TimeStamp>'.date("Ymdhis").'</TimeStamp> 
																		<ReturnFormat>JSON</ReturnFormat> 
																	</Parameter> 
																</Request>';
														$json = $api->post($xml, 'https://tango.tambula.net/paygate/payment');
														$object = json_decode($json, true);
														
														if($object['Parameter']['Status'] == '1')
														{
															if($account_debited == "agent")
															{
																mysql_query("update user_float set `comments` = 'FUEL VOUCHER ".number_format($object['Parameter']['VoucherAmount'])."' where id = '".$insert_od."'");
															}
															else
															{
																mysql_query("update customer_float set `remarks` = 'FUEL VOUCHER ".number_format($object['Parameter']['VoucherAmount'])."' where id = '".$insert_od."'");
															}
															
															$response = array("status"=>1,"message"=>"PLEASE FUEL ".$api->get_record("loan_client","concat(fname,' ',lname)","where phone = '".$api->format_number($data_object['phone'])."' and `status` = '1'")." (".$api->format_number($data_object['phone']).") WITH ".number_format($object['Parameter']['VoucherAmount']).($account_debited == "agent" ? " AND COLLECT ".number_format($amount).".\n-------\nPLEASE REMEMBER TO GIVE THE CUSTOMER A TAMBULA STICKER." : ".\n--------\nPLEASE REMEMBER TO GIVE THE CUSTOMER A TAMBULA STICKER."));
														}
														else
														{
															//Determine whether station or customer account was debited and reverse
															if($account_debited == "agent")
															{
																//Agent
																mysql_query("insert into user_float (`user`, amount, `comments`, date, signature) values ('".$user_id."', '".mysql_real_escape_string($amount)."', 'FAILED TRANSACTION REVERSAL', '".date("Y-m-d H:i:s")."', '".sha1($user_id.$api->format_number($data_object['phone']).$amount.'Fuel-Station'.date("Y-m-d H:i:s"))."')");
															}
															else
															{
																//Customer
																$n = mysql_query("insert into customer_float (`customer`, station, product, amount, `remarks`, transact_date, ref) values ('".$customer_id."', '".$user_id."', '".$product_id."', '".mysql_real_escape_string($amount)."', 'TRANSACTION REVERSAL', '".date("Y-m-d H:i:s")."', '".sha1($user_id.$api->format_number($data_object['phone']).$amount.'Fuel-Station'.date("Y-m-d H:i:s"))."')");
															}
															
															$response = array("status"=>0,"message"=>"DO NOT FUEL ".$api->get_record("loan_client","concat(trim(fname),' ',trim(lname))","where phone = '".$api->format_number($data_object['phone'])."' and `status` = '1'"));
														}
													
												} 
													catch(Exception $solo)
													{
													$response = array("status"=>0,"message"=>$solo->getMessage());
													//Reverse Transaction
													mysql_query("insert into user_float (`user`, amount, `comments`, date, signature) values ('".$user_id."', '".mysql_real_escape_string($amount)."', 'FAILED TRANSACTION REVERSAL', '".date("Y-m-d H:i:s")."', '".sha1($user_id.$api->format_number($data_object['phone']).$amount.'Fuel-Station'.date("Y-m-d H:i:s"))."')");
												}
												}
												else
												{
													$response = array("status"=>0,"message"=>"TRANSACTION ERROR. DO NOT FUEL.");
												}
											}
												else
												{
													//Debit Card Balance
													if($api->customer_card_bal($customer_id) >= $amount)
													{
														$account_debited = "customer";
														$n = mysql_query("insert into customer_float (`customer`, station, product, amount, `remarks`, transact_date, ref) values ('".$customer_id."', '".$user_id."', '".$product_id."', '-".mysql_real_escape_string($amount)."', 'FUEL VOUCHER', '".date("Y-m-d H:i:s")."', '".sha1($user_id.$api->format_number($data_object['phone']).$amount.'Fuel-Station'.date("Y-m-d H:i:s"))."')");
														goto voucher_transaction;
													}
												
													$response = array("status"=>0,"message"=>"THIS ACCOUNT HAS INSUFFICIENT FUNDS");
												}
											}
												else
												{
												//Last Scan
												$diff = abs(strtotime(date("Y-m-d H:i:s")) - strtotime($api->get_record("voucher","date","where customer = '".$customer_id."' and repayment is null and id in (select voucher from loan where voucher is not null and id in (select loan from loan_status where `status` = 'approved')) order by id desc limit 1")));
												if($diff <= 60*5)
											 	{
													$response = array("status"=>0,"message"=>"THIS CARD WAS SCANNED ".round(abs($diff/60))." MINUTES AGO. PLEASE CHECK PHONE FOR CUSTOMER TO SEE IF THEY RECEIVED MESSAGE.");
													goto app_end;
											 	}
												else
												{
													$response = array("status"=>1,"message"=>"DO NOT FUEL.\nIf this is in error, please call 0312319500 and press 0.");
												}											 
											}
											
											}
											else
											{
											if(isset($data_object['card_payment']) && $data_object['card_payment'] > 0)
											{
												//Cash Transaction
												if($api->customer_balance($customer_id) >= $data_object['card_payment'])
												{
													//Log Transaction
													$ref = md5($customer_id.$product_id.$amount.$user_id.date("Y-m-d H:i"));
													$logP = mysql_query("insert into customer_float (customer, station, product, amount, remarks, transact_date, ref) values ('".$customer_id."', '".$user_id."', '".$product_id."', '-".$data_object['card_payment']."', 'CARD TRANSACTION', '".date("Y-m-d H:i:s")."', '".$ref."')");
													if($logP)
													{
														$logPID = mysql_insert_id();
														//Log Payment
														$paymentT = mysql_query("insert into payment (client, amount, pay_source, source_ref_1, pay_date) values ('".$customer_id."', '".$data_object['card_payment']."', 'tambula_app', 'card-".$logPID."', '".date("Y-m-d H:i:s")."')");
														if($paymentT)
														{
															$language = $api->get_record("loan_client","language","where id = '".$customer_id."' and `status` = '1'");
															$customer_name = strtoupper($api->get_record("loan_client","concat(trim(fname),' ',trim(lname))","where id = '".$customer_id."' and `status` = '1'"));
															
															//Send Out Message
															$msg = "";
															switch($language)
															{
																case 'eng': $msg = $api->get_record("message_template","english","where msg_id = 'voucher'");
																break;
																case 'lug': $msg = $api->get_record("message_template","luganda","where msg_id = 'voucher'");
																break;
															}
												
															$msg = str_replace("{name}",$customer_name,$msg);
															$msg = str_replace("{fuel_amt}",number_format($data_object['card_payment']),$msg);
															$msg = str_replace("{date}",date("D-jS-M Y"),$msg);
															$msg = str_replace("{time}",date("g:ia"),$msg);
												
															//Send Second Part of Code User
															$api->send_sms('TAMBULA',$api->format_number($data_object['phone']),$msg,'normal');
															
															//Response to app
															$response = array("status"=>1,"message"=>"PLEASE FUEL ".$customer_name.".\nProduct: ".$api->get_record("product","product_name","where id = '".$product_id."'")." \nAmount: ".number_format($data_object['card_payment'])."\nDate: ".date("D-jS-M Y, g:ia", strtotime(date("Y-m-d H:i:s"))));
														}
														else
														{
															$ref = md5($customer_id.$product_id.$amount.$user_id.date("Y-m-d H:i"));
															//Roll Back
															mysql_query("insert into customer_float (customer, station, product, amount, remarks, transact_date, ref) values ('".$customer_id."', '".$user_id."', '".$product_id."', '".$data_object['card_payment']."', 'TRANSACTION REVERSAL', '".date("Y-m-d H:i:s")."', '".$ref."')");
															$response = array("status"=>0,"message"=>"TRANSACTION ERROR.\nPLEASE TRY AGAIN LATER.");
														}
													}
													else
													{
														$response = array("status"=>0,"message"=>"THIS APPEARS TO BE A DUPLICATE TRANSACTION.\nPLEASE TRY AGAIN SHORTLY.");
													}
												}
												else
												{
													$response = array("status"=>0,"message"=>"CUSTOMER HAS INSUFFICIENT FUNDS FOR THIS TRANSACTION.");
												}
											}
											else
											{
												$response = array("status"=>0,"message"=>"INVALID AMOUNT ENTERED.");
											}
											
										}
										}
									}
									else
									{
										$response = array("status"=>0,"message"=>"invalid user token");
									}
									
						 		  }
						 		  else
						 		  {
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		  break;
			 case 'device_ping': if(isset($data_object['device_id'],$data_object['latitude'],$data_object['longitude'],$data_object['battery_level']))
						 		  {
									 $log_activity = mysql_query("insert into device_ping (device_id, lat, lng, batt_level, when_added) values ('".mysql_real_escape_string($data_object['device_id'])."', '".mysql_real_escape_string($data_object['latitude'])."', '".mysql_real_escape_string($data_object['longitude'])."', '".mysql_real_escape_string($data_object['battery_level'])."', '".date("Y-m-d H:i:s")."')");
									 if($log_activity)
									 {
							 			$response = array("status"=>1,"message"=>"log successful");
									 }
									 else
									 {
							 			$response = array("status"=>0,"message"=>"log failed");
									 }
						 		  }
						 		  else
						 		  {
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		  break;
			 
			 case 'send_activation_call': if(isset($data_object['customer_phone'],$data_object['token']))
						 		  		  {
						    					$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
												
												if($user_id != "")
												{
													$callerNumber = "";
													
													if($data_object['customer_phone'] != "")
													{
														$customer_id = $api->get_record("loan_client","id","where phone = '".mysql_real_escape_string($api->format_number($data_object['customer_phone']))."'");
														if($customer_id != "") { $callerNumber = $api->format_number($data_object['customer_phone']); }
													}
													else
													{
														$customer_id = isset($data_object['customer_id']) ? $data_object['customer_id'] : ""; 
														$callerNumber = $api->get_record("loan_client","phone","where id = '".mysql_real_escape_string($customer_id)."' limit 1");
													}
													
													//Set Card Last Change Date to Null
													mysql_query("update client_qr_code set status_change_date = null where loan_client = '".$customer_id."' order by when_attached desc limit 1");

													//Initiate call back
													require_once('../ivr/AfricasTalkingGateway.php');
													// Specify your login credentials
													$username   = "thinvoid";
													$apikey     = "371e87ee68892c350e9b7860c52ce2e74f2d95d6ebe45e4e04b41bb699e2c376";
													$from = "+256312319500";
													$gateway = new AfricasTalkingGateway($username, $apikey);
													$gateway->call($from, $callerNumber);
													
													$response = array("status"=>1,"message"=>"call sent");
												}
												else
												{
										$response = array("status"=>0,"message"=>"invalid user token");
									}
						 		  		  }
						 		  		  else
						 		  		  {
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		  		  break;
			 
			 case 'cancel_loan': if(isset($data_object['loan_id'],$data_object['comments'],$data_object['token']))
						 		  {
						    		$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
									if($user_id != "" && $api->get_record("login_user","access_level","where id = '".$user_id."'") == "admin")
									{
										$customer_info = $api->get_records("loan_client",array("fname","lname","phone","language"),"where id = '".$api->get_record("loan","client","where id = '".mysql_real_escape_string($data_object['loan_id'])."'")."'");
										
										$loan_approval_status = $api->get_record("loan_status","status","where loan = '".mysql_real_escape_string($data_object['loan_id'])."'");
										if($loan_approval_status != "rejected")
										{										
											//Reject
											$canceL = mysql_query("update loan_status set `status` = 'rejected', `comments`='".mysql_real_escape_string($data_object['comments'])."' where loan = '".mysql_real_escape_string($data_object['loan_id'])."' limit 1");
											if($canceL && mysql_affected_rows() == 1)
											{
												//Notify User
												$message = "";
												switch($customer_info['language'])
												{
													case 'eng': $message = $api->get_record("message_template","english","where msg_id = 'fuel_error'");
													break;
													case 'lug': $message = $api->get_record("message_template","luganda","where msg_id = 'fuel_error'");
													break;
												}
												
												$message = str_replace("{name}",trim($customer_info['fname'])." ".trim($customer_info['lname']),$message);
												$api->send_sms('TAMBULA',$customer_info['phone'],$message,'normal');
												
												$response = array("status"=>1,"message"=>"loan successfully cancelled.");
											}
											else
											{
												$response = array("status"=>0,"message"=>"cancellation failed. please contact admin");
											}
										}
										else
										{
											$response = array("status"=>0,"message"=>"loan was previously cancelled");
										}
									}
									else
									{
										$response = array("status"=>0,"message"=>"invalid user token");
									}
						 		  }
						 		  else
						 		  {
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		  break;
			 case 'generate_qr_codes': if(isset($data_object['token'],$data_object['codes_to_generate']))
			 						   {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												if(is_numeric($data_object['codes_to_generate']) && $data_object['codes_to_generate'] > 0)
												{
													$total = $api->randomize_code_values($data_object['codes_to_generate']);
													$response = array("status"=>1,"message"=>$total." codes generated");
												}
												else
												{
													$response = array("status"=>0,"message"=>"invalid number of codes to generate");
												}
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
									   }
									   else
						 		 	   {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
									   break;
			 case 'list_qr_codes': if(isset($data_object['token']))
						 		   {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
												$response = array("status"=>1,"codes"=>array());
												$all_codes = mysql_query("select client_qr_code.2factor_auth as `sms_auth`, client_qr_code.status as `card_status`, qr_code.file_location as `file_name`, qr_code.serial_no as `serial_number`, concat(loan_client.fname,' ',loan_client.lname) as `client_name`, client_qr_code.loan_client as `client_id`, client_qr_code.when_attached as `attachment_date`, client_qr_code.card_printed as `print_status`, client_qr_code.status_change_date as `status_change_date` from qr_code join client_qr_code on (qr_code.id = client_qr_code.qr_code) left join loan_client on (client_qr_code.loan_client = loan_client.id) where ".($access_level == "admin" ? "client_qr_code.qr_code is not null" : "loan_client.reg_user = '".$user_id."'")." and client_qr_code.status in ('1','0') order by qr_code.when_added desc");
												$all_codes_info = mysql_fetch_assoc($all_codes);
												do
												{
													$response["codes"][] = array(
													"card_status"=>$all_codes_info['card_status'],
													"file_name"=>$all_codes_info['file_name'],
													"serial_number"=>$all_codes_info['serial_number'],
													"client_name"=>$all_codes_info['client_name'],
													"client_id"=>$all_codes_info['client_id'],
													"attachment_date"=>$all_codes_info['attachment_date'],
													"card_balance"=>$api->customer_card_bal($all_codes_info['client_id']),
													"sms_auth"=>($all_codes_info['sms_auth'] == "1" ? "YES" : "NO"),
													"card_rules"=>"",
													"print_status"=>$all_codes_info['print_status'],
													"status_change_date"=>$all_codes_info['status_change_date']);
												} while ($all_codes_info = mysql_fetch_assoc($all_codes));
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		   else
						 		   {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
								   break;
			 
			 case 'list_qr_codes_0': if(isset($data_object['token']))
						 		   {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$response = array("status"=>1,"codes"=>array());
												$all_codes = mysql_query("select qr_code.file_location as `file_name`, qr_code.serial_no as `serial_number` from qr_code where qr_code.id not in (select client_qr_code.qr_code from client_qr_code where 1) and qr_code.serial_no like '2017%' and qr_code.file_location is not null /*and instant_card = 'no'*/");
												$all_codes_info = mysql_fetch_assoc($all_codes);
												do
												{
													$response["codes"][] = array("file_name"=>$all_codes_info['file_name'],"serial_number"=>$all_codes_info['serial_number']);
												} while ($all_codes_info = mysql_fetch_assoc($all_codes));
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		   else
						 		   {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
								   break;
								   
			 case 'list_qr_codes_instant': if(isset($data_object['token']))
						 		   		   {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "" && $api->get_record("login_user","access_level","where id = '".$user_id."' and global_status = '1'") == 'admin')
											{
												$limit = isset($data_object['results']) ? $data_object['results'] : 1000;
												$response = array("status"=>1,"codes"=>array());
												$all_codes = mysql_query("select qr_code.file_location as `file_name`, qr_code.serial_no as `serial_number`, qr_code.when_added from qr_code where qr_code.id not in (select client_qr_code.qr_code from client_qr_code where 1) and qr_code.file_location is not null /*and instant_card = 'yes'*/ order by qr_code.id desc limit ".$limit);
												$all_codes_info = mysql_fetch_assoc($all_codes);
												do
												{
													$response["codes"][] = array("file_name"=>$all_codes_info['file_name'],"serial_number"=>$all_codes_info['serial_number'],"date"=>$all_codes_info['when_added']);
												} while ($all_codes_info = mysql_fetch_assoc($all_codes));
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		   		   else
						 		   		   {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
								   		   break;
			 
			 case 'list_agents': if(isset($data_object['token']))
						 		 {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
												$response = array("status"=>1,"agents"=>array());
												$all_agents = mysql_query("select id, name, phone from login_user where global_status = '1'".($access_level == "admin" ? "" : " and id = '".$user_id."'"));
												$all_agents_info = mysql_fetch_assoc($all_agents);
												do
												{
													$response["agents"][] = array("id"=>$all_agents_info['id'],"name"=>$all_agents_info['name'],"phone"=>$all_agents_info['phone']);
												} while ($all_agents_info = mysql_fetch_assoc($all_agents));
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		 else
						 		 {
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		 }
						     	 break;
								 
			 case 'active_sites': if(isset($data_object['token']))
						 		 {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
												$response = array("status"=>1,"active_sites"=>array());
												$all_agents = mysql_query("select id, name from login_user where global_status = '1' and (access_level = 'gas-station' or access_level = 'normal')");
												$all_agents_info = mysql_fetch_assoc($all_agents);
												do
												{
													$response["active_sites"][] = array("id"=>$all_agents_info['id'],"name"=>$all_agents_info['name']);
												} while ($all_agents_info = mysql_fetch_assoc($all_agents));
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		 else
						 		 {
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		 }
						     	 break;
			 
			 case 'active_partners': if(isset($data_object['token']))
						 		 	 {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$response = array("status"=>1,"agents"=>array());
												$all_agents = mysql_query("select id, name, phone from login_user where global_status = '1' and access_level = 'gas-station'");
												$all_agents_info = mysql_fetch_assoc($all_agents);
												do
												{
													$response["agents"][] = array("id"=>$all_agents_info['id'],"name"=>$all_agents_info['name'],"phone"=>$all_agents_info['phone']);
												} while ($all_agents_info = mysql_fetch_assoc($all_agents));
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		 	 else
						 		 	 {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		 	 }
						     	 	break;
									
			 case 'query_async':  if(isset($data_object['token'],$data_object['async_id']))
						 		 	 {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		 	 else
						 		 	 {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		 	 }
						     	 	break;
			 case 'active_agents': if(isset($data_object['token']))
						 		 	 {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$response = array("status"=>1,"agents"=>array());
												$all_agents = mysql_query("select id, name, phone from login_user where global_status = '1' and access_level not in ('gas-station','corporate')");
												$all_agents_info = mysql_fetch_assoc($all_agents);
												do
												{
													$response["agents"][] = array("id"=>$all_agents_info['id'],"name"=>$all_agents_info['name'],"phone"=>$all_agents_info['phone']);
												} while ($all_agents_info = mysql_fetch_assoc($all_agents));
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		 	 else
						 		 	 {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		 	 }
						     	 	break;
			 
			 case 'statistics': if(isset($data_object['token']))
						 		 {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												//add date constraints to starts
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
												
												$avg_repayment_period = round($api->get_record("analysis","AVG(analysis.`Repayment Period`)","WHERE analysis.`Repayment Period` IS NOT NULL".((isset($data_object['partner']) && $data_object['partner'] != "") ? " and analysis.`Service Provider ID` = '".$data_object['partner']."'" : "").((isset($data_object['filter']) && $data_object['filter'] == 'normal') ? ((isset($_POST['agent']) && $_POST['agent'] != "") ? " and analysis.`Client ID` in (select loan_client.id from loan_client where loan_client.reg_user = '".$_POST['agent']."')" : "") : "").(isset($data_object['from'],$data_object['to']) ? " and substr(`Payment Date`,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : " and substr(`Payment Date`,1,10) <= '".date("Y-m-d")."'")));
												
												$all_outstanding = $api->get_record("analysis","count(*)","WHERE analysis.`Payment Date` IS NULL".(isset($data_object['from'],$data_object['to']) ? " and substr(`Fuel Date`,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : " and substr(`Fuel Date`,1,10) <= '".date("Y-m-d")."'").((isset($data_object['partner']) && $data_object['partner'] != "") ? " and analysis.`Service Provider ID` = '".$data_object['partner']."'" : "").((isset($data_object['filter']) && $data_object['filter'] == 'normal') ? ((isset($_POST['agent']) && $_POST['agent'] != "") ? " and analysis.`Client ID` in (select loan_client.id from loan_client where loan_client.reg_user = '".$_POST['agent']."')" : "") : ""));
												
												$no_fuel_defaults = $api->get_record("analysis","count(*)","WHERE analysis.`Payment Date` IS NULL AND DATEDIFF('".(isset($data_object['from'],$data_object['to']) ? $data_object['to'] : date("Y-m-d"))."', substr(analysis.`Fuel Date`,1,10)) > ".$avg_repayment_period.(isset($data_object['from'],$data_object['to']) ? " and substr(`Fuel Date`,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : " and substr(`Fuel Date`,1,10) <= '".date("Y-m-d")."'").((isset($data_object['partner']) && $data_object['partner'] != "") ? " and analysis.`Service Provider ID` = '".$data_object['partner']."'" : "").((isset($data_object['filter']) && $data_object['filter'] == 'normal') ? ((isset($_POST['agent']) && $_POST['agent'] != "") ? " and analysis.`Client ID` in (select loan_client.id from loan_client where loan_client.reg_user = '".$_POST['agent']."')" : "") : ""));
												
												$response = array(
												"status"=>1,
												"parameters"=>array(
													"users_today"=>$api->get_record("loan_client","count(*)","where ".(isset($data_object['from'],$data_object['to']) ? "substr(reg_date,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : "substr(reg_date,1,10) = '".date("Y-m-d")."'").($access_level != "admin" ? " and reg_user = '".$user_id."'" : "").((isset($data_object['filter']) && $data_object['filter'] == 'normal') ? ((isset($_POST['agent']) && $_POST['agent'] != "") ? " and loan_client.reg_user = '".$_POST['agent']."'" : "") : "")),
													"payments_today_count"=>$api->get_record("payment","count(*)","where ".(isset($data_object['from'],$data_object['to']) ? "substr(pay_date,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : "substr(pay_date,1,10) = '".date("Y-m-d")."'").($access_level != "admin" ? " and `client` in (select id from loan_client where reg_user = '".$user_id."')" : "").((isset($data_object['partner']) && $data_object['partner'] != "") ? " and payment.`loan` in (select loan.id from loan where loan.service_provider = '".$data_object['partner']."')" : "").((isset($data_object['filter']) && $data_object['filter'] == 'normal') ? ((isset($_POST['agent']) && $_POST['agent'] != "") ? " and payment.client in (select loan_client.id from loan_client where loan_client.reg_user = '".$_POST['agent']."')" : "") : "")),
													"payments_today_sum"=>$api->get_record("payment","sum(amount)","where ".(isset($data_object['from'],$data_object['to']) ? "substr(pay_date,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : "substr(pay_date,1,10) = '".date("Y-m-d")."'").($access_level != "admin" ? " and `client` in (select id from loan_client where reg_user = '".$user_id."')" : "").((isset($data_object['partner']) && $data_object['partner'] != "") ? " and payment.`loan` in (select loan.id from loan where loan.service_provider = '".$data_object['partner']."')" : "").((isset($data_object['filter']) && $data_object['filter'] == 'normal') ? ((isset($_POST['agent']) && $_POST['agent'] != "") ? " and payment.client in (select loan_client.id from loan_client where loan_client.reg_user = '".$_POST['agent']."')" : "") : "")),
													"active_loans"=>$api->get_record("loan_summary","count(*)","where loan_amount - amount_paid > 0 and loan_status = 'approved'".($access_level != "admin" ? " and loan_client in (select id from loan_id where reg_user = '".$user_id."')" : "").((isset($data_object['partner']) && $data_object['partner'] != "") ? " and loan_id in (select loan.id from loan where loan.service_provider = '".$data_object['partner']."')" : "").((isset($data_object['filter']) && $data_object['filter'] == 'normal') ? ((isset($_POST['agent']) && $_POST['agent'] != "") ? " and loan_id in (select loan.id from loan where loan.client in (select loan_client.id from loan_client where loan_client.reg_user = '".$_POST['agent']."'))" : "") : "")),
													"fuel_overdue_payments"=>$no_fuel_defaults,
													"other_payments_overdue"=>$api->get_record("repayment_breakdown","count(*)","where ".(isset($data_object['from'],$data_object['to']) ? "substr(next_payment_date,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : "substr(next_payment_date,1,10) < '".date("Y-m-d")."'").((isset($data_object['filter']) && $data_object['filter'] == 'normal') ? ((isset($_POST['agent']) && $_POST['agent'] != "") ? " and loan in (select loan.id from loan where loan.client in (select loan_client.id from loan_client where loan_client.reg_user = '".$_POST['agent']."'))" : "") : "")." and payment_ref is null and loan in (select loan from loan_status where `status` = 'approved')".($access_level != "admin" ? " and `loan` in (select id from loan where `client` in (select id from loan_client where reg_user = '".$user_id."') and voucher is null)" : " and `loan` in (select id from loan where voucher is null)")),
													"fuel_default_rate"=>round(($no_fuel_defaults/$all_outstanding)*100,2),
													"vouchers_today"=>$api->get_record("voucher","count(*)","where ".(isset($data_object['from'],$data_object['to']) ? "substr(date,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : "substr(date,1,10) = '".date("Y-m-d")."'")." and `status` = 'used' and voucher.id in (select loan.voucher from loan where ".(isset($data_object['from'],$data_object['to']) ? "substr(loan.start_date,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : "substr(loan.start_date,1,10) = '".date("Y-m-d")."'")." and loan.id in (select loan_status.loan from loan_status where loan_status.status = 'approved')".((isset($data_object['filter']) && $data_object['filter'] == 'normal') ? ((isset($_POST['agent']) && $_POST['agent'] != "") ? " and loan.client in (select loan_client.id from loan_client where loan_client.reg_user = '".$_POST['agent']."')" : "") : "").")".($access_level != "admin" ? " and customer in (select id from loan_client where reg_user = '".$user_id."')" : "").((isset($data_object['partner']) && $data_object['partner'] != "") ? " and voucher.`id` in (select loan.voucher from loan where loan.service_provider = '".$data_object['partner']."')" : "")),
													"vouchers_new"=>$api->get_record("voucher","count(*)","where ".(isset($data_object['from'],$data_object['to']) ? "substr(date,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : "substr(date,1,10) = '".date("Y-m-d")."'")." and `status` = 'used' and voucher.customer in (SELECT DISTINCT(voucher.`customer`) FROM voucher GROUP BY customer HAVING COUNT(*) = 1) and voucher.id in (select loan.voucher from loan where ".(isset($data_object['from'],$data_object['to']) ? "substr(loan.start_date,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : "substr(loan.start_date,1,10) = '".date("Y-m-d")."'").((isset($data_object['partner']) && $data_object['partner'] != "") ? " and loan.`service_provider` = '".$data_object['partner']."'" : "")." and loan.id in (select loan_status.loan from loan_status where loan_status.status = 'approved')".((isset($data_object['filter']) && $data_object['filter'] == 'normal') ? ((isset($_POST['agent']) && $_POST['agent'] != "") ? " and loan.client in (select loan_client.id from loan_client where loan_client.reg_user = '".$_POST['agent']."')" : "") : "").")".($access_level != "admin" ? " and customer in (select id from loan_client where reg_user = '".$user_id."')" : "")),
													"vouchers_old"=>$api->get_record("voucher","count(*)","where ".(isset($data_object['from'],$data_object['to']) ? "substr(date,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : "substr(date,1,10) = '".date("Y-m-d")."'")." and `status` = 'used' and voucher.customer in (SELECT DISTINCT(voucher.`customer`) FROM voucher GROUP BY customer HAVING COUNT(*) > 1) and voucher.id in (select loan.voucher from loan where ".(isset($data_object['from'],$data_object['to']) ? "substr(loan.start_date,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : "substr(loan.start_date,1,10) = '".date("Y-m-d")."'").((isset($data_object['partner']) && $data_object['partner'] != "") ? " and loan.`service_provider` = '".$data_object['partner']."'" : "")." and loan.id in (select loan_status.loan from loan_status where loan_status.status = 'approved')".((isset($data_object['filter']) && $data_object['filter'] == 'normal') ? ((isset($_POST['agent']) && $_POST['agent'] != "") ? " and loan.client in (select loan_client.id from loan_client where loan_client.reg_user = '".$_POST['agent']."')" : "") : "").")".($access_level != "admin" ? " and customer in (select id from loan_client where reg_user = '".$user_id."')" : "")),
													"avg_repayment_period"=>$avg_repayment_period,
													"all_fuel_outstanding"=>$all_outstanding));
											}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
						 			}
						 		 else
						 		 {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 break;
			 case 'update_rider': 
			 case 'update_customer': if($data_object['token'])
						 		  	 {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												//User ID Overide
												if(isset($data_object['agent']) && $data_object['agent'] != "" && $api->get_record("login_user","count(*)","where id = '".$data_object['agent']."'") > 0)
												{
													$user_id = $data_object['agent'];
												}
												
												$fname = substr($data_object['name'],0,strpos($data_object['name']," "));
												$lname = substr($data_object['name'],strpos($data_object['name']," "));
												$activated = ($data_object['active'] == "1" && $data_object['active'] != $api->get_record("loan_client","status","where id = '".$data_object['customer_id']."' and `status` = '1'") ? true : false);
																								
												if(true/*trim($fname) != "" && trim($lname) != ""*/)
												{
													$go = mysql_query("update loan_client set fname='".mysql_real_escape_string($fname)."', lname='".mysql_real_escape_string($lname)."', id_type='".mysql_real_escape_string($data_object['id_type'])."',".(isset($data_object['pic_id']) && trim($data_object['pic_id']) != "" ? "id_pic='".mysql_real_escape_string($data_object['pic_id'])."',":"")." ".(isset($data_object['pic_portrait']) && trim($data_object['pic_portrait']) != "" ? "portrait_pic='".$data_object['pic_portrait']."'," : "")." phone='".mysql_real_escape_string($api->format_number($data_object['phone']))."', phone2=".(isset($data_object['phone2']) && trim($data_object['phone2']) != "" ? "'".mysql_real_escape_string($data_object['phone2'])."'" : "null").", sex='".mysql_real_escape_string($data_object['gender'])."', dob='".mysql_real_escape_string($data_object['dob'])."', address_1='".mysql_real_escape_string($data_object['location_work'])."', address_2='".mysql_real_escape_string($data_object['location_home'])."', language='".mysql_real_escape_string($data_object['preferred_language'])."', device_display='".(isset($data_object['bike_reg']) ? mysql_real_escape_string($data_object['bike_reg']) : "")."', info='".mysql_real_escape_string($data_object['info'])."', update_date='".date("Y-m-d H:i:s")."', reg_user='".$user_id."', status='".mysql_real_escape_string($data_object['active'])."' where id = '".$data_object['customer_id']."'");
													if($go)
													{
														//Sign up charge
														if(isset($data_object['sign_up_charge']) && $data_object['sign_up_charge'] > 0)
														{
															//Create Loan Entry
															$go2 = mysql_query("insert into loan (client, total_amount, min_amount, start_date, duration, payment_interval, desc_1, created_by, when_added, priority) values ('".$data_object['customer_id']."', '".$data_object['sign_up_charge']."', '".$data_object['sign_up_charge']."', '".date("Y-m-d H:i:s")."', '1', 'day', 'FUEL SIGNUP FEE', '".$user_id."', '".date("Y-m-d H:i:s")."', '1')");
															//Approve Loan
															if($go2)
															{
																$inserted_id = mysql_insert_id();
																$loan_status = mysql_query("update loan_status set `status` = 'approved' where loan = '".$inserted_id."'");
																if(!$loan_status || mysql_affected_rows() <= 0)
																{
																	@mail("joseph@thinvoid.com","Fuel: Loan Status Update Failed","Please check loan id: ".$inserted_id);
																}
															}
															else
															{
																@mail("joseph@thinvoid.com","Fuel: Loan insert fail","We failed to update the Loan Status customer ".$new_rider." ".mysql_error());
															}
														}
														
														//Product
														if(isset($data_object['product']))
														{
															if(is_array($data_object['product']))
															{
																foreach($data_object['product'] as $item)
																{
																	mysql_query("insert into loan_client_product (customer, product, `min_amt`, max_amt, when_added) values ('".$data_object['customer_id']."', '".mysql_real_escape_string($item)."', 10000, 10000, '".date("Y-m-d H:i:s")."')");
																	//Tracker Hack
																	if($item == 8)
																	{
																		//Activate
																		mysql_query("update loan_client set `status` = '1' where id = '".$data_object['customer_id']."'");
																	}
															}
															}
															else
															{
																$bits = explode(",",$data_object['product']);
																foreach($bits as $item)
																{
																mysql_query("insert into loan_client_product (customer, product, `upfront_payment`, cost, when_added) values ('".$data_object['customer_id']."', '".mysql_real_escape_string($item)."', ".$api->get_record("product","default_initial","where id = '".mysql_real_escape_string($item)."'").", ".$api->get_record("product","default_later","where id = '".mysql_real_escape_string($item)."'").", '".date("Y-m-d H:i:s")."')");
																//Tracker Hack
																if($item == 8)
																{
																	//Activate
																	mysql_query("update loan_client set `status` = '1' where id = '".$data_object['customer_id']."'");
																}
															}
															}
															
															mysql_query("delete from loan_client_product where customer = '".$data_object['customer_id']."' and product not in (".$data_object['product'].")");
														}
														
														//SMS
														if($activated)
														{
															$api->fuel_activataion_msg($api->format_number($data_object['phone']),$data_object['name'],$data_object['preferred_language']);
														}
														
														$response = array("status"=>1,"message"=>"customer was succesfully updated","user_id"=>$data_object['customer_id']);
													}
													else
													{
													$response = array("status"=>0,"message"=>mysql_error(),"user_id"=>"");
												}
												}
												else
												{
													$response = array("status"=>0,"message"=>"both first name and last name required","user_id"=>"");
												}
										}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 		  		}
						 		  	 else
						 		  	 {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  	 }
						 		  	 break;
			 case 'list_customers': if(isset($data_object['token']))
						 		 	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$customer_query_id = isset($data_object['customer_id']) ? $data_object['customer_id'] : "";
												
												$limit = isset($data_object['results']) ? $data_object['results'] : 50;
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
																																				
												$response = array("status"=>1,"customers"=>array());
												$all_agents = mysql_query("select id, fname, lname, phone, phone2, sex, dob, address_1, address_2, language, device_display, info, reg_date, reg_user, status from loan_client".($access_level != "admin" ? " where reg_user = '".$user_id."'" : " where reg_user is not null").($customer_query_id != "" ? " and id = '".$customer_query_id."'" : "")." and loan_client.id in (select loan.client from loan where voucher is not null) order by concat(fname,' ',lname) asc limit ".$limit);
												$all_agents_info = mysql_fetch_assoc($all_agents);
												do
												{
													$response["customers"][] = array("id"=>$all_agents_info['id'],"name"=>$all_agents_info['fname']." ".$all_agents_info['lname'],"phone"=>$all_agents_info['phone'],"phone2"=>$all_agents_info['phone2'],"sex"=>$all_agents_info['sex'],"dob"=>$all_agents_info['dob'],"address_1"=>$all_agents_info['address_1'],"address_2"=>$all_agents_info['address_2'],"language"=>$all_agents_info['language'],"device_display"=>$all_agents_info['device_display'],"qr_code_id"=>$api->get_record("client_qr_code","qr_code","where loan_client = '".$all_agents_info['id']."'"),"qr_code_serial"=>$api->get_record("qr_code","serial_no","where id = '".$api->get_record("client_qr_code","qr_code","where loan_client = '".$all_agents_info['id']."'")."'"),"fuel_card_printed"=>$api->get_record("client_qr_code","card_printed","where loan_client = '".$all_agents_info['id']."'"),"info"=>$all_agents_info['info'],"reg_date"=>$all_agents_info['reg_date'],"reg_user_id"=>$all_agents_info['reg_user'],"reg_user_name"=>$api->get_record("login_user","name","where id = '".$all_agents_info['reg_user']."'"),"active"=>$all_agents_info['status'],"all_fuel_taken"=>$api->get_record("loan","count(*)","where `client` = '".$all_agents_info['id']."' and voucher is not null and id in (select loan_status.loan from loan_status where loan_status.status = 'approved')"),"fuel_taken_this_month"=>$api->get_record("loan","count(*)","where `client` = '".$all_agents_info['id']."' and voucher is not null and substr(when_added,1,7) = '".date("Y-m")."' and id in (select loan_status.loan from loan_status where loan_status.status = 'approved')"));
												} 
												while ($all_agents_info = mysql_fetch_assoc($all_agents));
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 	else
						 		 	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		    }
						     	 	break;
			 
			 case 'customer_info': if(isset($data_object['token'],$data_object['customer_phone']))
						 		 	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$all_agents = mysql_query("select * from loan_client where phone = '".$api->format_number($data_object['customer_phone'])."' and `status` = '1'");
												if(mysql_num_rows($all_agents) > 0)
												{
													$all_agents_info = mysql_fetch_assoc($all_agents);
													$response = array("status"=>1,"customer"=>array());
													
													do
													{
														$response["customer"][] = array(
															"id"=>$all_agents_info['id'],
															"name"=>trim(trim($all_agents_info['fname'])." ".trim($all_agents_info['lname'])),
															"phone"=>$all_agents_info['phone'],
															"phone2"=>$all_agents_info['phone2'],
															"language"=>$all_agents_info['language'],
															"card_status"=>$api->get_record("client_qr_code","status","where loan_client = '".$all_agents_info['id']."' and status in ('1','0') order by when_attached desc limit 1"),
															"card_balance"=>$api->customer_card_bal($all_agents_info['id']),
															"card_last_change"=>$api->get_record("client_qr_code","status_change_date","where loan_client = '".$all_agents_info['id']."' and status in ('1','0') order by when_attached desc limit 1"),
															"active_loans"=>$api->user_loans($all_agents_info['id']));
													} 
													while ($all_agents_info = mysql_fetch_assoc($all_agents));
												}
												else
												{
													$response = array("status"=>0,"message"=>"unrecognised customer");
												}
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 	else
						 		 	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 	break;
			 
			 case 'list_customers2': if(isset($data_object['token']))
						 		 	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$customer_query_id = isset($data_object['customer_id']) ? $data_object['customer_id'] : "";
												
												$limit = isset($data_object['results']) ? $data_object['results'] : 50;
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
																																				
												$response = array("status"=>1,"customers"=>array());
												$all_agents = mysql_query("select id, fname, lname, phone, phone2, sex, dob, address_1, address_2, language, device_display, info, reg_date, reg_user, status from loan_client".($access_level != "admin" ? " where reg_user = '".$user_id."'" : " where reg_user is not null").($customer_query_id != "" ? " and id = '".$customer_query_id."'" : "")." and loan_client.id not in (select loan.client from loan where voucher is not null) order by concat(fname,' ',lname) asc limit ".$limit);
												$all_agents_info = mysql_fetch_assoc($all_agents);
												do
												{
													$response["customers"][] = array("id"=>$all_agents_info['id'],"name"=>$all_agents_info['fname']." ".$all_agents_info['lname'],"phone"=>$all_agents_info['phone'],"phone2"=>$all_agents_info['phone2'],"sex"=>$all_agents_info['sex'],"dob"=>$all_agents_info['dob'],"address_1"=>$all_agents_info['address_1'],"address_2"=>$all_agents_info['address_2'],"language"=>$all_agents_info['language'],"device_display"=>$all_agents_info['device_display'],"qr_code_id"=>$api->get_record("client_qr_code","qr_code","where loan_client = '".$all_agents_info['id']."'"),"qr_code_serial"=>$api->get_record("qr_code","serial_no","where id = '".$api->get_record("client_qr_code","qr_code","where loan_client = '".$all_agents_info['id']."'")."'"),"fuel_card_printed"=>$api->get_record("client_qr_code","card_printed","where loan_client = '".$all_agents_info['id']."'"),"info"=>$all_agents_info['info'],"reg_date"=>$all_agents_info['reg_date'],"reg_user_id"=>$all_agents_info['reg_user'],"reg_user_name"=>$api->get_record("login_user","name","where id = '".$all_agents_info['reg_user']."'"),"active"=>$all_agents_info['status'],"all_fuel_taken"=>$api->get_record("loan","count(*)","where `client` = '".$all_agents_info['id']."' and voucher is not null and id in (select loan_status.loan from loan_status where loan_status.status = 'approved')"),"fuel_taken_this_month"=>$api->get_record("loan","count(*)","where `client` = '".$all_agents_info['id']."' and voucher is not null and substr(when_added,1,7) = '".date("Y-m")."' and id in (select loan_status.loan from loan_status where loan_status.status = 'approved')"));
												} 
												while ($all_agents_info = mysql_fetch_assoc($all_agents));
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 	else
						 		 	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 	break;
									
			 case 'list_customers3': if(isset($data_object['token']))
						 		 	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$customer_query_id = isset($data_object['customer_id']) ? $data_object['customer_id'] : "";
												
												$limit = isset($data_object['results']) ? $data_object['results'] : 50;
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
																																				
												$response = array("status"=>1,"customers"=>array());
												$all_agents = mysql_query("select id, fname, lname, phone, phone2, sex, dob, address_1, address_2, language, device_display, info, reg_date, reg_user, status from loan_client".($access_level != "admin" ? " where reg_user = '".$user_id."'" : " where reg_user is not null").($customer_query_id != "" ? " and id = '".$customer_query_id."'" : "")." order by concat(fname,' ',lname) asc limit ".$limit);
												$all_agents_info = mysql_fetch_assoc($all_agents);
												do
												{
													$response["customers"][] = array(
													"id"=>$all_agents_info['id'],
													"name"=>$all_agents_info['fname']." ".$all_agents_info['lname'],
													"phone"=>$all_agents_info['phone'],
													"phone2"=>$all_agents_info['phone2'],
													"sex"=>$all_agents_info['sex'],
													"dob"=>$all_agents_info['dob'],
													"address_1"=>$all_agents_info['address_1'],
													"address_2"=>$all_agents_info['address_2'],
													"language"=>$all_agents_info['language'],
													"device_display"=>$all_agents_info['device_display'],
													"qr_code_id"=>$api->get_record("client_qr_code","qr_code","where loan_client = '".$all_agents_info['id']."' and status in ('1','0')"),
													"qr_code_serial"=>$api->get_record("qr_code","serial_no","where id = '".$api->get_record("client_qr_code","qr_code","where loan_client = '".$all_agents_info['id']."' and status in ('1','0')")."' and status = '1'"),
													"fuel_card_printed"=>$api->get_record("client_qr_code","card_printed","where loan_client = '".$all_agents_info['id']."'"),
													"info"=>$all_agents_info['info'],"reg_date"=>$all_agents_info['reg_date'],
													"reg_user_id"=>$all_agents_info['reg_user'],
													"reg_user_name"=>$api->get_record("login_user","name","where id = '".$all_agents_info['reg_user']."'"),
													"active"=>$all_agents_info['status'],
													"all_fuel_taken"=>$api->get_record("loan","count(*)","where `client` = '".$all_agents_info['id']."' and voucher is not null and id in (select loan_status.loan from loan_status where loan_status.status = 'approved')"),
													"fuel_taken_this_month"=>$api->get_record("loan","count(*)","where `client` = '".$all_agents_info['id']."' and voucher is not null and substr(when_added,1,7) = '".date("Y-m")."' and id in (select loan_status.loan from loan_status where loan_status.status = 'approved')"),
													"product_id"=>$api->user_product_info($all_agents_info['id']));
												} while ($all_agents_info = mysql_fetch_assoc($all_agents));
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 	else
						 		 	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 	break;
																								
			 case 'cash_out_request_modify': if(isset($data_object['token'],$data_object['request_id'],$data_object['new_status']))
						 		 			{
												$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
												if($user_id != "")
												{
													if($api->get_record("login_user","access_level","where id = '".$user_id."'") == "admin")
													{
														$n = mysql_query("update cash_out set `status` = '".mysql_real_escape_string($data_object['new_status'])."', modified_by = '".$user_id."', modify_date = '".date("Y-m-d H:i:s")."' where id = '".mysql_real_escape_string($data_object['request_id'])."' limit 1");
														if($n && mysql_affected_rows() > 0)
														{
															//Move Fuel Deposit
															$request_info = $api->get_records2("cash_out",array("user","collections","amount","log_date"),"where id = '".mysql_real_escape_string($data_object['request_id'])."'");
															
															if(($request_info[0]['collections']+$request_info[0]['amount']) > 0)
															{
																$float = mysql_query("insert into user_float (`user`, amount, `comments`, float_type, `date`, signature) values ('".$request_info[0]['user']."', '".($request_info[0]['collections']+$request_info[0]['amount'])."', 'FLOAT TRANSFER', 'fuel', '".$request_info[0]['log_date']."', '".sha1(date("Y-m-d H:i:s"))."')");
																if($float)
																{
																	$response = array("status"=>1,"message"=>"cash out succesful");
																}
																else
																{
																	//Reverse
																	mysql_query("update cash_out set `status` = 'failed', modified_by = '".$user_id."', modify_date = '".date("Y-m-d Hi:s")."' where id = '".mysql_real_escape_string($data_object['request_id'])."' limit 1");
																	$response = array("status"=>0,"message"=>"cash out failed.");
																}
															}
															else
															{
																$response = array("status"=>1,"message"=>"nothing to cashout.");
															}
														}
														else
														{
															$response = array("status"=>0,"message"=>"request update failed.");	
														}
											}
													else
													{
														$response = array("status"=>0,"message"=>"invalid access level");
													}
												}
												else
												{
													$response = array("status"=>0,"message"=>"invalid user token");
												}
									}
											else
											{
												$response = array("status"=>0,"message"=>"invalid parameters set");
											}
											break;
			 
			 case 'cash_out_request': if(isset($data_object['token']))
						 		 	{
						    			$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
										if($user_id != "")
										{
											if($api->fuel_agent_bal($user_id, array('fuel')) < 0)
											{
												//Commodity deficit
												$deficit = $api->fuel_agent_bal($user_id,array('fuel'));
												//Last Cashout Date
												$cash_out_date = $api->get_record("user_float","max(date)","where float_type = 'fuel' and `user` = '".$user_id."' and amount > 0");

												//Collections after last cashout
												$collections = 0;
												$collections += $api->get_record("user_float","sum(abs(amount))","where `user` = '".$user_id."' and `date` > '".$cash_out_date."' and float_type in ('cash','advance') and amount < 0");
												
												$go = mysql_query("insert into cash_out (user, deficit, collections, amount, log_date) values ('".$user_id."', '".abs($deficit)."', '".$collections."', '".(abs($deficit) - $collections)."', '".date("Y-m-d H:i:s")."')");
												if($go)
												{
													//Send Email on Request
													$response = array("status"=>1,"message"=>"Request has been logged. Please wait for confirmation message.");
												}
												else
												{
													$response = array("status"=>0,"message"=>mysql_error());
												}
												
												$response = array("status"=>1,"message"=>"Please wait for SMS confirmation.");
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid cashout request");
											}
										}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
									}
									else
									{
										$response = array("status"=>0,"message"=>"invalid parameters set");
									}
									break;
			 
			 case 'cash_out_status': if(isset($data_object['token']))
						 		 	{
						    			$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
										if($user_id != "")
										{
											if($api->fuel_agent_bal($user_id, array('fuel')) < 0)
											{
												$agent_name = $api->get_record("login_user","name","where id = '".$user_id."'");
												$agent_phone = $api->get_record("login_user","phone","where id = '".$user_id."'");
												
												//Commodity deficit
												$deficit = $api->fuel_agent_bal($user_id,array('fuel'));
												//Last Cashout Date
												$cash_out_date = $api->get_record("user_float","max(date)","where float_type = 'fuel' and `user` = '".$user_id."' and amount > 0");

												//Collections after last cashout
												$collections = 0;
												$collections += $api->get_record("user_float","sum(abs(amount))","where `user` = '".$user_id."' and `date` > '".$cash_out_date."' and float_type in ('cash','advance') and amount < 0");
												
												//Text User
												$message = $agent_name."\r\nLast Cash Out Date: ".$cash_out_date."\r\nAmount Owed: ".number_format($deficit)."\r\nCollections: ".number_format($collections)."\r\nAmount to pay: ".number_format(abs($deficit) - $collections)."Receipt Amount: ".number_format($deficit);
												
												$api->send_sms('TAMBULA',$agent_phone,$message,'normal');
												
												$response = array("status"=>1,"deficit"=>$deficit,"last_cash_out_date"=>$cash_out_date,"collections"=>$collections,"cash_out_amount"=>(abs($deficit) - $collections));
												
											}
											else
											{
												$response = array("status"=>0,"message"=>"Nothing to cashout at the moment.");
											}
										}
										else
										{
											$response = array("status"=>0,"message"=>"invalid user token");
										}
									}
									else
									{
										$response = array("status"=>0,"message"=>"invalid parameters set");
									}
									break;

			 case 'log_ussd_transaction':  if(isset($data_object['token']))
						 		 		   {
						    					$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
												if($user_id != "")
												{
													$log_ssd = mysql_query("insert into ussd_transaction (transac_id, ussd_date, phone, service_code, request_string, dialed_code, ussd_response, payment_token, system_date) values ('".mysql_real_escape_string($data_object['transac_id'])."', '".mysql_real_escape_string($data_object['ussd_date'])."', '".mysql_real_escape_string($data_object['phone'])."', '".mysql_real_escape_string($data_object['service_code'])."', '".mysql_real_escape_string($data_object['request_string'])."', '".mysql_real_escape_string($data_object['dialed_code'])."', '".mysql_real_escape_string($data_object['ussd_response'])."', '".mysql_real_escape_string($data_object['payment_token'])."', '".mysql_real_escape_string($data_object['system_date'])."')");
												if($log_ssd)
												{
													//Return User Information
													//Loan Status, Amount to Pay, Others.
													//Rider Info
													$get_info = mysql_query("select * from loan_client where phone = '".$api->format_number($data_object['phone'])."' and `status` = '1' limit 1");
													if(mysql_num_rows($get_info) == 1)
													{
														$user_info = mysql_fetch_assoc($get_info);
														$balance_query = mysql_query("SELECT loan.id AS loan_id, loan.`client`, loan.voucher as `fuel_voucher`, repayment_breakdown.`balance`, loan.`min_amount`, repayment_breakdown.`next_payment_date` FROM loan JOIN repayment_breakdown ON (loan.`id` = repayment_breakdown.`loan`) WHERE loan.`client` = '".$user_info['id']."' and loan.id in (select loan from loan_status where `status` = 'approved') AND repayment_breakdown.`when_paid` IS NULL ORDER BY loan.priority DESC, next_payment_date ASC limit 1");
															if(mysql_num_rows($balance_query) > 0)
															{
																$agent = $api->get_record("login_user", "id", "where phone = '".$api->format_number($data_object['phone'])."'");
																$balance_info = mysql_fetch_assoc($balance_query);
																$response = array("status"=>1,"parameters"=>array("name"=>trim($user_info['fname'])." ".trim($user_info['lname']),"language"=>$user_info['language'],"device_display"=>$user_info['device_display'],"status"=>$user_info['status'],"amount_due"=>$balance_info['min_amount'],"loan_id"=>$balance_info['loan_id'],"voucher"=>$balance_info['fuel_voucher'],"emmergency_contact"=>$api->get_records2("loan_client_contact",array("name","phone"),"where customer = '".$user_info['id']."' and status='1' order by id asc")),"message"=>(trim($agent) != "" ? "-3" : ""));
															}
															else
															{
																$agent = $api->get_record("login_user", "id", "where phone = '".$api->format_number($data_object['phone'])."'");
																$response = array("status"=>1,"parameters"=>array("name"=>trim($user_info['fname'])." ".trim($user_info['lname']),"language"=>$user_info['language'],"device_display"=>$user_info['device_display'],"status"=>$user_info['status'],"amount_due"=>0,"loan_id"=>"","emmergency_contact"=>$api->get_records2("loan_client_contact",array("name","phone"),"where customer = '".$user_info['id']."' and status='1' order by id asc")),"message"=>(trim($agent) != "" ? "-3" : ""));
															}
														 
													}
													else
													{
														$agent = $api->get_record("login_user", "id", "where phone = '".$api->format_number($data_object['phone'])."'");
														$response = array("status"=>0,"message"=>(trim($agent) != "" ? "-3" : "-2"));
													}
												}
												else
												{
													$response = array("status"=>0,"message"=>"-1");
												}
											}
												else
												{
													$response = array("status"=>0,"message"=>"invalid user token");
												}
						 					}
						 		 		   else
						 		 		   {
							 					$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   		   }
						     	 		   break;

			 case 'log_payment_intent': if(isset($data_object['token']))
						 		 		{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$log_ssd = mysql_query("insert into suspense (sender, amount, pay_source, source_ref_1, `comments`, pay_date) values ('".mysql_real_escape_string($data_object['sender'])."', '".mysql_real_escape_string($data_object['amount'])."', '".mysql_real_escape_string($data_object['pay_source'])."', '".mysql_real_escape_string($data_object['source_ref_1'])."', '".mysql_real_escape_string($data_object['comments'])."', '".mysql_real_escape_string($data_object['pay_date'])."')");
												if($log_ssd)
												{
													$reference = mysql_insert_id();
													$response = array("status"=>1,"message"=>$reference);
												}
												else
												{
													$response = array("status"=>0,"message"=>"-1");
												}
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 		   else
						 		 		   {
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 		   break;
			 case 'customer_care': if(isset($data_object['user_phone'],$data_object['token']))
						 		   {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
													if($api->get_record("customer_query","count(*)","where phone = '".mysql_real_escape_string($data_object['user_phone'])."' and resolution is null") == 0)
													{
														$go = mysql_query("insert into customer_query (phone, issue_desc, date) values ('".mysql_real_escape_string($data_object['user_phone'])."', '".(isset($data_object['message']) ? mysql_real_escape_string($data_object['message']) : "CALLED HOTLINE FOR HELP/ADDITIONAL INFORMATION")."', '".date("Y-m-d H:i:s")."')");
														if($go)
														{
														
														$response = array("status"=>1,"message"=>"issue has been logged");
													}
														else
														{
														$response = array("status"=>0,"message"=>mysql_error());
													}
													}
													else
													{
														//Previous Unresolved Query Exists, Update Hits
														$n = mysql_query("update customer_query set severity = severity+1, when_updated = '".date("Y-m-d H:i:s")."' where phone = '".mysql_real_escape_string($data_object['user_phone'])."' and resolution is null limit 1");
														if($n)
														{
															$response = array("status"=>1,"message"=>"issue has been logged");
														}
														else
														{
															$response = array("status"=>0,"message"=>mysql_error());
														}
													}
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 		  		}
						 		   else
						 		   {
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		   break;
			 case 'list_unresolved': if(isset($data_object['token']))
						 		 	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$query_id = isset($data_object['query_id']) ? $data_object['query_id'] : "";
												
												$limit = isset($data_object['results']) ? $data_object['results'] : 50;
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
																																				
												$response = array("status"=>1,"customers"=>array());
												$all_agents = mysql_query("select * from customer_query where".($query_id != "" ? " id = '".$query_id."' " : " resolution is null ")."order by when_posted desc limit ".$limit);
												$all_agents_info = mysql_fetch_assoc($all_agents);
												do
												{
													$response["customers"][] = array("id"=>$all_agents_info['id'],"phone"=>$all_agents_info['phone'],"name"=>$api->get_record("loan_client","concat(fname,' ',lname)","where phone = '".$all_agents_info['phone']."' and `status` = '1'"),"date"=>$all_agents_info['date'],"issue"=>$all_agents_info['issue_desc'],"times_called"=>$all_agents_info['severity'],"resolution"=>$all_agents_info['resolution'],"date"=>$all_agents_info['date']);
												} while ($all_agents_info = mysql_fetch_assoc($all_agents));
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 	else
						 		 	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 	break;
			 case 'resolve_query': if($data_object['token'])
						 		  	 {
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
													$go = mysql_query("update customer_query set issue_desc = '".mysql_real_escape_string($data_object['issue_desc'])."', resolution='".mysql_real_escape_string($data_object['resolution'])."', when_posted='".date("Y-m-d H:i:s")."', who_attended='".$user_id."' where id = '".$data_object['query_id']."'");
													if($go)
													{
														$response = array("status"=>1,"message"=>"update was succesful");
													}
													else
													{
													$response = array("status"=>0,"message"=>mysql_error(),"user_id"=>"");
												}
												
												
												
										}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 		  		}
						 		  	 else
						 		  	 {
							 		$response = array("status"=>0,"message"=>"invalid parameters set");
						 		  }
						 		  	 break;
			 case 'list_payments': if(isset($data_object['token']))
						 		 	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$customer_query_id = isset($data_object['customer_id']) ? $data_object['customer_id'] : "";
												
												$limit = isset($data_object['results']) ? $data_object['results'] : 50;
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
																																				
												$response = array("status"=>1,"payments"=>array());
												$all_payments = mysql_query("select * from payment".(isset($data_object['from'],$data_object['to']) ? " where substr(pay_date,1,10) between '".$data_object['from']."' and '".$data_object['to']."' " : " where substr(pay_date,1,10) = '".date("Y-m-d")."'").($access_level != "admin" ? " and `client` in (select id from loan_client where reg_user = '".$user_id."' and `status` = '1')" : " and `client` is not null").($customer_query_id != "" ? " and id = '".$customer_query_id."'" : "")." order by id desc limit ".$limit);
												$payment_info = mysql_fetch_assoc($all_payments);
												do
												{
													$response["payments"][] = array("id"=>$payment_info['id'],"loan_id"=>$payment_info['loan'],"client_id"=>$payment_info['client'],"client_name"=>$api->get_record("loan_client","concat(fname,' ',lname)","where id = '".$payment_info['client']."'"),"amount"=>$payment_info['amount'],"transact_fees"=>$payment_info['transact_fees'],"payment_source"=>$payment_info['pay_source'],"reference1"=>$payment_info['source_ref_1'],"reference2"=>$payment_info['source_ref_2'],"who_paid_id"=>($payment_info['pay_source'] == "tambula_app" ? $api->get_record("user_float","user","where id = '".$payment_info['source_ref_1']."'") : ""),"who_paid_name"=>($payment_info['pay_source'] == "tambula_app" ? $api->get_record("login_user","name","where id = '".$api->get_record("user_float","user","where id = '".$payment_info['source_ref_1']."'")."'") : ""),"location"=>($payment_info['pay_source'] == "interswitch" ? $api->get_record("landmark","name","where activity_ref = '".$payment_info['source_ref_2']."'") : ($payment_info['pay_source'] == "tambula_app" ? $api->get_record("login_user","name","where id = '".$api->get_record("user_float","user","where id  = '".mysql_real_escape_string($payment_info['source_ref_1'])."'")."'") : "-")),"date"=>$payment_info['pay_date']);
												} while ($payment_info = mysql_fetch_assoc($all_payments));
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 	else
						 		 	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 	break;
			 					//Fuel
			 case 'list_loans': if(isset($data_object['token']))
						 		 	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$limit = isset($data_object['results']) ? $data_object['results'] : 50;
												$loan_id = isset($data_object['loan_id']) ? $data_object['loan_id'] : "";
												
												//Search
												$search = isset($data_object['search']) ? $data_object['search'] : "";
												
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
																																				
												$response = array("status"=>1,"loans"=>array());
												$all_loans = mysql_query("select * from loan where id is not null ".(trim($loan_id) == "" ? "and `created_by` is not null" : "and id = '".mysql_real_escape_string($loan_id)."'").($access_level != "admin" ? " and `client` in (select id from loan_client where reg_user = '".$user_id."')" : " and `created_by` is not null").(isset($data_object['from'],$data_object['to']) ? " and substr(start_date,1,10) between '".$data_object['from']."' and '".$data_object['to']."' " : "").(trim($search) != "" ? " and loan.client in (select loan_client.id from loan_client where concat(loan_client.fname,' ',loan_client.lname) like '%".$search."%' or phone like '%".$search."%' or phone2 like '%".$search."%' or device_display like '%".$search."%' or info like '%".$search."%') " : "").((isset($data_object['product']) && $data_object['product'] != "") ? " and product = '".$data_object['product']."'" : "")." and id in (select loan from loan_status where `status` = 'approved') order by id desc limit ".$limit);
												$loan_info = mysql_fetch_assoc($all_loans);
												do
												{
													/*<option value="complete">Completed</option>
                                          <option value="pending">Pending</option>
                                          <option value="all" selected>All Loans</option>
                                          <option value="rejected">Rejected Loans</option>
                                          <option value="cancelled">Cancelled Loans</option*/
													if($data_object['loan_status'] == 'complete' && $api->loan_balance($loan_info['id']) != 0)
													{
														continue;	
													}
													
													if($data_object['loan_status'] == 'pending' && $api->get_record("loan_status","status","where loan = '".$loan_info['id']."'") != 'pending')
													{
														continue;	
													}
													
													if($data_object['loan_status'] == 'rejected' && $api->get_record("loan_status","status","where loan = '".$loan_info['id']."'") != 'rejected')
													{
														continue;	
													}
													
													if($data_object['loan_status'] == 'cancelled' && $api->get_record("loan_status","status","where loan = '".$loan_info['id']."'") != 'cancelled')
													{
														continue;	
													}
													
													if($data_object['loan_status'] == 'paying' && $api->loan_balance($loan_info['id']) == 0)
													{
														continue;
													}
													
													
													$total_paid = $api->get_record("payment","sum(amount)","where loan = '".$loan_info['id']."'");
													$sp = $api->get_record("login_user","name","where id = '".$loan_info['service_provider']."'");
													$response["loans"][] = array(
													"loan_id"=>$loan_info['id'],
													"client_id"=>$loan_info['client'],
													"client_name"=>$api->get_record("loan_client","concat(trim(fname),' ',trim(lname))","where id = '".$loan_info['client']."'"),
													"client_phone"=>$api->get_record("loan_client","phone","where id = '".$loan_info['client']."'"),
													"client_location"=>$api->get_record("loan_client","concat(address_1,' ',address_2)","where id = '".$loan_info['client']."'"),
													"card_balance"=>$api->customer_card_bal($loan_info['client']),
													"product"=>$api->get_record("product","product_name","where id = '".$loan_info['product']."'"),
													"total_amount"=>($total_paid == 0 ? ($loan_info['total_amount']-$total_paid) : $loan_info['total_amount']),
													"min_amount"=>$loan_info['min_amount'],
													"start_date"=>$loan_info['start_date'],
													"duration"=>$loan_info['duration'],
													"payment_interval"=>$loan_info['payment_interval'],
													"voucher"=>$loan_info['voucher'],
													"service_provider"=>$sp,
													"description"=>$loan_info['desc_1'],
													"when_added"=>$loan_info['when_added'],
													"approval_status"=>$api->get_record("loan_status","status","where loan = '".$loan_info['id']."'"),"total_paid"=>$total_paid,"last_payment_date"=>$api->get_record("payment","pay_date","where loan = '".$loan_info['id']."' order by id desc limit 1"),"next_payment_date"=>$api->get_record("repayment_breakdown","next_payment_date","where loan = '".$loan_info['id']."' and payment_ref is null order by next_payment_date desc limit 1"),"owed_todate"=>($loan_info['voucher'] == "" ? $api->loan_balance($loan_info['id'],$loan_info['start_date'],date("Y-m-d")) : ""));
												} 
												while ($loan_info = mysql_fetch_assoc($all_loans));
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 	else
						 		 	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 	break;
									
			 case 'list_loans2': if(isset($data_object['token']))
						 		 	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$limit = isset($data_object['results']) ? $data_object['results'] : 50;
												$loan_id = isset($data_object['loan_id']) ? $data_object['loan_id'] : "";
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
																																				
												$response = array("status"=>1,"loans"=>array());
												$all_loans = mysql_query("select * from loan where (voucher is null or voucher = '') ".(trim($loan_id) == "" ? "and `created_by` is not null" : "and id = '".mysql_real_escape_string($loan_id)."'").($access_level != "admin" ? " and `client` in (select id from loan_client where reg_user = '".$user_id."')" : " and `created_by` is not null")." and id in (select loan from loan_status where `status` = 'approved') order by id desc limit ".$limit);
												$loan_info = mysql_fetch_assoc($all_loans);
												do
												{
													$response["loans"][] = array("loan_id"=>$loan_info['id'],"client_id"=>$loan_info['client'],"client_name"=>$api->get_record("loan_client","concat(trim(fname),' ',trim(lname))","where id = '".$loan_info['client']."'"),"client_phone"=>$api->get_record("loan_client","phone","where id = '".$loan_info['client']."'"),"total_amount"=>$loan_info['total_amount'],"min_amount"=>$loan_info['min_amount'],"start_date"=>$loan_info['start_date'],"duration"=>$loan_info['duration'],"payment_interval"=>$loan_info['payment_interval'],"voucher"=>$loan_info['voucher'],"description"=>$loan_info['desc_1'],"when_added"=>$loan_info['when_added'],"approval_status"=>$api->get_record("loan_status","status","where loan = '".$loan_info['id']."'"),"total_paid"=>$api->get_record("payment","sum(amount)","where loan = '".$loan_info['id']."'"),"last_payment_date"=>$api->get_record("payment","pay_date","where loan = '".$loan_info['id']."' order by id desc limit 1"),"next_payment_date"=>$api->get_record("repayment_breakdown","next_payment_date","where loan = '".$loan_info['id']."' and payment_ref is null order by next_payment_date desc limit 1"));
												} 
												while ($loan_info = mysql_fetch_assoc($all_loans));
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 	else
						 		 	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 	break;
									
			 case 'list_landmarks': if(isset($data_object['token']))
						 		 	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$limit = isset($data_object['results']) ? $data_object['results'] : 50;
																																				
												$response = array("status"=>1,"landmarks"=>array());
												$all_landmarks = mysql_query("select * from landmark where `status` = '1' order by name asc limit ".$limit);
												$landmark_info = mysql_fetch_assoc($all_landmarks);
												do
												{
													$response["landmarks"][] = array("id"=>$landmark_info['id'],"name"=>$landmark_info['name'],"icon"=>$landmark_info['icon'],"lat"=>$landmark_info['lat'],"lng"=>$landmark_info['lng'],"info"=>$landmark_info['info']);
												} while ($landmark_info = mysql_fetch_assoc($all_landmarks));
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 	else
						 		 	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 	break;
			 case 'user_float': if(isset($data_object['token']))
						 		 	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$limit = isset($data_object['results']) ? $data_object['results'] : 50;
												$access_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");																								
												
													$response = array("status"=>1,"agent"=>array());
													$all_users = mysql_query("select * from login_user where".($access_level == "admin" ? " 1" : " id = '".$user_id."'")." order by name asc limit ".$limit);
													$all_users_info = mysql_fetch_assoc($all_users);
													do
													{
														
														$transactions = array();
														if(isset($data['summarized']) && $data['summarized'] == 'true')
														{
															$transacts = mysql_query("select * from user_float where `user` = '".$all_users_info['id']."' order by `date` desc");
															if(mysql_num_rows($transacts) > 0)
															{
															$transacts_all = mysql_fetch_assoc($transacts);
															do
															{
																$transactions[] = array("id"=>$transacts_all['id'],"amount"=>$transacts_all['amount'],"comments"=>$transacts_all['comments'],"float_type"=>$transacts_all['float_type'],"date"=>$transacts_all['date']);
															} while ($transacts_all = mysql_fetch_assoc($transacts));
														}
														}
														
														$response["agent"][] = array("id"=>$all_users_info['id'],"name"=>$all_users_info['name'],"phone"=>$all_users_info['phone'],"username"=>$all_users_info['username'],"access_level"=>$all_users_info['access_level'],"account_status"=>($all_users_info['global_status'] == "1" ? "ACTIVE" : "INACTIVE"),"active_device"=>$api->get_record("login_user_device","imei","where `user` = '".$all_users_info['id']."' order by when_added desc limit 1"),"last_login"=>$api->get_record("user_token","date_sub(expiry, INTERVAL 1 DAY)","where `user` = '".$all_users_info['id']."' order by expiry desc limit 1"),"balance"=>$api->fuel_agent_bal($all_users_info['id']),"balance_fuel"=>$api->fuel_agent_bal($all_users_info['id'],array('fuel')), "instant_credit"=>$all_users_info['cash_out'], "transactions"=>$transactions);
													} 
													while ($all_users_info = mysql_fetch_assoc($all_users));
												
												
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 	else
						 		 	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 	break;
			 case 'user_statement': if(isset($data_object['token']))
						 		 	{
						    				$user_id = $api->get_record("user_token","user","where token = '".mysql_real_escape_string($data_object['token'])."' and expiry >= '".date("Y-m-d H:i:s")."'");
											if($user_id != "")
											{
												$user_level = $api->get_record("login_user","access_level","where id = '".$user_id."'");
												$agent_phone = $api->get_record("login_user","phone","where id = '".$user_id."'");
												
												$limit = isset($data_object['results']) ? $data_object['results'] : 50;
																																				
												$response = array("status"=>1,"statement"=>array());
												
												$all_transactions = mysql_query(
												"SELECT DISTINCT(user_float.`id`) AS `transaction_id`, login_user.id AS `agent_id`,
												 login_user.`name` AS `agent_name`, login_user.`access_level` AS `agent_type`, 
												 product.id as `product_id`, product.product_name as `product_name`, 
												 user_float.`amount` AS `float_deduction`, loan.`total_amount` AS `service_amount`,
												 user_float.`date` AS `transaction_date`, loan_client.id as `customer_id`, 
												 CONCAT(loan_client.`fname`,' ',loan_client.`lname`) AS `customer_name`, 
												 loan_client.`phone` AS `customer_phone`, concat(user_float.`comments`,'-',user_float.float_type) as 
												 `transact_info` FROM login_user JOIN user_float ON (login_user.`id` = user_float.`user`) 
												 LEFT JOIN payment ON (user_float.`id` = payment.`source_ref_1` or concat('agent-',user_float.`id`) = payment.`source_ref_1`) LEFT JOIN loan_client ON (payment.`client` = loan_client.`id`) 
												 LEFT JOIN loan ON (loan_client.`id` = loan.`client` AND loan.`start_date` >= payment.`pay_date`) LEFT JOIN product on (product.id = loan.product) 
												 WHERE loan.`id` IN (SELECT loan_status.`loan` FROM loan_status WHERE loan_status.`status` = 'approved') ".((isset($data_object['partner']) && $data_object['partner'] != "") ? " and loan.service_provider = '".$data_object['partner']."'" : "")." and login_user.id != 27 and (payment.status = 'completed' or payment.status is null) ".(isset($data_object['agent']) && $data_object['agent'] != "" ? " and user_float.user = '".$data_object['agent']."'" : "").($user_level == "admin" ? "" : "and (user_float.user = '".$user_id."' or loan_client.reg_user = '".$user_id."') ")." ".((isset($data_object['filter']) && $data_object['filter'] == "fuel") ? "and login_user.access_level = 'gas-station' " : "")." ".((isset($data_object['from'],$data_object['to']) && $data_object['from'] != "" && $data_object['to'] != "") ? "and substr(user_float.date,1,10) between '".$data_object['from']."' and '".$data_object['to']."' " : "")."GROUP BY transaction_id ORDER BY transaction_date DESC limit ".$limit);
												$agent_transaction = mysql_fetch_assoc($all_transactions);
												do
												{
													$response["statement"][] = array(
													"id"=>$agent_transaction['transaction_id'],
													"transaction_id"=>"agent-".$agent_transaction['transaction_id'],
													"agent_id"=>$agent_transaction['agent_id'],
													"agent_name"=>$agent_transaction['agent_name'],
													"agent_access_level"=>$agent_transaction['agent_type'],
													"product_name"=>$agent_transaction['product_name'],
													"product_id"=>$agent_transaction['product_id'],
													"customer_id"=>$agent_transaction['customer_id'],
													"customer_name"=>$agent_transaction['customer_name'],
													"service_fee"=>$agent_transaction['float_deduction'],
													"date"=>$agent_transaction['transaction_date'],
													"transact_info"=>$agent_transaction['transact_info'],
													"service_amount"=>$agent_transaction['service_amount']);
												} 
												while ($agent_transaction = mysql_fetch_assoc($all_transactions));
												
												//Card Transactions
												$all_cards = mysql_query("SELECT DISTINCT(customer_float.`id`) AS `transaction_id`,
												 login_user.id AS `agent_id`, 
												 login_user.`name` AS `agent_name`, 
												 login_user.`access_level` AS `agent_type`, 
												 product.id as `product_id`, 
												 product.product_name as `product_name`, 
												 customer_float.`amount` AS `float_deduction`, 
												 loan.`total_amount` AS `service_amount`, 
												 customer_float.`transact_date` AS `transaction_date`, 
												 loan_client.id as `customer_id`, 
												 CONCAT(loan_client.`fname`,' ',loan_client.`lname`) AS `customer_name`, 
												 loan_client.`phone` AS `customer_phone`, 
												 concat(customer_float.`remarks`) as `transact_info` 
												 FROM login_user JOIN customer_float ON (login_user.`id` = customer_float.`station`) 
												 LEFT JOIN payment ON (customer_float.`id` = payment.`source_ref_1` or concat('customer-',customer_float.id) = payment.`source_ref_1`) 
												 LEFT JOIN loan_client ON (payment.`client` = loan_client.`id`) LEFT JOIN loan ON (loan_client.`id` = loan.`client` AND loan.`start_date` >= payment.`pay_date`) 
												 LEFT JOIN product on (product.id = loan.product) WHERE loan.`id` IN (SELECT loan_status.`loan` FROM loan_status WHERE loan_status.`status` = 'approved')".((isset($data_object['partner']) && $data_object['partner'] != "") ? " and loan.service_provider = '".$data_object['partner']."'" : "")." and login_user.id != 27 ".(isset($data_object['agent']) && $data_object['agent'] != "" ? " and user_float.user = '".$data_object['agent']."'" : "").($user_level == "admin" ? "" : "and (customer_float.station = '".$user_id."' or loan_client.reg_user = '".$user_id."') ")." ".((isset($data_object['filter']) && $data_object['filter'] == "fuel") ? "and login_user.access_level = 'gas-station' " : "")." ".((isset($data_object['from'],$data_object['to']) && $data_object['from'] != "" && $data_object['to'] != "") ? "and substr(customer_float.transact_date,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : "")."GROUP BY transaction_id ORDER BY transaction_date DESC limit ".$limit);
												
												if(mysql_num_rows($all_cards) > 0)
												{
													$card_transactions = mysql_fetch_assoc($all_cards);
													do
													{
														$response["statement"][] = array(
														"id"=>$card_transactions['transaction_id'],
														"transaction_id"=>"card-".$card_transactions['transaction_id'],
														"agent_id"=>$card_transactions['agent_id'],
														"agent_name"=>$card_transactions['agent_name'],
														"agent_access_level"=>$card_transactions['agent_type'],
														"product_name"=>$card_transactions['product_name'],
														"product_id"=>$card_transactions['product_id'],
														"customer_id"=>$card_transactions['customer_id'],
														"customer_name"=>$card_transactions['customer_name'],
														"service_fee"=>($card_transactions['float_deduction']),
														"date"=>$card_transactions['transaction_date'],
														"transact_info"=>$card_transactions['transact_info'],
														"service_amount"=>$card_transactions['service_amount']);
													} while ($card_transactions = mysql_fetch_assoc($all_cards));
												}
												
												$matched_ids = array();
												//Other IDs
												foreach($response["statement"] as $nM)
												{
													$matched_ids[] = $nM['id'];
												}
												
												//Other Transactions
												$debits = mysql_query("SELECT DISTINCT(user_float.`id`) AS `transaction_id`, 
												login_user.id AS `agent_id`, 
												login_user.`name` AS `agent_name`, 
												login_user.`access_level` AS `agent_type`, 
												user_float.`amount` AS `float_deduction`, 
												user_float.`date` AS `transaction_date`,
												concat(user_float.`comments`,' ',user_float.float_type) AS `transact_info`
												FROM login_user JOIN user_float ON (login_user.`id` = user_float.`user`) 
												WHERE login_user.id != 27 and user_float.id not in (".implode(",",$matched_ids).") ".(isset($data_object['agent']) && $data_object['agent'] != "" ? " and user_float.user = '".$data_object['agent']."'" : "").($user_level == "admin" ? "" : "and (user_float.user = '".$user_id."' or loan_client.reg_user = '".$user_id."') ")." ".((isset($data_object['filter']) && $data_object['filter'] == "fuel") ? "and login_user.access_level = 'gas-station' " : "")." ".((isset($data_object['from'],$data_object['to']) && $data_object['from'] != "" && $data_object['to'] != "") ? "and substr(user_float.date,1,10) between '".$data_object['from']."' and '".$data_object['to']."'" : "")." GROUP BY transaction_id ORDER BY transaction_date DESC limit ".$limit);
												$debit_info = mysql_fetch_assoc($debits);
												do
												{
													$response["statement"][] = array(
													"id"=>$debit_info['transaction_id'],
													"transaction_id"=>$debit_info['transaction_id'],
													"agent_id"=>$debit_info['agent_id'],
													"agent_name"=>$debit_info['agent_name'],
													"agent_access_level"=>$debit_info['agent_type'],
													"product_name"=>"",
													"product_id"=>"",
													"customer_id"=>"",
													"customer_name"=>"",
													"service_fee"=>($debit_info['float_deduction']),
													"date"=>$debit_info['transaction_date'],
													"transact_info"=>($debit_info['float_deduction'] > 0 ? "CREDIT - ".$debit_info['transact_info'] : "DEBIT - ".$debit_info['transact_info']),
													"service_amount"=>"");
												} 
												while ($debit_info = mysql_fetch_assoc($debits));
												
												
												//Sort Array
												$response['statement'] = $api->sort_userstatement($response['statement']);
												
												//Optimize for mobile and SMS
												if(isset($data_object['send_sms']) && $data_object['send_sms'] == "1")
												{
													$message = ""; $x = 1;
													//Mobile
													foreach($response['statement'] as $jk)
													{
														//Customer-AmountPaid-Date
														if(trim($jk['customer_name']) != "")
														{
															$message .= $x++.". ".$jk['customer_name']." - ".number_format($jk['service_amount'])."\nDate: ".date("D-jS-M,Y g:ia", strtotime($jk['date']))."\n";
														}
													}
													
													
													$api->send_sms('TAMBULA',$api->format_number($agent_phone),(trim($message) == "" ? "No transactions today." : $message),'normal');

												}
												
												goto app_end;
											}
											else
											{
												$response = array("status"=>0,"message"=>"invalid user token");
											}
						 			}
						 		 	else
						 		 	{
							 			$response = array("status"=>0,"message"=>"invalid parameters set");
						 		   }
						     	 	break;
			 case 'list_vouchers':
			 case 'list_':
			 
			default: $response = array("status"=>0,"message"=>"no action defined");
		}
  }
  else
  {
		$response = array("status"=>0,"message"=>"action is not defined","token"=>"");
  }
  
  app_end:
  //Log Response
  if($log_inserted_id != "")
  {
	  mysqli_query($db, "update api_logs set response = '".mysqli_real_escape_string($db, json_encode($response))."' where id = '".$log_inserted_id."'");
  }
  
  //Error Codes
  if($response['status'] == '0')
  {
  	//Email Admin
  	$email = '<strong>Error Alert</strong><br>Request ID: '.$log_inserted_id.'<br>Host: '.$_SERVER['REMOTE_ADDR'].'<br>Request Time: '.$currentTime.'<br>Request Body: '.json_encode($data_object).'<br>Request Response: '.json_encode($response);
  	$api->send_email("Tambula Admin","support@tambula.net","Tambula Fuel Error",$email);
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
