<?php
// Set the name of the json file here
$source = "datasource/setthishere.json";

// here we go

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if($source = "datasource/setthishere.json"){
	die("You need to change the name of the source file.");
}


require_once("lib/point.php");
require_once("lib/line.php");
require_once("lib/map.php");

$storyline = json_decode(file_get_contents($source));

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