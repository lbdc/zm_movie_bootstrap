<!DOCTYPE html>
<html lang="en">
<head>
<?php
	include 'zm_movie_header.html';
?>
<style>
input.style-2 {
   width:40px;
}
input.style-1 {
   width:60px;
}

</style>

<script type="text/javascript" src="js/moment.js"></script>
<script type="text/javascript" src="js/transition.js"></script>
<script type="text/javascript" src="js/collapse.js"></script>
<script type="text/javascript" src="js/bootstrap-datetimepicker.min.js"></script>
<link rel="stylesheet" href="css/bootstrap-datetimepicker.min.css">

</head>
<body>
<?php
//	include 'navbar.html';
//
// Load camera information from DB
require "zm_movie_functions.php";
$camera = Load_Camera();
echo '<script> var camera = ';
echo json_encode($camera);
echo ';</script>';
?>
<div class="container" id="make_movie">
<h2>Movies</h2>
	<div class="table-responsive">
		<table class="table table-hover table-bordered table-condensed">
		<colgroup>
			<col class="col-md-2">
			<col class="col-md-8">
		</colgroup>
		<tbody>
			<tr><td colspan=2>
			<select class="form-control" id="sel_cam" name="sel_cam" onChange="sel_cam(this)">
				echo '<option value="Select">Select Camera</option>';
				<?php   
				foreach($camera as $key=>$value) {
					echo '<option value="'.$key.'">'.$camera[$key]["Name"].'</option>';
				};  ?> 
			</select></div>
			<form role="form" name="make_movie" method="GET">
                        <div class="form-group">
			</td></tr>

			<tr><td>Camera</td><td><input type="text" class="form-control" id="Camera" name="monitor" value="" readonly></input></td></tr>
			<tr><td>CameraId</td><td><input type="text" class="form-control" id="CameraId" name="monitorId" value="" readonly></input></td></tr>
			<tr><th colspan =2>Required Parameters</th></tr>
			<tr><td>Video Start</td><td style="position: relative"><input type='text' class="form-control" name="start" id='start'/></td></tr>
			<tr><td>Video End</td><td style="position: relative"><input type='text' class="form-control" name="end" id='end'/></td></tr>
			<tr><td>Video Filename (no ext.)</td><td><input type ="text" class="form-control" name="Filename" id="Filename" value="filename"></td></tr>
			<tr><td>Frame (All or Alarm)</input></td><td><select name="frame" id="frame" class="form-control"><option value="All">All</option><option value="Alarm">Alarm</option></select></td></tr>

			<tr><th colspan =2>Optional Parameters</th></tr>
			<tr><td>Buffer</td><td><input type="number" name="buffer" max="120" min="0" step="1" value="0"></td></tr>
			<tr><td>Speed</td><td><input type="number" name="Speed" max="1000" min="1" step="1" value="1"></td></tr>
			<tr><td>Video FPS</td><td><input type="number" class="form-control" id="Fps" name="Fps" value="" readonly></input></td></tr> 
			<tr><td>New FPS</td><td><input type="number" name="MultiplierX" id="MultiplierX" max="120" min="0" step="0"></td></tr>
			<tr><td>Video Size</td><td><input type="text" class="form-control" id="Size" name="Size" value="" readonly></input></td></tr> 
			<tr><td>New Size</input></td><td><select name="Size1" id="Size1" class="form-control"><option value="1920:1080">1920x1080</option><option value="1280:720">1280x720</option><option value="800:600">800x600</option><option value="640:480">640x480</option><option value="320:240">320x240</option></select></td></tr>
	
<!--			<tr><td>Use VAAPI</td><td><input type ="checkbox" name="Vaapi" id="Vaapi" value=""></td></tr>	--> 
			<tr><td>VAAPI Device (leave blank if unsure)</td><td><input type ="text" type ="text" name="VADevice" id="VADevice" value="/dev/dri/renderD128"></td></tr> 
			</div>
		</tbody>
		</table>
		<button type="submit" name="mmovie" class="btn btn-primary btn-md">Make Movie</button>
		</form>
	</div>
</div>

<!--<?php var_dump($camera); ?> -->
<?php
$files = scandir('zm_tmp');
sort($files); // this does the sorting
foreach($files as $file){
   echo'<a href="zm_tmp/'.$file.'">'.$file.'</a>';
}
?>


<script type="text/javascript">
var x;
function sel_cam(sel) {
	x = sel.value;
	document.getElementById('Camera').value = camera[x]["Name"];
	document.getElementById('CameraId').value = camera[x]["Id"];
	document.getElementById('start').value = camera[x]["Starttime"];
	document.getElementById('end').value = camera[x]["Endtime"];
	document.getElementById('Size').value = camera[x]["Size"];
	document.getElementById('Size1').value = camera[x]["Size"];
	document.getElementById('Fps').value = camera[x]["Fps"];
	document.getElementById('MultiplierX').value = camera[x]["Fps"];
/* Parse Date&time for future date time picker
	var date = moment(camera[x]["Starttime"]).format('/dev/dri/renderD128YYYY-MM-DD HH:mm:ss');
	document.getElementById('year').value = moment(date).format('YYYY');
	document.getElementById('month').value = moment(date).format('MM');
	document.getElementById('day').value = moment(date).format('DD');
	document.getElementById('hour').value = moment(date).format('HH');
	document.getElementById('minute').value = moment(date).format('mm');
	document.getElementById('second').value = moment(date).format('ss');
*/
}
</script>
<?php

// POST data, call script, clear GET
$movie_path=""; //use local folder if not set
if(isset($_GET['mmovie'])) {
// if video size and FPS remains the same, do not send any parameters
	if($_GET["MultiplierX"] == $_GET["Fps"]) { $_GET["MultiplierX"] = ""; } else { $_GET["MultiplierX"] = "--fps ". $_GET["MultiplierX"]; }
	if($_GET["Size1"] == $_GET["Size"]) { $_GET["Size1"] = ""; } else { $_GET["Size1"] = "--size " . $_GET["Size1"]; } 
	if($_GET["buffer"] == 0) { $_GET["buffer"] = ""; } else { $_GET["buffer"] = "--buffer " . $_GET["buffer"]; }
	if($_GET["Speed"] == 1) { $_GET["Speed"] = ""; } else { $_GET["Speed"] = "--speed " . $_GET["Speed"]; }
	if(!empty($_GET["VADevice"])) { $_GET["VADevice"] = "--render " . $_GET["VADevice"]; } else { $_GET["VADevice"] = ""; } 
		
	$command='/usr/bin/php zm_alm_134.php --id '.$_GET["monitorId"].' --start "'.$_GET["start"].'" --end "'.$_GET["end"].'" --frame '.$_GET["frame"].' '.$_GET["buffer"].' --file '.$_GET["Filename"].' '.$_GET["Speed"].' '.$_GET["MultiplierX"].' '.$_GET["Size1"].' '.$_GET["VADevice"];
//	echo file_put_contents("command.txt",$command);
	exec("($command) > /dev/null &");
	unset($_GET);
	$page=$_SERVER['PHP_SELF'];
	echo '<script>location.href="'.$page.'";</script>';
}
?> 
</body>
</html>
