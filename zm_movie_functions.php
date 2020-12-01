<?php
//
// php functions used for website
//
function Load_Camera()
{
	// Read from etc/zm/zm.conf (ubuntu) or etc/zm.conf (centos)
	//
	if(file_exists("/etc/zm/zm.conf")) {
		$ini_file='/etc/zm/zm.conf';}
	else if(file_exists("/etc/zm.conf")) {
		$ini_file='/etc/zm.conf';}
	else { echo "No zoneminder configuration zm.conf found";}
	//
	// Parse ini file the long way (PHP deprecated # as comments in ini files)
	//
	$file = fopen($ini_file, "r");
	while(!feof($file)) {
		$line = fgets($file);
		if($line[0] =="#" || strlen($line) <=1) {
			// skip line
		}
		else {
			$config_ini=explode("=", $line);
			$config[$config_ini[0]]=str_replace(PHP_EOL, null, $config_ini[1]);
		}
	}
	fclose($file);
	define('ZM_HOST', $config['ZM_DB_HOST']);
	define('ZMUSER', $config['ZM_DB_USER']);
	define('ZMPASS', $config['ZM_DB_PASS']);
	define('ZM_DB', $config['ZM_DB_NAME']);	
	//
	// Loads cameras and event range
	//	
	$con=mysqli_connect(ZM_HOST,ZMUSER, ZMPASS, ZM_DB);
	if (mysqli_connect_errno()) {
		echo "Failed to connect to MySQL: " . mysqli_connect_error();
	}
	//
	// The query can be combined but works fine as is
	//
	$result = mysqli_query($con,"SELECT Id, Name, Width, Height, CaptureFPS from Monitors, Monitor_Status WHERE Monitors.Id=Monitor_Status.MonitorId AND Enabled=1 AND Function != 'None'");
	while($row = mysqli_fetch_assoc($result)) {
		$mon_name[]=$row;
	}
	for($i = 0; $i < count($mon_name); $i++) {
		$j = $mon_name[$i]['Id'];
	//	$result = mysqli_query($con,"SELECT MonitorId, min(Starttime), max(Endtime), CaptureFPS from Events, Monitor_Status where MonitorId=$j");
		$result = mysqli_query($con,"SELECT MonitorId, min(Starttime), max(Endtime) from Events where MonitorId=$j");
		while($row = mysqli_fetch_assoc($result)) {
			$mon_event[]=$row;
		}
	}
	// Don't like paranthesis in variables
	$i=0;
	foreach($mon_event as &$name) {
		$name['Id'] = $mon_name[$i]['Id'];
		$name['Name'] = $mon_name[$i]['Name'];
		$name['Size'] = $mon_name[$i]['Width'] . ':' . $mon_name[$i]['Height'];
		$name['Starttime'] = $name['min(Starttime)'];
		$name['Endtime'] = $name['max(Endtime)'];
		unset($name['min(Starttime)']);
		unset($name['max(Endtime)']);
		$name['Fps'] = round($mon_name[$i]['CaptureFPS']);
		$i++;
 	}	
	mysqli_close($con);
	return $mon_event;
}
function Movie_Update() {
	// suppress warnings in log
	error_reporting(E_ALL ^ E_WARNING); 
        echo '
        <div class="container">
                <h2>ZM Movies</h2>
                <table class="table text-center table-hover table-bordered table-condensed">
                        <colgroup>
				<col class="col-sm-2">
				<col class="col-sm-2">
				<col class="col-sm-1">
				<col class="col-sm-1">
				<col class="col-sm-1">
				<col class="col-sm-1">
				<col class="col-sm-1">
				<col class="col-sm-1">
                        </colgroup>
                        <thread>
                                <tr>
                                        <th>Movie</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-center">Length(min)</th>                                       
                                        <th class="text-center">Size(MB)</th>
                                        <th class="text-center">List</th>
                                        <th class="text-center">Pgs</th> 
                                        <th class="text-center">Log</th>
                                        <th class="text-center">Del</th>
                                </tr>
                        </thread>
                <tbody>';
// Show existing movies for download
// Get movies from folder and store them into array with corresponding supporting file

	$movie_path="zm_movie"; 
	$movie_tmp="zm_tmp"; 
// Simply looking at the log file (first created) as we need basename only
	$files = glob($movie_path.'/*.{log}', GLOB_BRACE);
        usort($files, create_function('$b,$a', 'return filemtime($a) - filemtime($b);'));
        foreach($files as $value) {
                $movie_files = pathinfo($value,PATHINFO_FILENAME);
                
// Progress of videos being processed
// Get estimated video length from log file and amount of videos
		$log = @file_get_contents($movie_path . '/' . $movie_files . '.log');
		preg_match("/(?<=min=).*/", $log, $length);
		preg_match("/(?<=found=).*/", $log, $count);
		preg_match("/Video did not complete/", $log, $notok);
		preg_match("/Video Success/", $log, $ok);
		$ff_progress_msg = "Processing";
		$movie_length = round($length[0],1);
// Verify if was not successfull
		if($ok) {$success = 1;}
		if($notok) {$success = 0;}
// if not get progress
		if(file_exists($movie_path . '/' . $movie_files . '.pgs') && !isset($success))
		{
			$ffmpeg_output = shell_exec('tail -11 ' . $movie_path . '/' . $movie_files . '.pgs');
			// get encoding ms from ffmpeg log
			preg_match("/(?<=out_time_ms=).*/", $ffmpeg_output, $a_match);
			// convert to seconds
			$var = $a_match[0]/(1000000); // in seconds
			$ff_progress = $var/($length[0]*60)*100;
		}
// Display in web
		$size = round(filesize($movie_path.'/'.$movie_files.'.mp4')/1000000);
		if(file_exists($movie_path.'/'.$movie_files.'.mp4') && $success == 1) { echo '
			<tr><td class="text-left"><a href="'.$movie_path.'/'.$movie_files.'.mp4">'.$movie_files.'.mp4</a></td>
			<td><div class="progress"><div class="progress-bar progress-bar-success" role="progressbar" style="width:100%">Completed</td>
			<td>'.$movie_length.'</td>
			<td>'.$size.'</td>';
		} elseif( $success == "0") { echo '
			<td class="text-left">'.$movie_files.'.mp4</td>
			<td><div class="progress"><div class="progress-bar progress-bar-danger" role="progressbar" style="width:100%">Error</td>
			<td>'.$movie_length.'</td>
			<td>'.$size.'</td>';}
		else { echo '
			<td class="text-left">'.$movie_files.'.mp4</td>
			<td><div class="progress" style="background-color: #6c757d"><div class="progress-bar progress-bar-striped progress-bar-animated progress-bar-animated" role="progressbar"
			style="width:'.$ff_progress.'%">Rendering</td>
			<td>'.$movie_length.'</td>
			<td>'.$size.'</td>';}

		if(file_exists($movie_path.'/'.$movie_files.'.txt')) { echo '
			<td><a href="'.$movie_path.'/'.$movie_files.'.txt"><span class="glyphicon glyphicon glyphicon-menu-hamburger"></a></span></td>'; 
		} else { echo '
			<td><span class="glyphicon glyphicon glyphicon-menu-hamburger"></a></span></td>'; }

		if(file_exists($movie_path.'/'.$movie_files.'.pgs')) { echo '
			<td><a href="'.$movie_path.'/'.$movie_files.'.pgs"><span class="glyphicon glyphicon glyphicon-hourglass"></a></span></td>'; 
		} else { echo '
			<td><span class="glyphicon glyphicon glyphicon-hourglass"></a></span></td>'; }
	
		if(file_exists($movie_path.'/'.$movie_files.'.log')) { echo '
			<td><a href="'.$movie_path.'/'.$movie_files.'.log"><span class="glyphicon glyphicon glyphicon-edit"></a></span></td>
			<td><button type="button" class="btn btn-default btn-sm" onclick=delkill("'.$movie_path.'/'.$movie_files.'")><span class="glyphicon glyphicon glyphicon-trash"></span></button></td>';
		} else { echo '
			<td><span class="glyphicon glyphicon glyphicon-menu-hamburger"></a></span></td>; 
			<td><button type="button" class="btn btn-default btn-sm" onclick=delkill("'.$movie_path.'/'.$movie_files.'")><span class="glyphicon glyphicon glyphicon-trash"></span></button></td>';}
		echo '</tr>';
	}
} 
function Delete_Movie($movie) {
	$encode = shell_exec("ps -ef | grep ".$movie.".mp4 | grep -v grep | awk '{print $2}'");
	$encode = explode("\n",$encode);
	if($encode[0] > 0) {
	// kill process
		foreach($encode as $value) {
			exec("kill $value");
		}
	}
	else {
	// deleting files
		unlink($movie.".mp4");
		unlink($movie.".txt");
		unlink($movie.".log");
		unlink($movie.".pgs");
	}
}
// Main php here
// Called function from url
$function_call = NULL;
if (defined('STDIN')) {
	if (isset($argv)){
		$function_call = $argv[1];
	}
} else {
	if(isset($_GET["f"])) {
		$function_call = $_GET["f"];
	}
}
if($function_call == "Movie_Update") {
	Movie_Update();
}
if($function_call == "Movie_Update_Collapse") {
	Movie_Update_collapse();
}
if($function_call == "Delete_Movie") {
	Delete_Movie($_GET["movie"]);
}
if($function_call == "Load_Camera") {
	Load_Camera();
}
	
?>
