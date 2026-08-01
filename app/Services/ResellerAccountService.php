<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\{AccountRepository,AuditRepository,ResellerRepository};
use App\Security\{Auth,DeviceCredentialCipher};
use DomainException;
use PDO;
use Throwable;

final readonly class ResellerAccountService
{
    public function __construct(private PDO $db,private ResellerRepository $resellers,private AccountRepository $accounts,private AuditRepository $audit,private DeviceCredentialCipher $cipher){}
    public function create(array $input): int
    {
        $profile=$this->profile();$username=trim((string)($input['username']??''));if(!preg_match('/^[A-Za-z0-9._-]{3,120}$/',$username))throw new DomainException('Usuario inválido.');if(strlen((string)($input['password']??''))<8)throw new DomainException('La credencial debe tener al menos 8 caracteres.');
        $packageId=(int)($input['package_id']??0);$max=(int)($input['max_connections']??1);
        $this->db->beginTransaction();
        try{
            $locked=$this->lock((int)$profile['id']);$package=$this->allowedPackage((int)$profile['id'],$packageId);
            $count=$this->accountCount((int)$profile['id']);if($locked['max_accounts']!==null&&$count>=(int)$locked['max_accounts'])throw new DomainException('Límite de cuentas alcanzado.');
            $allowedMax=$package['max_connections']??$locked['max_connections'];if($allowedMax!==null&&($max<1||$max>(int)$allowedMax))throw new DomainException('Límite de conexiones no permitido.');
            $days=(int)($package['duration_days']??0);$durationLimit=$package['max_duration_days']??$locked['max_duration_days'];if($days<1||($durationLimit!==null&&$days>(int)$durationLimit))throw new DomainException('Duración no permitida.');
            $cost=(int)$package['internal_price'];$this->charge($locked,$cost,'Creación de '.$username,Auth::id()??0);
            $id=$this->accounts->create(['reseller'=>(int)$profile['id'],'username'=>$username,'credential'=>$this->cipher->encrypt((string)($input['password']??'')),'status'=>'active','expires'=>date('Y-m-d H:i:s',time()+$days*86400),'connections'=>$max,'ip'=>null,'country'=>null,'agent'=>null,'notes'=>trim((string)($input['notes']??''))?:null],[$packageId],Auth::id()??0);
            $this->audit->record('reseller.account_created','end_user_account',$id,['reseller_id'=>(int)$profile['id'],'cost'=>$cost]);$this->db->commit();return $id;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function renew(int $accountId,int $packageId): void
    {
        $profile=$this->profile();$this->db->beginTransaction();
        try{$locked=$this->lock((int)$profile['id']);$this->ownedAccount($accountId,(int)$profile['id']);$package=$this->allowedPackage((int)$profile['id'],$packageId);$days=(int)$package['duration_days'];$cost=(int)$package['internal_price'];$this->charge($locked,$cost,'Renovación de cuenta '.$accountId,Auth::id()??0);$this->accounts->renew($accountId,$days,Auth::id()??0);$this->audit->record('reseller.account_renewed','end_user_account',$accountId,['days'=>$days,'cost'=>$cost]);$this->db->commit();}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function suspend(int $accountId): void { $profile=$this->profile();$this->ownedAccount($accountId,(int)$profile['id']);$this->accounts->setStatus($accountId,'suspended',Auth::id()??0);$this->audit->record('reseller.account_suspended','end_user_account',$accountId); }
    private function profile(): array { return $this->resellers->byUser(Auth::id()??0)??throw new DomainException('Revendedor no disponible.'); }
    private function lock(int $id): array { $s=$this->db->prepare('SELECT * FROM resellers WHERE id=? AND active=1 FOR UPDATE');$s->execute([$id]);return $s->fetch()?:throw new DomainException('Revendedor suspendido.'); }
    private function allowedPackage(int $id,int $packageId): array { $s=$this->db->prepare('SELECT p.duration_days,rp.internal_price,rp.max_duration_days,rp.max_connections FROM reseller_packages rp JOIN packages p ON p.id=rp.package_id WHERE rp.reseller_id=? AND rp.package_id=? AND rp.active=1 AND p.active=1');$s->execute([$id,$packageId]);return $s->fetch()?:throw new DomainException('Paquete no permitido.'); }
    private function accountCount(int $id): int { $s=$this->db->prepare('SELECT COUNT(*) FROM end_user_accounts WHERE reseller_id=? AND deleted_at IS NULL');$s->execute([$id]);return (int)$s->fetchColumn(); }
    private function ownedAccount(int $accountId,int $resellerId): void { $s=$this->db->prepare('SELECT 1 FROM end_user_accounts WHERE id=? AND reseller_id=? AND deleted_at IS NULL');$s->execute([$accountId,$resellerId]);if(!$s->fetchColumn())throw new DomainException('Cuenta no encontrada.'); }
    private function charge(array $reseller,int $cost,string $reason,int $actor): void { $before=(int)$reseller['credit_balance'];$after=$before-$cost;if($cost<0||$after<0)throw new DomainException('Saldo insuficiente.');$uuid=bin2hex(random_bytes(16));$this->db->prepare('INSERT INTO reseller_credit_transactions(reseller_id,amount,balance_before,balance_after,reason,actor_user_id,ip,uuid) VALUES(?,?,?,?,?,?,?,?)')->execute([(int)$reseller['id'],-$cost,$before,$after,$reason,$actor,$_SERVER['REMOTE_ADDR']??null,$uuid]);$this->db->prepare('UPDATE resellers SET credit_balance=? WHERE id=?')->execute([$after,(int)$reseller['id']]); }
}
