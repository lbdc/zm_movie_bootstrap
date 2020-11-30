<!DOCTYPE html>
<html lang="en">
<head>
<?php
	include 'zm_movie_header.html';
?>
<style>
.progress {
	margin-bottom: 1px !important; }
</style>
</head>
<body>
<h1></h1>
<?php
//	include 'navbar.html';
?>
<div class="container">
	<button class="btn btn-primary" type="button" id="mmovie">Make Movie</button>
</div>
<div id="progressDiv"></div>
<script>
// ajax update table interval script
var refreshId = setInterval(function() {refresh_table(); }, 5000); 
var iterations=0;
refresh_table();
function refresh_table()
{
	iterations++;
	if (iterations >=500) {
		clearInterval(refreshId);
		alert("Timeout...Please refresh page to continue");
	}
        var xmlhttp;
        if (window.XMLHttpRequest)
        {// code for IE7+, Firefox, Chrome, Opera, Safari
                xmlhttp=new XMLHttpRequest();
        }
        else
        {// code for IE6, IE5
                xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange=function()
        {
                if (xmlhttp.readyState==4 && xmlhttp.status==200)
                {
                        document.getElementById("progressDiv").innerHTML=xmlhttp.responseText;
                }
        }

        xmlhttp.open("GET","zm_movie_functions.php?f=Movie_Update&d="+new Date().getTime(),true);
        xmlhttp.send();
}
function delkill(movie) {
	var xhttp = new XMLHttpRequest();
	xhttp.onreadystatechange = function() {
		if (xhttp.readyState == 4 && xhttp.status == 200) {
			refresh_table();
		}
	};
	xhttp.open("GET", "zm_movie_functions.php?f=Delete_Movie&movie="+ movie, true);
	xhttp.send();
}
$('#mmovie').on('click', function(){    
	window.open("zm_movie_make.php");
})
</script>
</body>
</html>
