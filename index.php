<?php

/*
*
* custom form using Google Drive and Spreadsheet as DB
*
*/

session_start();

if (isset($_POST['form_action'])&&$_POST['form_action']=='submitted') {

	date_default_timezone_set('Europe/Belgrade');

	// google recaptcha validation

	if(isset($_POST['g-recaptcha-response'])){
        $captcha=$_POST['g-recaptcha-response'];
    }
    else
        $captcha = false;

    if(!$captcha){
        $_SESSION['msg'] = "Form is not validated trough recaptcha.";
        header("Location: index.php");
        die;
    }
    else{
        $secret = 'secret-key';
        $data = array(
            'secret' => $secret,
            'response' => $captcha
        );

		$verify = curl_init();
		curl_setopt($verify, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
		curl_setopt($verify, CURLOPT_POST, true);
		curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
		curl_setopt($verify, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($verify);

        if(json_decode($response)->success==false)
        {
            $_SESSION['msg'] = "Google recaptcha validation unsuccessful.";
            header("Location: index.php");
            die;
        }
    }

	require_once __DIR__ . '/vendor/autoload.php';

	$client = new Google_Client();
	$client->setApplicationName('Your application name');
	$client->setAuthConfig(__DIR__ . '/google_client_key.json');
	$client->addScope(Google_Service_Sheets::SPREADSHEETS);
	$client->setScopes(array('https://www.googleapis.com/auth/drive'));
	$client->setAccessType('offline');

	/*
	* uploading files in folder of yours
	*
	*/

	$driveService = new Google_Service_Drive($client);

	$files_arr = array();

	$folder_id = 'Google drive folder ID string';


	$fileMetadata = new Google_Service_Drive_DriveFile(array(
    	'name' => $_POST['first_name'] . ' ' . $_POST['last_name'],
    	'mimeType' => 'application/vnd.google-apps.folder',
    	'parents' => array($folder_id))
	);
	$single_folder = $driveService->files->create($fileMetadata, array('fields' => 'id'));






	foreach ($_FILES as $raw_file) {
		$fileMetadata = new Google_Service_Drive_DriveFile(array(
		    'name' => $raw_file['name'],
		    'parents' => array($single_folder->id)
		));
		$content = file_get_contents($raw_file['tmp_name']);
		$file = $driveService->files->create($fileMetadata, array(
		    'data' => $content,
		    'mimeType' => $raw_file['type'],
		    'uploadType' => 'media',
		    'fields' => 'id'));

		array_push($files_arr, $file->id);
	}

	/*
	* insert in google sheet
	*
	*/

	$service = new Google_Service_Sheets($client);

	$spreadsheetId = 'Google spreadsheet ID string';
	$range = 'Spreadsheet Sheet title or other range';

	$valueRange= new Google_Service_Sheets_ValueRange();
	$vals=array();

	$vals['values'][] = @date("d.m.Y. H:i:s");
	$vals['values'][] = $_POST['first_name'];
	$vals['values'][] = $_POST['last_name'];
	$vals['values'][] = $_POST['birth_date'];
	$vals['values'][] = $_POST['city'];
	$vals['values'][] = $_POST['gender'];
	$vals['values'][] = '=HYPERLINK("mailto:' . $_POST['email'] . '","' . $_POST['email'] . '")';
	$vals['values'][] = "'" . $_POST['phone'];
	$vals['values'][] = $_POST['source'];
	$vals['values'][] = $_POST['interests'];
	$vals['values'][] = $_POST['plans'];
	$vals['values'][] = $_POST['meaning_activity'];
	$vals['values'][] = $_POST['last_activity'];
	$vals['values'][] = '=HYPERLINK("https://drive.google.com/open?id=' . $files_arr[0] . '","mot.letter")';
	$vals['values'][] = '=HYPERLINK("https://drive.google.com/open?id=' . $files_arr[1] . '","CV")';

	$valueRange->setValues( $vals );
	$conf = ["valueInputOption" => "USER_ENTERED"];

	try {
	    $res = $service->spreadsheets_values->append($spreadsheetId, $range, $valueRange, $conf);
	} catch (Exception $e) {
	    die($e->getMessage());
	}



	// email of confirmation
	$to      = $_POST['email'];
	$subject = 'Mail of confirmation';
	$message = '<p>Successful application, Thank You!</p>';

	$headers = 'From: noreply@mysite.com' . "\r\n" .
    'Reply-To: noreply@mysite.com' . "\r\n" .
    'Content-type: text/html; charset=utf-8' . "\r\n" .
    'MIME-Version: 1.0' . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

	mail($to, $subject, $message, $headers);
}


?>


<!DOCTYPE html>
<html lang="sr">
	<head>
		<meta charset="utf-8">

		<title>Custom Form</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
		<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js" type="text/javascript"></script>
		<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
		<!-- Global site tag (gtag.js) - Google Analytics -->
		<script async src="https://www.googletagmanager.com/gtag/js?id=some_GA_ID"></script>
		<script>
		  	window.dataLayer = window.dataLayer || [];
		  	function gtag(){dataLayer.push(arguments);}
		  	gtag('js', new Date());

		  	gtag('config', 'some_GA_ID');
		</script>
		<style type="text/css">
			div.ui-datepicker{
			 	font-size:14px;
			}

			.spinner-holder {
				position: absolute;
				z-index: 3;
				top: 30%;
				left: 50%;
				width: 50px;
				height: 50px;
			}
			.spinner {
			  	width: 40px;
			  	height: 40px;
			  	position: relative;
			  	margin: 100px auto;
			}

			.double-bounce1, .double-bounce2 {
			  	width: 100%;
			  	height: 100%;
			  	border-radius: 50%;
			  	background-color: #8ED2C7;
			  	opacity: 0.6;
			  	position: absolute;
			  	top: 0;
			  	left: 0;
			  
			  	-webkit-animation: sk-bounce 2.0s infinite ease-in-out;
			  	animation: sk-bounce 2.0s infinite ease-in-out;
			}

			.double-bounce2 {
			  	-webkit-animation-delay: -1.0s;
			  	animation-delay: -1.0s;
			}

			@-webkit-keyframes sk-bounce {
			  	0%, 100% { -webkit-transform: scale(0.0) }
			  	50% { -webkit-transform: scale(1.0) }
			}

			@keyframes sk-bounce {
			  	0%, 100% { 
			    	transform: scale(0.0);
			    	-webkit-transform: scale(0.0);
			  	} 50% { 
			    	transform: scale(1.0);
			    	-webkit-transform: scale(1.0);
			  	}
			}
			body div.active-content {
				opacity: .1;
				transition: all .3s ease;
			}
		</style>
		<script src="https://www.google.com/recaptcha/api.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
		<script type="text/javascript">
			// JavaScript for disabling form submissions if there are invalid fields
			(function() {
			  'use strict';
			  window.addEventListener('load', function() {
			    // Fetch all the forms we want to apply custom Bootstrap validation styles to
			    var forms = document.getElementsByClassName('needs-validation');
			    // Loop over them and prevent submission
			    var validation = Array.prototype.filter.call(forms, function(form) {
			      form.addEventListener('submit', function(event) {
			        if (form.checkValidity() === false) {
			          	event.preventDefault();
			          	event.stopPropagation();

			          	// set focus on first invalid form field
			          	var el = $('.form-control:invalid').get(0);

			          	$('html,body').animate({scrollTop: $(el).offset().top - 200}, 200, function() {
						    $(el).focus();
						});
			        } else {
						$('.spinner-holder').show();
						$('.active-content').css('opacity', '.1');
			        }
			        form.classList.add('was-validated');
			      }, false);
			    });
			  }, false);
			})();
		</script>
		<!-- Facebook Pixel Code -->
		<script>
		  	!function(f,b,e,v,n,t,s)
	  		{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
	  			n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  			if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
  			n.queue=[];t=b.createElement(e);t.async=!0;
	  		t.src=v;s=b.getElementsByTagName(e)[0];
	  		s.parentNode.insertBefore(t,s)}(window, document,'script',
	  		'https://connect.facebook.net/en_US/fbevents.js');
	  		fbq('init', 'FB_pixel_ID');
	  		fbq('track', 'PageView');
		</script>
		<noscript><img height="1" width="1" style="display:none"
		  	src="https://www.facebook.com/tr?id=FB_pixel_ID&ev=PageView&noscript=1"
		/></noscript>
		<!-- End Facebook Pixel Code -->
		
	</head>
	<body>
		<div class="spinner-holder">
			<div class="spinner">
			 	<div class="double-bounce1"></div>
			  	<div class="double-bounce2"></div>
			</div>
		</div>
		<div class="container active-content">
			<div class="row">
				<h4 class="col-md-6 mt-5 offset-md-3 text-center">FORM</h4>
				
				<?php
				if (isset($_POST['form_action'])&&$_POST['form_action']=='submitted') {
					?>
					<!-- Form submitted fb tracking -->
					<script type="text/javascript">
						var facebookTrackConversionForForm = "#ad_form";
						var facebookConversionType = "Lead";
					  	
					      		fbq('track', facebookConversionType);
					    	
					</script>
					<!-- End of Form submitted fb tracking -->
					<div class="col-12 mt-5 text-center">
						<p class="text-success">Thank for filling up our form.</p>
						<p class="text-success"><small style="opacity: .5;">An email hes been sent to you. Check email inbox, or spam folder.</small></p>
					</div>
					<?php
				} else {
					?>
					<div class="col-12 mt-5 offset-md-3">
						<p><small class="text-muted">* Needed fields.</small></p>
					</div>
				<?php
				}
				if (isset($_SESSION['msg'])) {
					?>
					<div class="col-12 mt-5 text-center">
						<p class="text-danger"><?php echo $_SESSION['msg'] ; ?></p>
					</div>
					<?php
					unset($_SESSION['msg']);
				}
				?>

				<form action="" method="post" id="ad_form" data-toggle="validator" class="needs-validation col-md-6 mt-5 mb-5 offset-md-3" enctype="multipart/form-data" novalidate autocomplete="off">
				  	<div class="form">
				    	<div class="mb-3">
				      		<label for="first_name">First Name*</label>
				      		<input type="text" class="form-control" id="first_name" placeholder="First Name" name="first_name" required>
				      		<span class="glyphicon form-control-feedback" aria-hidden="true"></span>
    						<div class="invalid-feedback">
					        	Please enter valid data.
					      	</div>
				    	</div>
				    	<div class="mb-3">
				      		<label for="last_name">Last Name*</label>
				      		<input type="text" class="form-control" id="last_name" placeholder="Last Name" name="last_name" required>
				      		<div class="invalid-feedback">
					        	Please enter valid data.
					      	</div>
				    	</div>
				    	<div class="mb-3">
				    		<label for="birth_date">Date of birth*</label>
							<input type="text" name="birth_date" class="form-control" id="birth_date" placeholder="Date of birth" required>
							<div class="invalid-feedback">
								Please enter your birth date.
							</div>
						</div>
					    <div class="mb-3">
					      	<label for="city">Residence*</label>
					      	<input type="text" class="form-control" id="city" placeholder="Residence" name="city" required>
					      	<div class="invalid-feedback">
					        	Please enter valid data.
					      	</div>
					    </div>
					    <div class="mb-3">
					      	<label for="gender">Gender</label>
					      	<select id="gender" class="form-control" name="gender">
					        	<option value="" selected>Choose...</option>
					        	<option value="male">Male</option>
					        	<option value="female">Female</option>
					      	</select>
					    </div>
				  	</div>
				  	<div class="form">
					    <div class="mb-3">
					      	<label for="email">Email*</label>
					      	<input type="email" class="form-control" id="email" placeholder="name@domain.com" name="email" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" required>
					      	<div class="invalid-feedback">
					        	Please enter valid email address.
					      	</div>
					    </div>
					    <div class="mb-3">
					      	<label for="phone">Phone*</label>
					      	<input type="tel" class="form-control" id="phone" placeholder="+381xxxxxxxxx" name="phone" pattern="[+381]{4}[0-9]{8,9}" required>
					      	<div class="invalid-feedback">
					        	Please enter valid phone number.
					      	</div>
					    </div>
				  	</div>
				  	<div class="form-group">
				  		<label for="source">How you heard for this form?*</label>
				  		<textarea class="form-control" rows="3" id="source" name="source" required></textarea>
				  		<div class="invalid-feedback">
				        	Required field.
				      	</div>
				  	</div>
				  	<div class="form-group">
				  		<label for="interests">Your fields of interests? (e.g. politics, elections, jurnalism, human rights...etc)*</label>
				  		<textarea class="form-control" rows="3" id="interests" name="interests" required></textarea>
				  		<div class="invalid-feedback">
				        	Required field.
				      	</div>
				  	</div>
				  	<div class="form-group mb-5">
				  		<label for="plans">Wich skills do you want to build on?*</label>
				  		<textarea class="form-control" rows="3" id="plans" name="plans" required></textarea>
				  		<div class="invalid-feedback">
				        	Required field.
				      	</div>
				  	</div>
				  	<div class="form-group mb-5">
				  		<label for="meaning_activity">For you what is the meanng of be active in community?*</label>
				  		<textarea class="form-control" rows="3" id="meaning_activity" name="meaning_activity" required></textarea>
				  		<div class="invalid-feedback">
				        	Required field.
				      	</div>
				  	</div>
				  	<div class="form-group mb-5">
				  		<label for="last_activity">Tell us your last activity for comunity?*</label>
				  		<textarea class="form-control" rows="3" id="last_activity" name="last_activity" required></textarea>
				  		<div class="invalid-feedback">
				        	Required field.
				      	</div>
				  	</div>
				  	<div class="custom-file mb-5">
    					<input type="file" class="custom-file-input form-control" id="mot_letter" name="mot_letter" required>
    					<label class="custom-file-label" for="mot_letter">Motivation letter...</label>
    					<div class="invalid-feedback">Motivation letter is required.</div>
  					</div>
  					<div class="custom-file mb-5">
    					<input type="file" class="custom-file-input form-control" id="cv" name="cv" required>
    					<label class="custom-file-label" for="cv">CV...</label>
    					<div class="invalid-feedback">CV is required.</div>
  					</div>
  					<div class="captcha_wrapper">
						<div class="g-recaptcha mb-3" data-sitekey="site-key"></div>
					</div>
				  	<input type="hidden" name="form_action" value="submitted">
				  	<button  onClick="gtag('event', 'send_application', { 'event_category': 'application','event_label': 'Submiting form' });" class="btn btn-primary" id="submit" type="submit">Submit</button>
				</form>
			</div>
		</div>
		<script>
            $('.custom-file-input').on('change',function(){
            	if(this.files[0].size > 26214400){
			       alert("Fajl ne sme biti veći od 25MB!");
			       this.value = "";
			    };
                //get the file name
                var fileName = $(this).val().split('\\').reverse()[0];
                //replace the "Choose a file" label
                $(this).next('.custom-file-label').html(fileName);
            });
		    // gives format to date of jquery ui datepicker
			jQuery(document).ready(function(){
				$( "#birth_date" ).datepicker({
					dateFormat: "dd.mm.yy.",
					showButtonPanel: true,
    				changeMonth: true,
    				changeYear: true,
    				selectOtherMonths: true,
    				yearRange: "1930:2019"
				});
			});

			jQuery( window ).on('load', function() {
				$('.spinner-holder').hide();
				$('.active-content').css('opacity', '1');
			});
			
			//keep element in view
			(function($)
			{
			    $(document).ready( function()
			    {
			        var elementPosTop = $('.spinner-holder').position().top;
			        $(window).scroll(function()
			        {
			            var wintop = $(window).scrollTop(), docheight = $(document).height(), winheight = $(window).height();
			            //if top of element is in view
			            if (wintop > elementPosTop)
			            {
			                //always in view
			                $('.spinner-holder').css({ "position":"fixed", "top":"25%" });
			            }
			            else
			            {
			                //reset back to normal viewing
			                $('.spinner-holder').css({ "position":"absolute", "top":"30%", "left":"50%" });
			            }
			        });
			    });
			})(jQuery);
		</script>
	</body>
</html>