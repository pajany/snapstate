<?php
	namespace Front\View\Helper;
	use Zend\View\Helper\AbstractHelper;
	
	class Shorturl extends AbstractHelper
	{
	    public function __invoke($var)
	    {
			return $this->optimize($var);
	    }
		
		public function optimize($url) {
			if(trim($url) != '') {
				$url	= $this->hexToBase64($url);
				//$url	= $this->base64ToHex($url);
			}
			return $url;
		}
		
		public function base64ToHex($string) {
			return bin2hex(base64_decode($string));
		}
		
		public function hexToBase64($string) {
			return base64_encode($this->hex2bin($string));
		}
		
		public function hex2bin($data) {
	    	$newdata	= '';
		    $len 		= strlen($data);
		    for($i=0; $i < $len; $i += 2) {
	    		$newdata	.= pack("C",hexdec(substr($data,$i,2)));
			}
			return $newdata;
		}
	}	?>