<?php
declare(strict_types=1);
namespace App\Controllers\Api;
use App\Core\{Request,Response};use App\Services\{TmdbMetadataService,TmdbService};use Throwable;
final class TmdbApiController extends ApiController
{
 public function __construct(private readonly TmdbService $tmdb,private readonly TmdbMetadataService $metadata){}
 public function search(Request $r):void{$this->token($r,'admin','tmdb.search');try{$result=$this->tmdb->search($r->string('type')?:'movie',$r->string('title'),$r->int('year')?:null,$r->string('language')?:null,$r->int('page')?:1);$this->ok($result,200,['page'=>$result['page']??1,'total_pages'=>$result['total_pages']??1,'total_results'=>$result['total_results']??0]);}catch(Throwable $e){Response::json(['ok'=>false,'error'=>['code'=>'tmdb_error','message'=>$e->getMessage()]],422);}}
 public function select(Request $r):void{$this->token($r,'admin','tmdb.select');try{$this->metadata->select($r->int('content_id'),$r->int('tmdb_id'),$r->string('language')?:'es-ES');$this->ok(['updated'=>true]);}catch(Throwable $e){Response::json(['ok'=>false,'error'=>['code'=>'validation_error','message'=>$e->getMessage()]],422);}}
}
