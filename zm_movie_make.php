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
			<tr><td>Video Start</td><td style="position: relative"><input type='text' class="form-control" name="start" id='start'/></td></tr>
<!--			<tr><td></td><td><input type="number" class="style-1" id="year" max="2050" min="2000" step="1" value=""><input type="number" class="style-2" id="month" max="12" min="1" step="1" value=""><input type="number" class="style-2" id="day" max="31" min="1" step="1" value=""><input type="number" class="style-2" id="hour" max="23" min="0" step="1" value=""><input type="number" class="style-2" id="minute" max="59" min="0" step="1" value=""><input type="number" class="style-2" id="second" max="59" min="1" step="1" value=""></td></tr>
-->			
			<tr><td>Video End</td><td style="position: relative"><input type='text' class="form-control" name="end" id='end'/></td></tr>
			<tr data-toggle="tooltip" title="Used with mocord"><td>Buffers (Sec.)</td><td><input type="number" name="Buffer" max="60" min="0" step="5" value="5"></td></tr>
			<tr data-toggle="tooltip" title="Alarm or All Frames"><td>Frames</td><td><select name="Frames" class="form-control"> <option value="Alarm">Alarm</option> <option value="All">All</option></select></td></tr>

			<tr><th colspan =2>Encoder Parameters</th></tr>
			<tr data-toggle="tooltip" title="Quality: 0=Best 51=Worst"><td>CRF</td><td><input type="number" name="CRF" max="51" min="0" value="23"></td></tr>
			<tr data-toggle="tooltip" title="Default: Main"><td>Profile</td><td><select name="Profile" class="form-control"><option value="Baseline">Baseline</option><option value="Main" SELECTED>Main</option><option value="High">High</option></select></td></tr>
			<tr><td>Preset</td><td><select name="Preset" class="form-control"><option value="Veryslow">Veryslow</option><option value="Slow">Slow</option><option value="Medium">Medium</option><option value="Fast" SELECTED>Fast</option><option value="Faster">Faster</option><option value="Veryfast">Veryfast</option><option value="Superfast">Superfast</option><option value="Ultrafast">Ultrafast</option></select></td></tr>
			<tr><td>Speed</td><td><input type="number" name="Speed" max="50" min="1" step="1" value="10"></td></tr>
			<tr><td>Skip Frames</td><td><input type="number" name="MultiplierX" max="100" min="1" step="1" value="1"></td></tr>
			<tr data-toggle="tooltip" title="* Camera Size"><td>Size</td><td><select name="Size" id="Size" class="form-control"><option value=""></option><option value="1920:1080">1920x1080</option><option value="1280:720">1280x720</option><option value="800:600">800x600</option><option value="704:480">640x480</option><option value="640:480">640x480</option><option value="320:240">320x240</option></select></td></tr>
			<tr><td>Filename</td><td><input type ="text" class="form-control" name="Filename" id="Filename" value=""></td></tr>
			<tr><td>MaxTime</td><td><input type="number" name="MaxTime" max="60" min="1" step="1" value="60"></td></tr>

			</div>
		</tbody>
		</table>
		<button type="submit" name="mmovie" class="btn btn-primary btn-md">Make Movie</button>
		</form>
	</div>
</div>
<script type="text/javascript">
var x;
function sel_cam(sel) {
	x = sel.value;
	document.getElementById('Camera').value = camera[x]["Name"];
	document.getElementById('CameraId').value = camera[x]["Id"];
	document.getElementById('start').value = camera[x]["Starttime"];
	document.getElementById('end').value = camera[x]["Endtime"];
	document.getElementById('Size').value = camera[x]["Size"];
	document.getElementById('Filename').value = camera[x]["Name"];
/* Parse Date&time for future date time picker
	var date = moment(camera[x]["Starttime"]).format('YYYY-MM-DD HH:mm:ss');
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
$myfile = fopen("zm_movie_path.txt", "r") or die("Unable to open zm_movie_path.txt");
$movie_path=fgets($myfile);
$movie_path=str_replace(array("\r", "\n"), '', $movie_path);
fclose($myfile);

if(isset($_GET['mmovie'])) {
	$command='/usr/bin/php zm_movie_encode.php '.$_GET["monitorId"].' "'.$_GET["start"].'" "'.$_GET["end"].'" '.$_GET["Buffer"].' '.$_GET["Speed"].' '.$_GET["MultiplierX"].' '.$_GET["Frames"].' '.$_GET["Size"].' '.$_GET["Profile"].' '.$_GET["Preset"].' '.$_GET["CRF"].' '.$_GET["MaxTime"].' '.$_GET["Filename"];
	exec("($command) > /dev/null &");
	unset($_GET);
	$page=$_SERVER['PHP_SELF'];
	echo '<script>location.href="'.$page.'";</script>';
}
?> 
</body>
</html>
