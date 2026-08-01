<?php
declare(strict_types=1);
namespace App\Services;

use App\Domain\Media\MediaPathGuard;
use RuntimeException;

final readonly class FfprobeAnalyzer
{
    public function __construct(private MediaPathGuard $paths){}
    public function analyze(string $mountPath,string $candidate): array
    {
        $relative=$this->paths->relative($mountPath,$candidate);$binary=(string)($_ENV['FFPROBE_PATH']??'/usr/local/bin/ffprobe');
        if(!is_file($binary))throw new RuntimeException('FFprobe no está instalado en la ruta configurada.');
        $command=[$binary,'-v','error','-show_format','-show_streams','-of','json',realpath($candidate)];
        $pipes=[];$process=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($process))throw new RuntimeException('No fue posible iniciar FFprobe.');
        stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$stdout='';$stderr='';$started=microtime(true);$timeout=max(5,min(120,(int)($_ENV['FFPROBE_TIMEOUT']??30)));
        do{$stdout.=stream_get_contents($pipes[1]);$stderr.=stream_get_contents($pipes[2]);$status=proc_get_status($process);if(!$status['running'])break;if(microtime(true)-$started>$timeout){proc_terminate($process,9);throw new RuntimeException('FFprobe excedió el tiempo permitido.');}usleep(20000);}while(true);
        $stdout.=stream_get_contents($pipes[1]);$stderr.=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);if($exit===-1)$exit=(int)($status['exitcode']??-1);
        if($exit!==0)throw new RuntimeException('FFprobe rechazó el archivo: '.substr(trim((string)$stderr),0,300));
        $probe=json_decode((string)$stdout,true,512,JSON_THROW_ON_ERROR);
        return ['relative_path'=>$relative,'size_bytes'=>filesize($candidate),'checksum_sha256'=>hash_file('sha256',$candidate),'probe'=>$probe,'tracks'=>$this->tracks($probe['streams']??[])];
    }
    private function tracks(array $streams): array
    {
        return array_map(static fn(array $s):array=>['stream_index'=>(int)($s['index']??0),'type'=>(string)($s['codec_type']??'unknown'),'codec'=>$s['codec_name']??null,'language'=>$s['tags']['language']??null,'title'=>$s['tags']['title']??null,'is_default'=>(bool)($s['disposition']['default']??false),'width'=>$s['width']??null,'height'=>$s['height']??null,'fps'=>$s['avg_frame_rate']??null],$streams);
    }
}
