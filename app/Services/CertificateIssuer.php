<?php
declare(strict_types=1);
namespace App\Services;
use RuntimeException;
final class CertificateIssuer
{
 public function issueMain(int $requestId):array
 {
  if($requestId<1)throw new RuntimeException('Solicitud TLS inválida.');$helper='/usr/local/sbin/media-panel-cert-helper';if(!is_executable($helper))throw new RuntimeException('El helper TLS privilegiado no está instalado.');$pipes=[];$process=proc_open(['/usr/bin/sudo','-n',$helper,(string)$requestId],[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);if(!is_resource($process))throw new RuntimeException('No se pudo iniciar el helper TLS.');$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);if($exit!==0)throw new RuntimeException('Certbot falló: '.substr(trim((string)$stderr),0,500));return ['request_id'=>$requestId,'result'=>trim((string)$stdout)];
 }
}
