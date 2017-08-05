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
	$con=mysqli_connect(ZM_HOST,ZMUSER, ZMPASS, ZM_DB);
	if (mysqli_connect_errno()) {
		echo "Failed to connect to MySQL: " . mysqli_connect_error();
	}
	//
	// The query can be combined but works fine as is
	//
	$result = mysqli_query($con,"SELECT Id, Name, Width, Height from Monitors");
	while($row = mysqli_fetch_assoc($result)) {
		$mon_name[]=$row;
	}
	for($i = 0; $i < count($mon_name); $i++) {
		$j = $mon_name[$i]['Id'];
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
		$i++;
 	}	
	mysqli_close($con);
	return $mon_event;
}
function Movie_Update() {
	echo '
	<div class="container">
		<h2>ZM Movies</h2>
		<table class="table text-center table-hover table-bordered table-condensed">
			<colgroup>
				<col class="col-sm-6">
				<col class="col-sm-1">
				<col class="col-sm-1">
				<col class="col-sm-1">
				<col class="col-sm-1">
				<col class="col-sm-1">
			</colgroup>	
			<thread>
				<tr>
					<th>Movie</th>
					<th class="text-center">MB</th>
					<th class="text-center">min</th>
					<th class="text-center">List</th>
					<th class="text-center">Log</th>
					<th class="text-center">Del</th>
				</tr>
			</thread>
		<tbody>';
// Show existing movies for download
// Get movies from folder and store them into array with corresponding supporting file
	$movie_path=""; //use local folder if not set
	$myfile = fopen("zm_movie_path.txt", "r") or die("Unable to open zm_movie_path.txt");
	$movie_path=fgets($myfile);
	$movie_path=str_replace(array("\r", "\n"), '', $movie_path);
	fclose($myfile);
	foreach(glob($movie_path.'/*.{mkv,mp4}', GLOB_BRACE) as $value) {
		$movie_files[] = pathinfo($value,PATHINFO_FILENAME); 
		$movie_txt[]= basename($value,pathinfo($value,PATHINFO_EXTENSION))."txt";
		$movie_log[]= basename($value,pathinfo($value,PATHINFO_EXTENSION))."log"; 
		$movie_progress[]= basename($value,pathinfo($value,PATHINFO_EXTENSION))."progress"; 
	}
	echo '<div class="container" Id="movies">';
	if(isset($movie_files)) {
		foreach($movie_files as $key=>$value) {		
		// Populate movie information
		// Movie duration
			$duration = preg_grep("(Movie_duration)", file($movie_path."/".$movie_log[$key]));
			$duration = array_pop($duration);
			$duration = explode(' ', $duration);
			$duration = array_pop($duration);
			// Movie Progress
			$text_length = intval(exec("wc -l ".$movie_path."/".$movie_txt[$key]));
			$progress = file($movie_path."/".$movie_progress[$key]);
			$progress =round(intval($progress[0])/$text_length*100);
			// Movie Status (check if it being encoded)
			$encode = shell_exec("ps -ef | grep ".$movie_files[$key].".mp4 | grep -v grep | awk '{print $2}'");
			$encode=explode("\n",$encode);
			// Movie Size
			$size=number_format(filesize($movie_path."/".$movie_files[$key].".mp4")/1000000/$progress*100,1);	
			if($encode[0] > 0 && $progress < 100) {
				$status = "Encoding"; }
			else if ($encode[0] == 0 && $progress == 100) {
					$status = "Completed"; }
			else if ($text_length == 0) { 
					$status = "No alarms"; }
			else {$status = "error"; }
		// Display movie files	
			if($status=="Completed") {
				echo '<tr>
				<td class="text-left"><a href="'.$movie_path.'/'.$movie_files[$key].'.mp4">'.$movie_files[$key].'.mp4</a>
				<div class="progress"><div class="progress-bar progress-bar-success" role="progressbar" style="width:100%">Completed</td>';
			}
			else if($status=="Encoding") {
				echo '<tr>
				<td class="text-left">'.$movie_files[$key].'.mp4
				<div class="progress"><div class="progress-bar progress-bar" role="progressbar" style="width:'.$progress.'%">'.$progress.'%</td>';
			}
			else if($status=="No alarms") {
				echo '<tr>
				<td class="text-left">'.$movie_files[$key].'.mp4
				<div class="progress"><div class="progress-bar progress-bar-warning" role="progressbar" style="width:100%">No Alarms</td>';	
			}
			else if($status=="Error") {
				echo '<tr>
				<td class="text-left">'.$movie_files[$key].'.mp4
				<div class="progress"><div class="progress-bar progress-bar-danger" role="progressbar" style="width:100%">Error - See Log</td>';
			}
			echo '
			<td>'.$size.'</td>
			<td>'.$duration.'</td>
			<td><a href="'.$movie_path.'/'.$movie_files[$key].'.txt"><span class="glyphicon glyphicon glyphicon-menu-hamburger"></a></span></td>
			<td><a href="'.$movie_path.'/'.$movie_files[$key].'.log"><span class="glyphicon glyphicon glyphicon-comment"></a></span></td>
			<td><button type="button" class="btn btn-default btn-sm" onclick=delkill("'.$movie_path.'/'.$movie_files[$key].'")><span class="glyphicon glyphicon glyphicon-trash"></span></button></td>
			</tr>';
		}
	}
}
function Delete_Movie($movie) {
	$encode = shell_exec("ps -ef | grep ".$movie.".mp4 | grep -v grep | awk '{print $2}'");
	$encode=explode("\n",$encode);
	echo '<script>alert(movie)</script>';
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
		unlink($movie.".progress");
	}
}
// Main php here
// Called function from url
// Initialize variable $function_call
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
if($function_call == "Delete_Movie") {
	Delete_Movie($_GET["movie"]);
}
if($function_call == "Load_Camera") {
	Load_Camera();
}
	
?>
