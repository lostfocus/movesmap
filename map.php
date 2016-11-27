<?php
// here we go
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// show upload-form if no file is present
if (empty($_FILES)) : ?>

	<form action="map.php" method="POST" enctype="multipart/form-data">
		<input type="file" name="sourcefile" />
		<input type="submit" />
	</form>

<?php
// and die, die, die my darling
die();

// get content of uploaded file
else :
	$source = file_get_contents($_FILES["sourcefile"]["tmp_name"]);
endif;

// draw map
require_once("lib/point.php");
require_once("lib/line.php");
require_once("lib/map.php");

$storyline = json_decode($source);

$lines = array();

$center = false;

foreach($storyline as $d){
	if(isset($d->segments) && is_array($d->segments)) {
		foreach ( $d->segments as $segment ) {
			if ( $segment->type == "move" ) {
				$line = new \mapdraw\line();
				foreach ($segment->activities as $activity) {
					// Moves is a bit wonky for flights.
					if($activity->activity != "airplane"){
						foreach ($activity->trackPoints as $trackPoint) {
							$point = new \mapdraw\point();
							$point->setLatitude($trackPoint->lat);
							$point->setLongitude($trackPoint->lon);
							$line->addPoint($point);
							// If in doubt, center on the last point. This gets overwritten by the bounds thing anyway.
							$center = $point;
						}
					}
				}
				$lines[] = $line;
			}
		}
	}
}

$map = new \mapdraw\map();

foreach($lines as $line){

	$map->addLine($line,166,10,46);
}

// $map->setCenter($center);
$map->getZoomFromBounds();

$map->draw();
