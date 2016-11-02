<?php
namespace mapdraw;


class map
{
	/**
	 * @var point
	 */
	protected $center;
	protected $zoom;
	protected $width, $height;

	/**
	 * @return int
	 */
	public function getWidth() {
		return $this->width;
	}

	/**
	 * @param int $width
	 */
	public function setWidth( $width ) {
		if($width < 100) $width = 100;
		$this->width = $width;
	}

	/**
	 * @return int
	 */
	public function getHeight() {
		return $this->height;
	}

	/**
	 * @param int $height
	 */
	public function setHeight( $height ) {
		if($height < 100) $height = 100;
		$this->height = $height;
	}

	protected $tileSize = 256;
	protected $tileUrl = "http://c.tile.openstreetmap.fr/hot/{Z}/{X}/{Y}.png";

	protected $centerX, $centerY, $offsetX, $offsetY;

	protected $useTileCache = true;
	protected $tileCacheBaseDir = 'cache/tiles';

	protected $image;

	protected $useMapCache = true;
	protected $mapCacheBaseDir = 'cache/maps';
	protected $mapCacheID = '';
	protected $mapCacheFile = '';
	protected $mapCacheExtension = 'png';

	protected $lines = array();

	protected $maxLat, $maxLong, $minLat, $minLong;

	public function __construct()
	{
		$this->zoom = 15;
		$this->width = 1080;
		$this->height = 1080;
	}

	public function draw(){
		$this->_initCoordinates();

		$this->image = imagecreatetruecolor($this->width, $this->height);

		$startX = floor($this->centerX - ($this->width / $this->tileSize) / 2);
		$startY = floor($this->centerY - ($this->height / $this->tileSize) / 2);
		$endX = ceil($this->centerX + ($this->width / $this->tileSize) / 2);
		$endY = ceil($this->centerY + ($this->height / $this->tileSize) / 2);

		$this->offsetX += floor($this->width / 2);
		$this->offsetY += floor($this->height / 2);
		$this->offsetX += floor($startX - floor($this->centerX)) * $this->tileSize;
		$this->offsetY += floor($startY - floor($this->centerY)) * $this->tileSize;


		for($x = $startX; $x <= $endX; $x++){
			for($y = $startY; $y <= $endY; $y++){
				$url = str_replace(array('{Z}','{X}','{Y}'),array($this->zoom, $x, $y), $this->tileUrl);
				$n = pow(2, $this->zoom);
				// $lon_deg = round($x  / $n * 360 - 180,4);
				// $lat_deg = round(rad2deg(atan(sinh(pi() * (1 - 2 * $y / $n)))),4);
				$tileData = $this->_fetchTile($url);
				if($tileData){
					$tileImage = imagecreatefromstring($tileData);
				} else {
					$tileImage = imagecreate($this->tileSize,$this->tileSize);
					$color = imagecolorallocate($tileImage, 255, 255, 255);
					@imagestring($tileImage,1,127,127,'err',$color);
				}
				$red = imagecolorallocate($tileImage,255,0,0);
				$destX = ($x - $startX) * $this->tileSize + $this->offsetX;
				$destY = ($y - $startY) * $this->tileSize + $this->offsetY;
				imagecopy($this->image, $tileImage, $destX, $destY, 0, 0, $this->tileSize, $this->tileSize);
			}
		}


		//$this->image = imagecreatefromjpeg("bg.jpg");

		if(count($this->lines) > 0){
			foreach($this->lines as $l){
				$linecolor = imagecolorallocate($this->image, $l->red, $l->green, $l->blue);
				$lightred = intval($l->red * 1.5);
				$lightred = ($lightred > 255) ? 255 : $lightred;
				$lightgreen = intval($l->green * 1.5);
				$lightgreen = ($lightgreen > 255) ? 255 : $lightgreen;
				$lightblue = intval($l->blue * 1.5);
				$lightblue = ($lightblue > 255) ? 255 : $lightblue;
				$linecolorlight = imagecolorallocate($this->image, $lightred, $lightgreen, $lightblue);
				$p = $l->line->getPoints();
				for($i = 1; $i < count($p); $i++){
					$p0 = $p[$i - 1];
					$p1 = $p[$i];
					$x0 = $this->_getXFromLongitude($p0->getLongitude());
					$y0 = $this->_getYFromLatitude($p0->getLatitude());
					$x1 = $this->_getXFromLongitude($p1->getLongitude());
					$y1 = $this->_getYFromLatitude($p1->getLatitude());
					$this->_imagelinethick($this->image, $x0, $y0, $x1, $y1, $linecolorlight,3);
					imageline($this->image, $x0, $y0, $x1, $y1, $linecolor);
				}
			}
			/*
			foreach($this->lines as $l){
				$p = $l->line->getPoints();
				for($i = 1; $i < count($p); $i++) {
					$p0 = $p[$i - 1];
					$p1 = $p[$i];
					$x0 = $this->_getXFromLongitude($p0->getLongitude());
					$y0 = $this->_getYFromLatitude($p0->getLatitude());
					$x1 = $this->_getXFromLongitude($p1->getLongitude());
					$y1 = $this->_getYFromLatitude($p1->getLatitude());
					imageline($this->image, $x0, $y0, $x1, $y1, $linecolor);
				}
			}
			*/
		}

		$this->_sendHeader();
		return imagepng($this->image);
	}

