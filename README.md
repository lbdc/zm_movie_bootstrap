# zm-movie-bootstrap

Make zoneminder movies from Zoneminder 1.36 passthrough
by entering start and end periods, parameters such as speed, fps, size etc.
Option to only export all alarms between period (if using mocord) in one continous movie.

Tested on Ubuntu 20.04 and latest zoneminder 1.36

Index screenshot

![](https://github.com/lbdc/zm_movie_bootstrap/blob/master/Index.png)

Make Movie screenshot

![](https://github.com/lbdc/zm_movie_bootstrap/blob/master/Make_movie.png)
*************
Files:
*************
zm_alm_136.php: encode engine uses ffmpeg. Can run script from command line directly.

Other files required for HTML front end

index.php: Use this as index

zm_movie_functions.php: miscellaneous functions called by scripts

zm_movie_header.html: html header information

zm_movie_make.php: setup movie parameters
*************
Installation:
*************
Put all files in a subfolder to /var/www/html

e.g. /var/www/html/zm136/

chown -R www-data:www-data /var/www/html/zm136/ (or web user)

chmod -R 775 /var/www/html/zm134/

Point your browser to http://host:port/zm136/index.php and enjoy

Note: the script will create 2 subfolders zm134/zm_movie for storing movies and zm134/zm_tmp for temporary files.
Ensure the storage area is set to either default or other. If blank, go to camera settings and set to default.

![](https://github.com/lbdc/zm_movie_bootstrap/blob/master/storage.png)

TODO:
- Add date/time picker

