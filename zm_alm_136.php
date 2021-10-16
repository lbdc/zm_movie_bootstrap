<?php
//
// php make movie zm 1.36 with passthrough
// Tested on Ubuntu 20.04
//------------------------
// set relative location of temporary and movie folder 
// and create them if not exist
//------------------------
$path_tmp="zm_tmp";
$path_movie="zm_movie";
if(is_dir($path_tmp) === false )
{
    mkdir($path_tmp);
}
if(is_dir($path_movie) === false )
{
    mkdir($path_movie);
}
//------------------------
// get command line options
//------------------------
$shortopts  = "";
$longopts  = array(
	"id:",     // Required option camera: Id e.g. 1
	"start:",    // Required option start time: e.g. "2019-07-02 09:00:00"
	"end:",    // Required option end time: e.g. "2019-07-02 10:00:00"
	"speed:",    // optional option speed: e.g. 2. If no speed ig given, default 1
	"fps:",    // optional option fps: e.g. 15. If no fps is given, default to camera fps
	"size:",    // optional option e.g. 1980:1080. If no size is given, default to camera size   
	"buffer:",    // Required option: in seconds e.g. 10
	"file:",    // Required option: video filename e.g. "front door" 
	"render:",    // Optional option: render device. Currently only works with VAAPI e.g. "/dev/dri/render D128"
	"frame:",    // Required option: Frame type "Alarm" or "All" e.g. "All"
);
$options = getopt($shortopts,$longopts);
//var_dump($options);
// create patterns for verification
$regex_time = "/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1]) (0[0-9]|1[0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/";
$regex_int = "/[1-9][0-9]*/";
$regex_file = "/^([^.]+)$/";
// Verify options (required and optional)
// initialize error variable. Set on any error
$exit = NULL;
if(isset($options["id"])) { if(!preg_match($regex_int,$options["id"])) { $exit = $exit . "Verify id parameter\n"; } } else { $exit = $exit . "Missing id parameter\n";}
if(isset($options["buffer"])) { if(!preg_match($regex_int,$options["buffer"])) { $exit = $exit . "Verify buffer parameter\n"; } } 
if(isset($options["start"])) { if(!preg_match($regex_time,$options["start"])) { $exit = $exit . "Verify start time parameter\n"; } } else { $exit = $exit . "Missing start time parameter\n";}
if(isset($options["end"])) { if(!preg_match($regex_time,$options["end"])) { $exit = $exit . "Verify end time parameter\n"; } } else { $exit = $exit . "Missing end time parameter\n";}
if(isset($options["file"])) { if(!preg_match($regex_file,$options["file"])) { $exit = $exit . "Verify file parameter\n"; } } else { $exit = $exit . "Missing file parameter\n";}
if(isset($options["speed"])) { if(!preg_match($regex_int,$options["speed"])) {$exit = $exit . "Verify speed optional parameter\n";}}
if(isset($options["fps"])) { if(!preg_match($regex_int,$options["fps"])) {$exit = $exit . "Verify speed optional parameter\n";}}
if(isset($options["size"])) { if(!($options["size"] == "1920:1080" || $options["size"] == "1280:720" || $options["size"] == "800:600" || $options["size"] == "640:480" || $options["size"] == "320:240")) {$exit = $exit . "Verify speed optional parameter\n";}}
if(isset($options["frame"])) { if(!($options["frame"] == "Alarm" || $options["frame"] == "All")) {$exit = $exit . "Verify frame parameter\n";}} else { $exit = $exit . "Missing frame parameter\n";}
//If any error encounter, exit and print error/parameters
if(isset($exit)) {
	exit($exit . "
Required Parameters
--id     Camera Id          e.g. --id 1
--start  Start time         e.g. --start \"2019-07-02 09:00:00\"
--end    End time           e.g. --end \"2019-07-02 18:00:00\"
--file   Video filename     e.g. --file Movie2-Front
--frame  Frame type	     e.g. --frame All or --frame Alarm 
Optional Parameters
--buffer Buffer in seconds  e.g. --buffer 10 (Minimum 2s for ffmpeg to extract short alarms of a few frames)
--speed  Speed              e.g. --speed 2 (Defaults 1)
--fps    Frame per Seconds  e.g. --fps 10 (Defaults camera FPS)
--size   Size of video	    e.g. --size 1980:1080 (Defaults to camera)
--render Render device	    Currently only works with VAAPI e.g. \"/dev/dri/renderD128\"
");
}
// Set default parameters
// Buffer must be at least a few seconds for ffmpeg to extract video.
if(!isset($options["buffer"])) { $options["buffer"] = 2;}
//------------------------
// Open log file
//------------------------
$log_file = $path_movie . "/" . $options["file"] . ".log";
// show parameters in log
$log_text_ini = "Parameters: \n" . print_r($options, true);
file_put_contents($log_file,$log_text_ini);
//------------------------
// open zoneminder configuration
//------------------------
if(file_exists("/etc/zm/zm.conf")) {
	$ini_file='/etc/zm/zm.conf';}
else if(file_exists("/etc/zm.conf")) {
	$ini_file='/etc/zm.conf';}
else { 
	file_put_contents($log_file, "No zoneminder configuration zm.conf found\n", FILE_APPEND);
	exit("No zoneminder configuration zm.conf found");}
// Parse ini file the long way (PHP deprecated # as comments in ini files)
$file = fopen($ini_file, "r");
while(!feof($file)) {
	$line = fgets($file);
	if(preg_match("/(?<=ZM_DB_USER=).*/",$line,$zm_user)) {define('ZMUSER', $zm_user[0]);}
	if(preg_match("/(?<=ZM_DB_HOST=).*/",$line,$zm_host)) {define('ZM_HOST', $zm_host[0]);}
	if(preg_match("/(?<=ZM_DB_PASS=).*/",$line,$zm_pass)) {define('ZMPASS', $zm_pass[0]);}
	if(preg_match("/(?<=ZM_DB_NAME=).*/",$line,$zm_db)) {define('ZM_DB', $zm_db[0]);}
}
fclose($file);
//------------------------
// Connect to ZM DB and query database
//------------------------
$con=mysqli_connect(ZM_HOST,ZMUSER, ZMPASS, ZM_DB);
if (mysqli_connect_errno()) {
	file_put_contents($log_file, "Failed to connect to MySQL\n", FILE_APPEND);
	echo "Failed to connect to MySQL: " . mysqli_connect_error();
}
// Set query parameters
$sql_arg_start = $options["start"];
$sql_arg_end = $options["end"];
$sql_arg_id = $options["id"];
//------------------------
// Alarm frames query. Could have used a function I guess
//------------------------
if($options["frame"] == "Alarm")
{
// Query1 is required to initialize variable
	$SQL_Query1 = "SET @curRow=0;";
// Query2 gets the min and max times of each alarm sequence ouf!
	$SQL_Query2 = 
	"SELECT min(Timestamp) as min,max(Timestamp) as max, Delta, StartDateTime, EndDateTime, MonitorId, EventId, StorageId,Path, max(Timestamp)-min(Timestamp) as s from (SELECT Timestamp, Id, Delta, StartDateTime, EventId,EndDateTime, MonitorId, Type, StorageId, Path, @curRow := @curRow + 1 AS row_number FROM (SELECT Timestamp, Frames.Id, EventId, Delta, StartDateTime, EndDateTime, MonitorId, Frames.Type, StorageId, Storage.Path FROM Frames, Events, Storage WHERE Frames.Type='Alarm' AND Events.Id=Frames.EventId AND Events.StorageId=Storage.Id AND Frames.TimeStamp >= '$sql_arg_start' AND Frames.Timestamp <= '$sql_arg_end' AND MonitorId='$sql_arg_id' GROUP BY Timestamp) as q) as r GROUP BY Timestamp - row_number ORDER BY min(Timestamp) ASC;";
	echo "Executing database query...\n";
	mysqli_query($con,$SQL_Query1);
	$result = mysqli_query($con,$SQL_Query2);
//------------------------
// Process query
//------------------------
	if ( mysqli_num_rows($result) == 0) {
  // no results found ;
		file_put_contents($log_file, "MySQL Query - No alarms found\n", FILE_APPEND);
		exit("MySQL Query - No alarms found\n");
	}
// parse results of query into array
	while($row = mysqli_fetch_assoc($result)) {
		$mon_Events[]=$row;
	}
	mysqli_close($con);
// Iterate through array $mon_Events. 
// Add buffer to start and end time.
	for($i = 0; $i < count($mon_Events); $i++) {
		$mon_Events[$i]["min"] = date("Y-m-d H:i:s", strtotime($mon_Events[$i]["min"]) - $options["buffer"]);
		$mon_Events[$i]["max"] = date("Y-m-d H:i:s", strtotime($mon_Events[$i]["max"]) + $options["buffer"]);
		if($mon_Events[$i]["min"] < $mon_Events[$i]["StartTime"]) { $mon_Events[$i]["min"] = $mon_Events[$i]["StartTime"]; }
		if($mon_Events[$i]["max"] > $mon_Events[$i]["EndTime"]) { $mon_Events[$i]["max"] = $mon_Events[$i]["EndTime"]; }
//	echo $mon_Events[$i]["min"] . " --- " . $mon_Events[$i]["max"] . "\n";
	}
//var_dump($mon_Events);
//Replace time fields as required when alarm times overlap
	$compte = count($mon_Events);
	for($i = 1; $i < $compte ; $i++) {
		if($mon_Events[$i]["min"] <= $mon_Events[$i-1]["max"]) 
		{
//			echo "overlap\n";
			$mon_Events[$i]["min"] = $mon_Events[$i-1]["min"];
			unset($mon_Events[$i-1]);
		}
	}
	// Count events
	$count_event = count($mon_Events);
//------------------------
// Dump list of events to ffmpeg input text file
// and prepare alarm video segments in temporary folder
//------------------------
	echo "\nExtracting alarm segments Videos with FFMPEG...\n";
	$file = fopen($path_movie . "/" . $options["file"] . ".txt","w+") or die("Unable to create file");
	$name_i = 0;
	$movie_length = 0;
	if(!file_exists($path_tmp . "/" . $options["file"])) {
		mkdir($path_tmp . "/" . $options["file"]);}
	foreach ($mon_Events as $value) {
// Calculate start and end of alarm segment		
		$diff_start = strtotime($value["min"]) - strtotime($value["StartTime"]);
		$diff_end = strtotime($value["max"]) - strtotime($value["min"]);
//	echo $value["EventId"] . "---". $value["min"] . " -a- " . $value["max"] . "\n";
// 	explode date to get into filesystem storage (zoneminder uses date as folders)
		$date = explode(" ",$value["StartTime"]);
		$videos = $value["Path"] . "/" . $value["MonitorId"] . "/" . $date[0] . "/" . $value["EventId"] . "/" . $value["EventId"] . "-video.mp4";
// 	Extract video segment with ffmpeg
		$ffmpeg_command = "ffmpeg -y -loglevel fatal -ss " . $diff_start . " -i " . $videos  . " -t " . $diff_end . " -c copy -map 0 " . $path_tmp . "/" . $options["file"] . "/" . $value["EventId"] . "_" . $name_i . ".mp4\n";
//		echo $ffmpeg_command;
		echo "Processing record " . ($name_i+1) . " of $count_event\r";
		shell_exec($ffmpeg_command);
//	Write video segment in ffmpeg input file for future concantenate
		$vid = "../" . $path_tmp . "/" . $options["file"] . "/" . $value["EventId"] . "_" . $name_i . ".mp4";
		$string = "file '" . $vid . "'\n";
		$name_i++;
		fwrite($file, $string);
	// Add time of each video segment to be used for progress of ffmpeg concat progress
		$movie_length = $movie_length + (strtotime($value["max"]) - strtotime($value["min"]));
	}
	fclose($file);
}
//------------------------
// All Frames query
//------------------------
// Query2 gets the min and max times of each alarm sequence
if($options["frame"] == "All")
{
	$SQL_Query2 = "SELECT MonitorId, Events.Name,EventId, StartDateTime, EndDateTime,StorageId, Storage.Path FROM Storage,Frames, Events WHERE Events.Id=Frames.EventId AND Events.StorageId=Storage.Id AND Frames.TimeStamp >= '$sql_arg_start' AND Frames.Timestamp <= '$sql_arg_end' AND MonitorId=$sql_arg_id GROUP BY EventId";
	echo "Executing database query...\n";
	$result = mysqli_query($con,$SQL_Query2);
//------------------------
// Process query
//------------------------
	if ( mysqli_num_rows($result) == 0) {
  // no results found ;
		file_put_contents($log_file, "MySQL Query - No events found\n", FILE_APPEND);
		exit("MySQL Query - No events found\n");
	}
// parse results of query into array
	while($row = mysqli_fetch_assoc($result)) {
		$mon_Events[]=$row;
	}
	mysqli_close($con);
	
// Remove spaces from event name Required?
// for ($i = 0; $i < count($mon_Events); $i++) {	
//	$mon_Events[$i]["Name"] = str_replace(' ', '', $mon_Events[$i]["Name"]);}

// Count events
	$count_event = count($mon_Events);
//------------------------
// Dump list of events to ffmpeg input text file
// Since this is a continuous movie, only the first and last events will
// be truncated and prepared in temporary folders. Others are full events
// and will be taken directly from zoneminder storage
//------------------------
	echo "\nExtracting video segments Videos with FFMPEG...\n";
	$file = fopen($path_movie . "/" . $options["file"] . ".txt","w+") or die("Unable to create file");
	$name_i = 0;
	if(!file_exists($path_tmp . "/" . $options["file"])) {
		mkdir($path_tmp . "/" . $options["file"]);}
// Process 1st video
// Remove spaces from event name
	$mon_Events[0]["Name"] = str_replace(' ', '', $mon_Events[0]["Name"]);
	$diff_start = strtotime($options["start"]) - strtotime($mon_Events[0]["StartTime"]);
	$diff_end = min(strtotime($options["end"]) - strtotime($options["start"]) , strtotime($mon_Events[0]["EndTime"]) - strtotime($options["start"]));
// 	explode date to get into filesystem storage (zoneminder uses date as folders)
	$date = explode(" ",$mon_Events[0]["StartTime"]);
	$videos = $mon_Events[0]["Path"] . "/" . $mon_Events[0]["MonitorId"] . "/" . $date[0] . "/" . $mon_Events[0]["EventId"] . "/" . $mon_Events[0]["EventId"] . "-video.mp4";
	$ffmpeg_command = "ffmpeg -y -loglevel fatal -ss " . $diff_start . " -i " . $videos  . " -t " . $diff_end . " -c copy -map 0 " . $path_tmp . "/" . $options["file"] . "/" . $mon_Events[0]["EventId"] . "_first.mp4\n";
	shell_exec($ffmpeg_command);
	$vid = "../" . $path_tmp . "/" . $options["file"] . "/" . $mon_Events[0]["EventId"] . "_first.mp4";
	$string = "file '" . $vid . "'\n";
	fwrite($file, $string);
// Dump event zoneminder path into ffmpeg in file
// This is for events between the first and last event reported
// If there is only 2 events, ignore this section and proceed to process last event
	for ($i = 1; $i < count($mon_Events)-1; $i++) {
// Remove spaces from event name
		$mon_Events[$i]["Name"] = str_replace(' ', '', $mon_Events[$i]["Name"]);
		// Truncate time
		$date = explode(" ",$mon_Events[$i]["StartTime"]);
		//echo string to file
		$videos = $mon_Events[$i]["Path"] . "/" . $mon_Events[$i]["MonitorId"] . "/" . $date[0] . "/" . $mon_Events[$i]["EventId"] . "/" . $mon_Events[$i]["EventId"] . "-video.mp4";
		$string = "file '" . $videos . "'\n";
		fwrite($file, $string);
	}
// Process last video if applicable (at least 3 events)
// Remove spaces from event name
	$last_event = $count_event-1;
	if($last_event >=2) {
		$mon_Events[$last_event]["Name"] = str_replace(' ', '', $mon_Events[$last_event]["Name"]);
		$diff_start = 0;
		$diff_end = strtotime($options["end"]) - strtotime($mon_Events[$last_event]["StartTime"]);
	// 	explode date to get into filesystem storage (zoneminder uses date as folders)
		$date = explode(" ",$mon_Events[$last_event]["StartTime"]);
		$videos = $mon_Events[$last_event]["Path"] . "/" . $mon_Events[$last_event]["MonitorId"] . "/" . $date[0] . "/" . $mon_Events[$last_event]["EventId"] . "/" . $mon_Events[$last_event]["EventId"] . "-video.mp4";
		$ffmpeg_command = "ffmpeg -y -loglevel fatal -ss " . $diff_start . " -i " . $videos  . " -t " . $diff_end . " -c copy -map 0 " . $path_tmp . "/" . $options["file"] . "/" . $mon_Events[$last_event]["EventId"] . "_last.mp4\n";
		shell_exec($ffmpeg_command);
		$vid = "../" . $path_tmp . "/" . $options["file"] . "/" . $mon_Events[$last_event]["EventId"] . "_last.mp4";
		$string = "file '" . $vid . "'\n";
		fwrite($file, $string);
	}
	fclose($file);	
	$movie_length = strtotime($options["end"]) - strtotime($options["start"]);
}
//------------------------
// Identify how many alarm instances will be processed.
// Give a chance to quit if too many alarms before proceeding.
// Increase buffer will reduce instances.
//------------------------
file_put_contents($log_file, "\n" . "Records found=" . $count_event . "\nPress CTRL-C to abort\n", FILE_APPEND);
echo "\n" . "Records found=" . $count_event . "\nPress CTRL-C to abort\n";
for ($i = 3; $i > 0; --$i) {
	echo $i;
	usleep(250000);
	echo '.';
	usleep(250000);
	echo '.';
	usleep(250000);
	echo '.';
	usleep(250000);
}
//------------------------
// Adjust movie length with speed adjustment
//------------------------
$movie_length = $movie_length/max($options["speed"],1);
echo "Estimated Movie Length min=" . round($movie_length/60,2) . "\n";
file_put_contents($log_file, "Estimated Movie Length min=" . round($movie_length/60,2) ."\n", FILE_APPEND);
//------------------------
// Setup parameters for ffmpeg concantenate options size, speed and fps
//------------------------
if(isset($options["size"])) {
	$Size=explode(":",$options["size"]); 
	$Width=$Size[0];
	$scale = "scale=" . $Width . ":-1"; 
	$scale_vaapi = "scale_vaapi=w=" . $Width . ":h=-2";}
else {
	$scale_vaapi = "";
	$scale="";}
// Speed
if(isset($options["speed"])) {
	$speed=1/$options["speed"];
	$setpts = ",setpts=" . $speed . "*PTS";}
else {
	$setpts = "";}
// fps
if(isset($options["fps"])) {
	$fps = "-r " .  $options["fps"];}
else {
	$fps = "";}
// Concantenate variable into one string for FFMPEG complex filter
// Strip first comma of filter variable if required
$filter = $scale . $setpts;
$filter_vaapi = $scale_vaapi . $setpts;
// Strip commas of filter variable if required
// If filter is not empty, add parameters for ffmpeg
if(empty($filter) && empty($filter_vaapi)) {
	$filter = "";
	$filter_vaapi = ""; }
else {
	$filter = ltrim($filter,",");
	$filter_vaapi = ltrim($filter_vaapi,",");
	$filter = "-vf '" . $filter . "'";
	$filter_vaapi = "-vf '". $filter_vaapi . "'"; }
//
// Concantenate and reencode if necessary
//
// If speed is maintained and no other changes to size and fps no re-encoding necessary. Very fast. No progress will be shown.
file_put_contents($log_file, "Concantenate movie\n\n", FILE_APPEND);
echo "\nConcantenate movie.\n";
if(!(isset($options["speed"]) || isset($options["fps"]) || isset($options["size"]))) {
	$ffmpeg_command="ffmpeg -y -loglevel fatal -progress " . $path_movie . "/" . $options["file"] . ".pgs -f concat -safe 0 -i " . $path_movie . "/" . $options["file"] . ".txt -c copy " . $path_movie . "/" . $options["file"] . ".mp4";
	file_put_contents($log_file, "ffmpeg command: $ffmpeg_command\n\n", FILE_APPEND);
	echo $ffmpeg_command;
} else {
// Is VAAPI enabled
	if(isset($options["render"])) {
//		$ffmpeg_command = "ffmpeg -y -loglevel fatal -progress " . $path_movie . "/" . $options["file"] . ".pgs -f concat -safe 0 -hwaccel vaapi -hwaccel_device " . $options["render"] . " -hwaccel_output_format vaapi -i " . $path_movie . "/" . $options["file"] . ".txt " . $fps . " " . $filter_vaapi . " -c:v h264_vaapi " . $path_movie . "/" . $options["file"] . ".mp4 > /dev/null & echo $!";
		$ffmpeg_command = "ffmpeg -y -loglevel fatal -progress " . $path_movie . "/" . $options["file"] . ".pgs -f concat -safe 0 -hwaccel vaapi -hwaccel_device " . $options["render"] . " -hwaccel_output_format vaapi -i " . $path_movie . "/" . $options["file"] . ".txt " . $fps . " " . $filter_vaapi . " -c:v h264_vaapi " . $path_movie . "/" . $options["file"] . ".mp4";
		file_put_contents($log_file, "ffmpeg command: $ffmpeg_command\n\n", FILE_APPEND);
//		echo $ffmpeg_command;
	}
	else { // vaapi not enabled
//		$ffmpeg_command="ffmpeg -y -loglevel fatal -progress " . $path_movie . "/" . $options["file"] . ".pgs -f concat -safe 0 -i " . $path_movie . "/" . $options["file"] . ".txt " . $fps . " " . $filter . " " . $path_movie .  "/" . $options["file"] . ".mp4  > /dev/null & echo $!";
		$ffmpeg_command="ffmpeg -y -loglevel fatal -progress " . $path_movie . "/" . $options["file"] . ".pgs -f concat -safe 0 -i " . $path_movie . "/" . $options["file"] . ".txt " . $fps . " " . $filter . " " . $path_movie .  "/" . $options["file"] . ".mp4";
		file_put_contents($log_file, "ffmpeg command: $ffmpeg_command\n\n", FILE_APPEND);
//		echo $ffmpeg_command;
	}
}
shell_exec($ffmpeg_command);
//------------------------
// ffmpeg pid has finished. Check if sucessful and duration has completed
//------------------------
$ffmpeg_output = shell_exec('tail -11 ' . $path_movie . '/' . $options["file"] . '.pgs');
preg_match("/progress=end/", $ffmpeg_output, $pgs_match);
preg_match("/(?<=out_time_ms=).*/", $ffmpeg_output, $tm_match);
$completion = $tm_match[0]/($movie_length*1000000);
if($pgs_match) {
	echo "Video Success: " . $path_movie .  "/" . $options["file"] . ".mp4\n";
	file_put_contents($log_file, "Video Success: " . $path_movie .  "/" . $options["file"] . ".mp4\n", FILE_APPEND);
} else {	
	echo "Video did not complete $completion %\n";
	file_put_contents($log_file, "Video did not complete $completion %\n", FILE_APPEND);}
//------------------------
// Delete temp files
//------------------------
echo "Cleanup tmp files\n";
file_put_contents($log_file, "Cleanup tmp files\n", FILE_APPEND);
array_map('unlink', glob($path_tmp . "/" . $options["file"] . "/*.*"));
rmdir($path_tmp . "/" . $options["file"]);
?>
