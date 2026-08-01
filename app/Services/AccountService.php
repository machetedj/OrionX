<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\{AccountRepository,AuditRepository};
use App\Security\{Auth,DeviceCredentialCipher};
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

final readonly class AccountService
{
    public function __construct(private PDO $db,private AccountRepository $accounts,private AuditRepository $audit,private DeviceCredentialCipher $cipher){}
    public function create(array $input): int
    {
        $username=trim((string)($input['username']??''));
        if(!preg_match('/^[A-Za-z0-9._-]{3,120}$/',$username)) throw new RuntimeException('Usuario inválido.');
        if(strlen((string)($input['password']??''))<8)throw new RuntimeException('La credencial debe tener al menos 8 caracteres.');
        $max=(int)($input['max_connections']??1);if($max<1||$max>100)throw new RuntimeException('El límite de conexiones debe estar entre 1 y 100.');
        $expires=trim((string)($input['expires_at']??''));
        if($expires!==''&&!DateTimeImmutable::createFromFormat('Y-m-d\TH:i',$expires))throw new RuntimeException('Expiración inválida.');
        $country=strtoupper(trim((string)($input['allowed_country']??'')));if($country!==''&&!preg_match('/^[A-Z]{2}$/',$country))throw new RuntimeException('País inválido.');
        $ip=trim((string)($input['allowed_ip']??''));if($ip!==''&&!filter_var($ip,FILTER_VALIDATE_IP))throw new RuntimeException('IP inválida.');
        $data=['reseller'=>null,'username'=>$username,'credential'=>$this->cipher->encrypt((string)($input['password']??'')),'status'=>'active','expires'=>$expires===''?null:str_replace('T',' ',$expires).':00','connections'=>$max,'trial'=>isset($input['is_trial'])?1:0,'restreamer'=>isset($input['is_restreamer'])?1:0,'ip'=>$ip?:null,'country'=>$country?:null,'agent'=>trim((string)($input['allowed_user_agent']??''))?:null,'notes'=>trim((string)($input['notes']??''))?:null];
        $this->db->beginTransaction();
        try{$id=$this->accounts->create($data,(array)($input['package_ids']??[]),Auth::id()??throw new RuntimeException('Sesión inválida.'));$this->audit->record('account.created','end_user_account',$id,['username'=>$username]);$this->db->commit();return $id;}
        catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function reveal(int $id): string { $account=$this->accounts->find($id)??throw new RuntimeException('Cuenta no encontrada.');$this->audit->record('account.secret_revealed','end_user_account',$id);return $this->cipher->decrypt($account['credential_ciphertext']); }
    public function setStatus(int $id,string $status): void { if(!in_array($status,['active','suspended','blocked'],true))throw new RuntimeException('Estado inválido.');$this->accounts->setStatus($id,$status,Auth::id()??0);$this->audit->record('account.status_changed','end_user_account',$id,['status'=>$status]); }
    public function renew(int $id,int $days): void { if($days<1||$days>3650)throw new RuntimeException('Días de renovación inválidos.');$this->accounts->renew($id,$days,Auth::id()??0);$this->audit->record('account.renewed','end_user_account',$id,['days'=>$days]); }
    public function disconnect(int $id): int { $count=$this->accounts->disconnect($id,'admin_disconnect');$this->audit->record('account.sessions_disconnected','end_user_account',$id,['sessions'=>$count]);return $count; }
    public function delete(int $id): void { $this->accounts->softDelete($id,Auth::id()??0);$this->audit->record('account.deleted','end_user_account',$id); }
}
