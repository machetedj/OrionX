#!/usr/bin/php
<?php
declare(strict_types=1);
if(posix_geteuid()!==0){fwrite(STDERR,"root requerido\n");exit(1);}
$id=(int)($argv[1]??0);if($id<1){fwrite(STDERR,"id inválido\n");exit(2);}
$base='/opt/licensed-media-panel/storage/imports/xui';$control=$base.'/'.$id.'.json';
$real=realpath($control);if($real===false||!str_starts_with($real,$base.DIRECTORY_SEPARATOR)){fwrite(STDERR,"control inválido\n");exit(3);}
$data=json_decode((string)file_get_contents($real),true,16,JSON_THROW_ON_ERROR);
foreach(['database','username','password','dump'] as $key)if(!isset($data[$key])||!is_string($data[$key]))exit(4);
if(!preg_match('/^orionx_xui_upload_[0-9]+$/',$data['database'])||!preg_match('/^orionx_xui_[0-9]+$/',$data['username'])||!preg_match('/^[a-f0-9]{48}$/',$data['password']))exit(5);
$dump=realpath($data['dump']);if($dump===false||!str_starts_with($dump,$base.DIRECTORY_SEPARATOR)||!preg_match('/\.sql(?:\.gz)?$/i',$dump))exit(6);
$sql=sprintf("DROP DATABASE IF EXISTS `%s`; CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; DROP USER IF EXISTS '%s'@'127.0.0.1'; CREATE USER '%s'@'127.0.0.1' IDENTIFIED BY '%s'; GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'127.0.0.1'; FLUSH PRIVILEGES;",$data['database'],$data['database'],$data['username'],$data['username'],$data['password'],$data['database'],$data['username']);
$root=proc_open(['/usr/bin/mariadb','--protocol=socket','--execute='.$sql],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,['PATH'=>'/usr/sbin:/usr/bin:/sbin:/bin'],['bypass_shell'=>true]);if(!is_resource($root))exit(7);$rootOut=stream_get_contents($pipes[1]);$rootErr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$rootExit=proc_close($root);if($rootExit!==0){fwrite(STDERR,$rootErr?:$rootOut);exit($rootExit);}
$defaults=tempnam('/tmp','orionx-db-');chmod($defaults,0600);file_put_contents($defaults,"[client]\nhost=127.0.0.1\nuser={$data['username']}\npassword={$data['password']}\n");
$process=proc_open(['/usr/bin/mariadb','--defaults-extra-file='.$defaults,'--binary-mode','--database='.$data['database']],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,['PATH'=>'/usr/sbin:/usr/bin:/sbin:/bin'],['bypass_shell'=>true]);if(!is_resource($process)){unlink($defaults);exit(8);}
$input=str_ends_with(strtolower($dump),'.gz')?gzopen($dump,'rb'):fopen($dump,'rb');if($input===false)exit(9);while(!feof($input)){$chunk=str_ends_with(strtolower($dump),'.gz')?gzread($input,1048576):fread($input,1048576);if($chunk===false)break;fwrite($pipes[0],$chunk);}str_ends_with(strtolower($dump),'.gz')?gzclose($input):fclose($input);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);unlink($defaults);if($exit!==0){fwrite(STDERR,$err?:$out);exit($exit);}
$lock=sprintf("REVOKE ALL PRIVILEGES, GRANT OPTION FROM '%s'@'127.0.0.1'; GRANT SELECT ON `%s`.* TO '%s'@'127.0.0.1'; FLUSH PRIVILEGES;",$data['username'],$data['database'],$data['username']);
$final=proc_open(['/usr/bin/mariadb','--protocol=socket','--execute='.$lock],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,['PATH'=>'/usr/sbin:/usr/bin:/sbin:/bin'],['bypass_shell'=>true]);if(!is_resource($final))exit(10);$finalOut=stream_get_contents($pipes[1]);$finalErr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$finalExit=proc_close($final);if($finalExit!==0){fwrite(STDERR,$finalErr?:$finalOut);exit($finalExit);}unlink($control);echo "XUI_SQL_RESTORE_OK\n";
