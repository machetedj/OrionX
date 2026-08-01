<?php
declare(strict_types=1);
namespace App\Security;

final class LegacyPassword
{
 public function forImport(string $value):string
 {
  if($this->isHash($value))return $value;
  return password_hash($value,PASSWORD_ARGON2ID);
 }
 public function verify(string $plain,string $stored):bool
 {
  if(password_verify($plain,$stored))return true;
  if(preg_match('/^\$(?:5|6)\$/',$stored)){ $candidate=crypt($plain,$stored);return is_string($candidate)&&hash_equals($stored,$candidate); }
  if(preg_match('/^[a-f0-9]{32}$/i',$stored))return hash_equals(strtolower($stored),md5($plain));
  if(preg_match('/^[a-f0-9]{40}$/i',$stored))return hash_equals(strtolower($stored),sha1($plain));
  return false;
 }
 public function needsUpgrade(string $stored):bool{return !str_starts_with($stored,'$argon2id$')||password_needs_rehash($stored,PASSWORD_ARGON2ID);}
 private function isHash(string $value):bool{return (bool)preg_match('/^(?:\$argon2(?:id|i|d)\$|\$2[ayb]\$|\$(?:5|6)\$|[a-f0-9]{32}$|[a-f0-9]{40}$)/i',$value);}
}
