<?php

/* Algorithm logic 20-01-2017*/
/**
 * Passed Paramiter for encrpt OR descrpt
 * 1) Enxrption data
 * 2) Algorithm type
 * 3) Algorith binary key
 * 4) Start Key
 * 5) Key Lenght
 * 
 * **/
 
class SecureEncryption
{
	// Encryption Alogorithm
	private $algorithm_for_encrpt;
	private $path;
	private $key;
	private $encrypt_key;
	private $iv_sep;
	public static $instance = NULL;
	
    function __construct()
	{
		$this->algorithm_for_encrpt = 'aes-256-cbc';	
		$this->path = plugin_dir_path(__FILE__).'/encryption/key.encrypted.txt';
		$this->iv_sep = '|:|';	

		// Get encryption key from secure location
		$key_for_encrypt_data = file_get_contents( $this->path );

		// If no key found so generate new one and write on file
		if( '' == $key_for_encrypt_data ) 
		{			
			$key_for_encrypt_data = $this->generate_encrypt_key();	
		}
		$this->key = $this->hex_to_bin($key_for_encrypt_data);
	}	
	
	
	// Generate encrypt_key for encryption
	public function generate_encrypt_key()
	{
		$encrypt_key = openssl_random_pseudo_bytes( 32 );
		$hex_encrypt_key = bin2hex( $encrypt_key );
		file_put_contents( $this->path, $hex_encrypt_key);

		return $hex_encrypt_key;
	}


	// Convert Hex to binary key
	private function hex_to_bin( $data )
	{
		if( function_exists('hex2bin') )
		{
			return hex2bin( $data );
		}

		return pack("H*" , $data);
	}

	//Initialization Vector
	public function generate_iv( $length )
	{
		$iv_length = $length && $length!="" ? $length : openssl_cipher_iv_length( $this->algorithm_for_encrpt );
		return openssl_random_pseudo_bytes( $iv_length );
	}

	//Create in stance of class
	public static function instance() 
	{
		if ( is_null( self::$instance ) ) 
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	// Open SSL Encrypt Data
	public function wc_offline_encrypt( $plain_text )
	{
		if( function_exists('openssl_encrypt') )
		{
			// Get Initialization Vector
			$iv = $this->generate_iv('');
			$encrypted = openssl_encrypt ($plain_text, $this->algorithm_for_encrpt, $this->encrypt_key, 0, $iv);
			$hex_iv = bin2hex( $iv );
			return $encrypted . $this->iv_sep . $hex_iv;
		}else{
			$this->generate_log('Not support openssl_encrypt function');
		}

		return false;
	}

	// Open SSL Descrypt Data
	public function wc_offline_decrypt( $cipher_text )
	{
		if( function_exists('openssl_decrypt') )
		{
			list($encrypted, $iv) = explode( $this->iv_sep, $cipher_text );
			$bin_iv = $this->hex_to_bin( $iv );
			return openssl_decrypt( $encrypted, $this->algorithm_for_encrpt, $this->encrypt_key, 0, $bin_iv );
		}else{
			$this->generate_log('Not support openssl_decrypt function');
		}

		return false;
	}
	
	// Generate log for not supported any function 
	public function generate_log( $msg )
	{
		$log_date = date('Y-m-d H:i:s');
		$log_msg = '[' . $log_date . '] ' . $msg . PHP_EOL;
		file_put_contents(plugin_dir_path(__FILE__).'/log/woo_offline_payment.log', $log_msg, FILE_APPEND);
	}	

}


