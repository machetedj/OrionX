<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,View};use App\Repositories\LogRepository;use App\Security\Auth;
final readonly class LogController
{
 public function __construct(private View $view,private LogRepository $repo){}
 public function index(Request $r):void{$this->allow('logs.view');$filters=['category'=>$r->string('category'),'level'=>$r->string('level'),'query'=>$r->string('query')];$this->view->render('logs/index',['title'=>'Logs','logs'=>$this->repo->search($filters),'filters'=>$filters]);}
 public function export(Request $r):void{$this->allow('data.export');$rows=$this->repo->search(['category'=>$r->string('category'),'level'=>$r->string('level'),'query'=>$r->string('query')],1000);header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="logs.csv"');$out=fopen('php://output','wb');fputcsv($out,['id','category','level','message','correlation_id','actor','ip','created_at']);foreach($rows as $row)fputcsv($out,[$row['id'],$row['category'],$row['level'],$row['message'],$row['correlation_id'],$row['actor_user_id'],$row['ip'],$row['created_at']]);fclose($out);}
 private function allow(string $permission):void{if(!Auth::can($permission)){http_response_code(403);exit('Sin permiso');}}
}
