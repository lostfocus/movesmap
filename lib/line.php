<?php
namespace mapdraw;


class line
{
	protected $points = array();

	public function getPoints()
	{
		return $this->points;
	}

	public function addPoint(point $point){
		$this->points[] = $point;
	}
}