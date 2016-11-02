<?php
namespace mapdraw;


class point
{
	var $latitude, $longitude;

	/**
	 * @return float
	 */
	public function getLatitude()
	{
		return $this->latitude;
	}

	/**
	 * @param float $latitude
	 */
	public function setLatitude($latitude)
	{
		$this->latitude = $latitude;
	}

	/**
	 * @return float
	 */
	public function getLongitude()
	{
		return $this->longitude;
	}

	/**
	 * @param float $longitude
	 */
	public function setLongitude($longitude)
	{
		$this->longitude = $longitude;
	}
}