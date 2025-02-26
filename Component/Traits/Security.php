<?php

namespace Component\Traits;

trait Security 
{
	private $crypt_pass = "webnmobileisbest";
    private $crypt_iv = "webnmobileisbest";
    private $crypt_type = "AES-256-CBC";
	
	/**
    * 암호화
	*
	*/
   public function encrypt($data = null)
   {
       if ($data) {
          $endata = openssl_encrypt($data , $this->crypt_type, $this->crypt_pass, true, $this->crypt_iv);
          $endata = base64_encode($endata);
          return $endata;
       }
   }

   /**
   * 복호화 
   *
   */
   public function decrypt($data = null)
   {
       if ($data) {
           $data = base64_decode($data);
           $data = openssl_decrypt($data, $this->crypt_type, $this->crypt_pass, true, $this->crypt_iv);

           return $data;
       }
   }
   
   /**
   * 비밀번호 해시 
   *
   * @param String $password 해시처리할 비밀번호 
   */
   public function getPasswordHash($password = null)
   {
       if ($password) {
          return password_hash($password, PASSWORD_DEFAULT, ['cost' => 5]);
	   }
   }
   
   /**
   * 비밀번호 해시 체크 
   *
   * @param String $password 비밀번호 
   * @param String $hash 비교할 해시코드 
   *
   * @return Boolean
   */
   public function checkPasswordHash($password = null, $hash = null)
   {
	   if (!$password || !$hash)
		   return false;
	   
	   return password_verify($password, $hash);
   }
}