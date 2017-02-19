<?php
//
// paramerets $MonitorId,$Starttime,$Endtime,$Buffer,$Speed,$MultiplierX,$Frametype,$Codec,$Size,$Profile,$Preset,$CRF,$Maxtime,$Filename
//
$go_encode=0;
$dump_data=var_dump($argv);
$fp = fopen("dump.txt", "w");
fwrite($fp, $dump_data);
fclose($fp);
if (defined('STDIN') && (isset($argc) && $argc == 14)) {
	$MonitorId_all=explode(',',$argv[1]); // Camera Id's separated by comma, stored in array	
	$Starttime=$argv[2]; // format: '2015-01-15 07:00' or '-24'
	$Endtime=$argv[3]; // format: '2015-01-15 07:00' or '-12'
	$Buffer=$argv[4]; // seconds, 0 means alarm frames only
	$Speed=$argv[5]; // 2 means 2X, 3 = 3x. etc...
	$MultiplierX=$argv[6]; // Drop frames
	$Frametype=$argv[7]; // Alarm or All
	$Size=$argv[8]; // 1920:1080, 1280:720, 640:480, 320:200
	$Profile=$argv[9]; // ffmpeg profile Baseline Main or High 
	$Preset=$argv[10]; // ffmpeg Preset slow fast etc... 
	$CRF=$argv[11]; // ffmpeg CRF 0 (lossless) to 51 (worst)
	$Maxtime=$argv[12]; // do not exceed time (in mins) per movie
	$Filename=$argv[13]; //Filename
	$go_encode=1;
} elseif (count($_GET) == 14) {
	$MonitorId_all=explode(',',$_GET["MonitorId_all"]); // Camera Id's separated by comma, stored in array	
	$Starttime=$_GET["Starttime"]; // format: '2015-01-15 07:00' or '-24'
	$Endtime=$_GET["Endtime"]; // format: '2015-01-15 07:00' or '-12'
	$Buffer=$_GET["Buffer"]; // seconds, 0 means alarm frames only
	$Speed=$_GET["Speed"]; // 2 means 2X, 3 = 3x. etc...
	$MultiplierX=$_GET["MultiplierX"]; // Drop frames
	$Frametype=$_GET["Frametype"]; // Alarm or All
	$Size=$_GET["Size"]; // 1920:1080, 1280:720, 640:480, 320:200
	$Profile=$_GET["Profile"]; // ffmpeg profile Baseline Main or High 
	$Preset=$_GET["Preset"]; // ffmpeg Preset slow fast etc... 
	$CRF=$_GET["CRF"]; // ffmpeg CRF 0 (lossless) to 51 (worst)
	$Maxtime=$_GET["Maxtime"]; // do not exceed time (in mins) per movie
	$Filename=$_GET["Filename"]; //Filename
	$go_encode=1;
}
if($go_encode ==1) {
//
// Determine Starttime and Endtime
	if(is_numeric($Endtime)) {
		$Endtime = date('Y-m-d H:i', strtotime($Endtime.' hours')); 
	}
	if(is_numeric($Starttime)) {
		$Starttime = date('Y-m-d H:i', strtotime($Starttime.' hours')); 
	}
	echo $Endtime;
	echo $Starttime;
// Iterate through each camera

	foreach($MonitorId_all as $eyed) {
		$MonitorId = $eyed;

// Read from etc/zm/zm.conf (ubuntu) or etc/zm.conf (centos)
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
//
		define('ZM_HOST', $config['ZM_DB_HOST']);
		define('ZMUSER', $config['ZM_DB_USER']);
		define('ZMPASS', $config['ZM_DB_PASS']);
		define('ZM_DB', $config['ZM_DB_NAME']);
//
// Set target folder for movies generated, defaults to folder where script resides
//		define('PATH_TARGET',getcwd());
		$movie_path=""; //use local folder if not set
		$myfile = fopen("zm_movie_path.txt", "r") or die("Unable to open zm_movie_path.txt");
		$movie_path=fgets($myfile);
		$movie_path=str_replace(array("\r", "\n"), '', $movie_path);
		fclose($myfile);
		define('PATH_TARGET',getcwd()."/".$movie_path);
//
// Get DIR_EVENTS from database
		$con=mysqli_connect(ZM_HOST,ZMUSER, ZMPASS, ZM_DB);
		$result = mysqli_query($con, "SELECT Value FROM Config WHERE Name = 'ZM_DIR_EVENTS'");
		while($row = mysqli_fetch_assoc($result)) {
			$Dir_Events=end($row);
		}
// Get Camera Name from database
		$result = mysqli_query($con, "SELECT Name FROM Monitors WHERE Id=$MonitorId");
		while($row = mysqli_fetch_assoc($result)) {
		        $Mon_Name=end($row);
	        }
// If multiple monitors from command line then add id, timestamps to filename	
		echo "Length" . count($MonitorId_all);
		if(count($MonitorId_all) > 1) {		
			$Filename = $Mon_Name.date('Y-m-d_H-i', strtotime($Starttime)).'.mp4'; }
		else if(count($MonitorId_all) == 1) {
			$Filename = $Filename.'.mp4';
		}
		mysqli_close($con);
		define('PATH_EVENT', $config['ZM_PATH_WEB'].'/'.$Dir_Events);
//
// parameters $MonitorId,$Starttime,$Endtime,$Buffer,$Speed,$MultiplierX,$Frametype,$Size,$Filename,$Profile,$Preset,$CRF,$Maxtime,$Filename
		if(file_exists($Filename)) {
			$Filename=basename($Filename, ".".pathinfo($Filename,PATHINFO_EXTENSION)).'_1.'.pathinfo($Filename,PATHINFO_EXTENSION); 
		}
// Parse filename extension
		$Filename_base=basename($Filename, ".".pathinfo($Filename,PATHINFO_EXTENSION)); 
// open log file
		$path_target = PATH_TARGET;
		$zm_movie_log = fopen("$path_target/$Filename_base.log","w") or die(' Unable to open log file');
// Is ffmpeg or avconv installed
		exec("which avconv",$ffmpeg);
		if (empty($ffmpeg)) {
			exec("which ffmpeg",$ffmpeg);
			if (empty($ffmpeg)) {
				fwrite($zm_movie_log,"fmpeg or avconv not found!".PHP_EOL);
				die("ffmpeg or avconv not found!".PHP_EOL);
			}
		}
// Reset variables when executing multiple movies
		$Speed=$argv[5]; // 2 means 2X, 3 = 3x. etc...
		$MultiplierX=$argv[6]; // Drop frames
// Create log
		fwrite($zm_movie_log,"Starting Movie: $Filename Id:$MonitorId".PHP_EOL."Start: $Starttime".PHP_EOL."End: $Endtime".PHP_EOL."Buffer: $Buffer".PHP_EOL."Speed $Speed".PHP_EOL."MultiplierX: $MultiplierX".PHP_EOL."Frames:$Frametype".PHP_EOL."Size:$Size".PHP_EOL."Profile: $Profile".PHP_EOL."Preset: $Preset".PHP_EOL."CRF: $CRF".PHP_EOL);
// Get DIR_EVENTS from database
// open database
		$con=mysqli_connect(ZM_HOST,ZMUSER,ZMPASS,ZM_DB);
		if (mysqli_connect_errno()) {
		        echo "Failed to connect to MySQL: " . mysqli_connect_error();
			fwrite($zm_movie_log,"Failed to connect to MySQL".PHP_EOL);
		}
// Get bulk frame interval needed to expand them (used later)
		$result = mysqli_query($con, "SELECT Value FROM Config WHERE Name = 'ZM_BULK_FRAME_INTERVAL'");
		while($row = mysqli_fetch_assoc($result)) {
			$Bulk_frame_interval=end($row);
		}
// Calculate FPS of Camera (required for IP cams). Done in SQL. Use first EventId. Assumed Constant.
		$result = mysqli_query($con, "SELECT round(Frames/Length) FROM Events WHERE MonitorId=$MonitorId AND StartTime>= '$Starttime' limit 1");
		while($row = mysqli_fetch_assoc($result)) {
	        	$fps=end($row);
		}
// Get significant digits for naming files 'EVENT_IMAGE_DIGITS'
		$result = mysqli_query($con, "SELECT Value FROM Config WHERE Name = 'ZM_EVENT_IMAGE_DIGITS'");
		while($row = mysqli_fetch_assoc($result)) {
		        $Event_image_digits=end($row);
		}
// Store initial Starttime for naming video later
		$Video_start = $Starttime;
// open file for dumping frames path to be used as input to mencoder (or ffmpeg)
		$list1 = fopen("$path_target/$Filename_base.txt","w") or die(' Unable to open tmp file');
		if ($Frametype == 'Alarm') {
	// Find all events with first/last timestamps of consecutive alarm frames between requested time for requested monitor
	// Unset Alarm_list for multiple cameras as arguments 
			unset($Alarm_list);
			$result = mysqli_query($con,"SELECT EventId, FrameId, Type, TimeStamp, StartTime, EndTime, MonitorId FROM Frames, Events WHERE Frames.Type='Alarm' AND 	Events.Id=Frames.EventId AND Frames.TimeStamp >= '$Starttime' AND Frames.Timestamp <= '$Endtime' AND MonitorId=$MonitorId");
			$FrameId_Before = 0;
			$FrameData_Before = array('EventId','FrameId','Type','TimeStamp','StartTime','EndTime','MonitorId');
			$FrameData_Before = array_fill_keys($FrameData_Before,'');
			$i=0;
			$j=0;
			$Alarm_Start = 0;
			while($row = mysqli_fetch_assoc($result)) {
			        $FrameId = intval($row['FrameId']);
			        $FrameData = $row;
			        if ( $FrameId_Before + 1 != $FrameId) {
			                $j=$i+1;
			                $Alarm_list[$i]['EndTime'] = $FrameData_Before['TimeStamp'];
			                $Alarm_list[$j]['StartTime'] = $FrameData['TimeStamp'];
			                $Alarm_list[$j]['PathTime'] = $FrameData['StartTime'];
			                $Alarm_list[$j]['FrameId'] = $FrameId;
			                $Alarm_list[$i]['EventId'] = $FrameData_Before['EventId'];
			                $Alarm_list[$i]['MonitorId'] = $FrameData_Before['MonitorId'];
			                $i++;}
				        $FrameId_Before = $FrameId;
				        $FrameData_Before = $FrameData;
				}
	// Dump last end alarm frame in array to complete the new array
			$Alarm_list[$i]['EndTime'] = $FrameData['TimeStamp'];
			$Alarm_list[$i]['EventId'] = $FrameData['EventId'];
			$Alarm_list[$i]['MonitorId'] = $FrameData['MonitorId'];
	// Debugging purposes
	// var_dump($Alarm_list);
	//
	// Iterate through each alarm and add buffer time before and after discrete alarm events
			for($i = 1; $i < count($Alarm_list); $i++) {
			        $NewEndtime = strtotime($Alarm_list[$i]['EndTime'])+$Buffer;
			        $NewStartTime = strtotime($Alarm_list[$i]['StartTime'])-$Buffer;
			        $Alarm_list[$i]['EndTime'] = date('Y-m-d H:i:s', $NewEndtime);
			        $Alarm_list[$i]['StartTime'] = date('Y-m-d H:i:s', $NewStartTime);
			}
	// Eliminate overlap of alarms (if applicable) by moving start time of next alarm event to end time of previous
			for($i = 1; $i < count($Alarm_list)-1; $i++) {
			        if ($Alarm_list[$i+1]['StartTime'] <= $Alarm_list[$i]['EndTime']) {
			                $NewStarttime = strtotime($Alarm_list[$i]['EndTime'])+1;
			                $Alarm_list[$i+1]['StartTime'] = date('Y-m-d H:i:s', $NewStarttime);
			        }
			}
			fwrite($zm_movie_log,"Distinct alarm events found: ".count($Alarm_list).PHP_EOL);
	// Debugging purposes
	// var_dump($Alarm_list);
			}
// If 'All' argument is used for generating the movie simply substitute initial time arguments for one big event (all frames between times)
// Add a buffer to account for any bulk frames before/after requested time
		else if ($Frametype == 'All') {
			unset($Alarm_list);
			$Buffer=$Bulk_frame_interval/$fps+ $MultiplierX/$fps;
			$NewEndtime = strtotime($Endtime)+$Buffer;
			$NewStartTime = strtotime($Starttime)-$Buffer;
			$Alarm_list[1]['EndTime'] = date('Y-m-d H:i:s', $NewEndtime);
			$Alarm_list[1]['StartTime'] = date('Y-m-d H:i:s', $NewStartTime);
			$Alarm_list[1]['MonitorId'] = $MonitorId;
// Must set additional index for counter to work below
		        $Alarm_list[2]['StartTime'] = '';
		        $Alarm_list[2]['EndTime'] = '';
		        $Alarm_list[2]['MonitorId'] = '';
		}
// Iterate through each alarm event and dump path and image name to input file for movie maker
// For 'All' frames this is simply one event
		$last_row='';
		$last_row_type='';
		for($j = 1; $j < count($Alarm_list); $j++)
		{
		        $EventStartTime = $Alarm_list[$j]['StartTime'];
		        $EventEndTime = $Alarm_list[$j]['EndTime'];
		        $MonitorId = $Alarm_list[$j]['MonitorId'];
		        $result = mysqli_query($con,"SELECT EventId, FrameId, Type, StartTime, MonitorId FROM Frames, Events WHERE Events.Id=Frames.EventId AND Frames.TimeStamp 	>= 	'$EventStartTime' AND Frames.TimeStamp <= '$EventEndTime' AND MonitorId=$MonitorId");
		        while($row = mysqli_fetch_assoc($result))
		        {
		                $DP = date_parse($row['StartTime']);
		                $yy=substr($DP['year'],2);
		                $yy=sprintf("%02d",$yy);
		                $mm=sprintf("%02d",$DP['month']);
		                $dd=sprintf("%02d",$DP['day']);
		                $hh=sprintf("%02d",$DP['hour']);
		                $min=sprintf("%02d",$DP['minute']);
		                $ss=sprintf("%02d",$DP['second']);
	// Write frames to file
	// Expand bulk frames if present
	// Pad event name to match zoneminder setting
		                if ($row['FrameId'] - $last_row == 1)
		                {
					$FrameId = str_pad($row['FrameId'],$Event_image_digits,'0',STR_PAD_LEFT);
		                        $Event = PATH_EVENT."/".$MonitorId."/".$yy."/".$mm."/".$dd."/".$hh."/".$min."/".$ss."/".$FrameId."-capture.jpg".PHP_EOL;
		                        fwrite($list1,$Event);
		                }
		                else if (($row['FrameId'] - $last_row) > 1 AND ($row['FrameId'] - $last_row) <= $Bulk_frame_interval)
		                {
		                        for ($i=$last_row+1; $i <= $row['FrameId']; $i++)
		                        {
						$FrameId = str_pad($i,$Event_image_digits,'0',STR_PAD_LEFT);
					        $Event = PATH_EVENT."/".$MonitorId."/".$yy."/".$mm."/".$dd."/".$hh."/".$min."/".$ss."/".$FrameId."-capture.jpg".PHP_EOL;
		                                fwrite($list1,$Event);
		                        }
		                }
		                $last_row=$row['FrameId'];
		        }
		}
		fclose($list1);
// calculate and log duration of movie in minutes
// Reset speed and multiplier
		$Speed=$argv[5];
		$MultiplierX=$argv[6];
		$length=intval(exec("wc -l $path_target/$Filename_base.txt"));
		$movie_actual=round($length/$fps/60,1);
		$movie_duration=round($length/$fps/60/$MultiplierX/$Speed,1);
// Calculate the total speed x multiplier to meet the maximum movie length
		while($movie_duration > $Maxtime && $MultiplierX <=100 && $Maxtime > 0) { 
			if($Speed >= 50) {
				$MultiplierX = $MultiplierX + 1; 
				$Speed = 1; }
			else {
				$Speed = $Speed + 1; } 
			$movie_duration=round($length/$fps/60/$MultiplierX/$Speed,1);
		}  
//
		fwrite($zm_movie_log,"Maxtime: $Maxtime".PHP_EOL);
		fwrite($zm_movie_log,"Recalculated speed: $Speed".PHP_EOL);
		fwrite($zm_movie_log,"Recalculated MultiplierX: $MultiplierX".PHP_EOL);
		fwrite($zm_movie_log,"Movie_duration(min) = $movie_duration".PHP_EOL);
		fwrite($zm_movie_log,"Actual_duration(min) = $movie_actual".PHP_EOL);

// Make movie (avconv/ffmpeg)
// Skip frames for timelapse or if set remove enough frames to meet time limite
		if($MultiplierX > 1) {
			$skip_frames=$MultiplierX;
			$arguments = "awk 'NR%$skip_frames==0{print \$0}' ".PATH_TARGET."/$Filename_base.txt > ".PATH_TARGET."/$Filename_base.txt.tl";
			exec($arguments);
			copy(PATH_TARGET."/$Filename_base.txt.tl", PATH_TARGET."/$Filename_base.txt");
			unlink(PATH_TARGET."/$Filename_base.txt.tl");
		}
// Calculate requested speed of movie
		$fps =$fps*$Speed;

// set parameters
		$date1=explode(" ",$Video_start);
		$video_file=$Filename;
		$Extension=pathinfo($Filename);
		$Size=explode(":",$Size);
		$Width=$Size[0];
//
		$encoder_param = "x=0; while read CMD; do cat \$CMD; x=$((x+1)); echo \$x > ".PATH_TARGET."/".$Filename_base.".progress;  done < ".PATH_TARGET."/".$Filename_base.".txt | " .$ffmpeg[0] . " -r ".$fps." -f image2pipe -vcodec mjpeg -i - -profile:v ".$Profile." -preset:v ".$Preset." -threads 0 -crf ".$CRF. " -vf scale=" . $Width. ":-1 " .PATH_TARGET."/".$video_file." -y";

		fwrite($zm_movie_log,$encoder_param.PHP_EOL);
//
		if(filesize(PATH_TARGET."/".$Filename_base.".txt") > 0) {
			$pid=exec("($encoder_param)  & echo $!");
			fwrite($zm_movie_log,"PID = ".$pid.PHP_EOL); 
		}
		else {
			fwrite($zm_movie_log,"PID = None".PHP_EOL); 
			fwrite($zm_movie_log,"*** No events found ***".PHP_EOL);
			file_put_contents($video_file, 'see log');
		}
		fwrite($zm_movie_log,"---------------------------------------".PHP_EOL);
		fclose($zm_movie_log);
	} // end of foreach($MonitorId_all as $MonitorId) loop
}
else {
	echo "Usage:".PHP_EOL;
	echo "CameraId Starttime Endtime Buffer(s) Speed(X) SkipFrames Frametype(All, Alarm) Size(1280:720) Profile(Main) Preset(Fast) CRF(1-51) Maxtime(min 0=disabled) Filename".PHP_EOL;
	echo "Example:".PHP_EOL;
	echo "php zm_mca_01.php 1,2,3 '2015-01-15 07:00' '2015-01-15 07:00' 30 2 1 Alarm 1280:720 Main Fast 28 5 camera1".PHP_EOL; 
	echo "or".PHP_EOL; 
	echo "php zm_mca_01.php 1,2,3 '-24' '-12' 30 2 1 Alarm 1280:720 Main Fast 28 5 camera1".PHP_EOL; 
}
?>