	protected function _getXFromLongitude($longitude){
		return floor(($this->width / 2) - $this->tileSize * ($this->centerX - $this->_lonToTile($longitude,$this->zoom)));
	}

	protected function _getYFromLatitude($latitude){
		return floor(($this->height / 2) - $this->tileSize * ($this->centerY - $this->_latToTile($latitude,$this->zoom)));
	}

	public function addLine(line $line, $red = 255, $green = 255, $blue = 255){
		$p = $line->getPoints();
		if(count($p) > 1){
			$l = new \stdClass();
			$l->line = $line;
			$l->red = $red;
			$l->green = $green;
			$l->blue = $blue;
			$this->lines[] = $l;
			foreach($p as $point){
				$this->_addPointToBounds($point);
			}
		}
	}

	protected function _fetchTile($url){
		if($this->useTileCache && ($cached = $this->_checkTileCache($url))){
			return $cached;
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0");
		curl_setopt($ch, CURLOPT_URL, $url);
		$tile = curl_exec($ch);
		curl_close($ch);
		if($tile && $this->useTileCache){
			$this->_writeTileToCache($url,$tile);
		}
		return $tile;
	}

	protected function _writeTileToCache($url, $data){
		$filename = $this->_tileUrlToFilename($url);
		$this->_mkdir_recursive(dirname($filename),0777);
		file_put_contents($filename, $data);
	}

	protected function _mkdir_recursive($pathname, $mode){
		if(!is_dir(dirname($pathname))){
			$this->_mkdir_recursive(dirname($pathname), $mode);
		} else {
			if(!is_dir($pathname)){
				mkdir($pathname,$mode);
			} else {
				return true;
			}
		}
	}

	protected function _tileUrlToFilename($url){
		return $this->tileCacheBaseDir."/".str_replace(array('http://'),'',$url);
	}

	protected function _checkTileCache($url){
		$filename = $this->_tileUrlToFilename($url);
		if(file_exists($filename)){
			return file_get_contents($filename);
		}
	}

	protected function _initCoordinates(){
		if(!$this->center){
			if(
				($this->minLat == null)
				||
				($this->minLong == null)
				||
				($this->maxLat == null)
				||
				($this->maxLong == null)
			) die();
			$this->center = new point();
			$this->center->setLatitude(($this->minLat + $this->maxLat) / 2);
			$this->center->setLongitude(($this->minLong + $this->maxLong) / 2);
		}

		$this->centerX = $this->_lonToTile($this->center->getLongitude(),$this->zoom);
		$this->centerY = $this->_latToTile($this->center->getLatitude(), $this->zoom);
		$this->offsetX = floor((floor($this->centerX) - $this->centerX) * $this->tileSize);
		$this->offsetY = floor((floor($this->centerY) - $this->centerY) * $this->tileSize);
	}

	protected function _lonToTile($long, $zoom){
		return (($long + 180) / 360) * pow(2, $zoom);
	}

	protected function _latToTile($lat, $zoom){
		return (1 - log(tan($lat * pi()/180) + 1 / cos($lat* pi()/180)) / pi()) /2 * pow(2, $zoom);
	}

	protected function _sendHeader(){
		header('Content-Type: image/png');
		$expires = 60*60*24*14;
		header("Pragma: public");
		header("Cache-Control: maxage=".$expires);
		header('Expires: ' . gmdate('D, d M Y H:i:s', time()+$expires) . ' GMT');
	}


	/**
	 * @return int
	 */
	public function getZoom()
	{
		return $this->zoom;
	}

	/**
	 * @param int $zoom
	 */
	public function setZoom($zoom)
	{
		if($zoom < 1) $zoom = 1;
		if($zoom > 18) $zoom = 18;
		$this->zoom = $zoom;
	}

	public function getZoomFromBounds(){
		$topLeftXOutside = $topLeftYOutside = $bottomRightXOutside = $bottomRightYOutside = true;

		$s = 16;
		$this->zoom++;
		while(($s > 0) && ($topLeftXOutside ||$topLeftYOutside || $bottomRightXOutside ||$bottomRightYOutside)){
			$s--;
			$this->zoom--;
			$this->_initCoordinates();

			// Top Left
			$topLeftX = $this->_getXFromLongitude($this->minLong);
			$topLeftY = $this->_getYFromLatitude($this->maxLat);

			// Bottom Right
			$bottomRightX = $this->_getXFromLongitude($this->maxLong);
			$bottomRightY = $this->_getYFromLatitude($this->minLat);

			$topLeftXOutside = ($topLeftX < 2) || ($topLeftX > ($this->width - 2));
			$topLeftYOutside = ($topLeftY < 2) || ($topLeftY > ($this->height - 2));

			$bottomRightXOutside = ($bottomRightX < 2) || ($bottomRightX > ($this->width - 2));
			$bottomRightYOutside = ($bottomRightY < 2) || ($bottomRightY > ($this->width - 2));
		}
		// $this->zoom++;
		// var_dump($this); die();
	}

	/**
	 * @return point
	 */
	public function getCenter()
	{
		return $this->center;
	}

	/**
	 * @param point $center
	 */
	public function setCenter(point $center)
	{
		$this->center = $center;
		$this->_addPointToBounds($center);
	}

	protected function _addPointToBounds(point $point){
		if(!$this->maxLat || ($point->getLatitude() > $this->maxLat)){
			$this->maxLat = $point->getLatitude();
		}
		if(!$this->minLat || ($point->getLatitude() < $this->minLat)){
			$this->minLat = $point->getLatitude();
		}
		if(!$this->maxLong || ($point->getLongitude() > $this->maxLong)){
			$this->maxLong = $point->getLongitude();
		}
		if(!$this->minLong || ($point->getLongitude() < $this->minLong)){
			$this->minLong = $point->getLongitude();
		}
	}

	protected function _imagelinethick($image, $x1, $y1, $x2, $y2, $color, $thick = 1)
	{
		/* this way it works well only for orthogonal lines
		imagesetthickness($image, $thick);
		return imageline($image, $x1, $y1, $x2, $y2, $color);
		*/
		if ($thick == 1) {
			return imageline($image, $x1, $y1, $x2, $y2, $color);
		}
		$t = $thick / 2 - 0.5;
		if ($x1 == $x2 || $y1 == $y2) {
			return imagefilledrectangle($image, round(min($x1, $x2) - $t), round(min($y1, $y2) - $t), round(max($x1, $x2) + $t), round(max($y1, $y2) + $t), $color);
		}
		$k = ($y2 - $y1) / ($x2 - $x1); //y = kx + q
		$a = $t / sqrt(1 + pow($k, 2));
		$points = array(
			round($x1 - (1+$k)*$a), round($y1 + (1-$k)*$a),
			round($x1 - (1-$k)*$a), round($y1 - (1+$k)*$a),
			round($x2 + (1+$k)*$a), round($y2 - (1-$k)*$a),
			round($x2 + (1-$k)*$a), round($y2 + (1+$k)*$a),
		);
		imagefilledpolygon($image, $points, 4, $color);
		return imagepolygon($image, $points, 4, $color);
	}

}