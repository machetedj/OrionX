<?php
declare(strict_types=1);
namespace App\Services;

use DomainException;
use PDO;
use Throwable;

final readonly class CreditService
{
    public function __construct(private PDO $db){}

    public function adjust(int $resellerId,int $amount,string $reason,int $actorId,?string $operationId=null,?string $ip=null): array
    {
        if($amount===0)throw new DomainException('El movimiento no puede ser cero.');
        $reason=trim($reason);if($reason===''||strlen($reason)>255)throw new DomainException('Motivo inválido.');
        $operationId=$operationId??bin2hex(random_bytes(16));
        if(!preg_match('/^[a-f0-9]{32}$/i',$operationId))throw new DomainException('Identificador de operación inválido.');
        if($ip!==null&&!filter_var($ip,FILTER_VALIDATE_IP))throw new DomainException('Dirección IP inválida.');

        $this->db->beginTransaction();
        try{
            $lock=$this->db->prepare('SELECT credit_balance FROM resellers WHERE id=:id AND active=1 FOR UPDATE');$lock->execute(['id'=>$resellerId]);
            $before=$lock->fetchColumn();if($before===false)throw new DomainException('Revendedor inexistente o suspendido.');
            $existing=$this->db->prepare('SELECT * FROM reseller_credit_transactions WHERE uuid=:uuid');$existing->execute(['uuid'=>$operationId]);$movement=$existing->fetch();
            if($movement){
                if((int)$movement['reseller_id']!==$resellerId||(int)$movement['amount']!==$amount)throw new DomainException('El identificador ya pertenece a otra operación.');
                $this->db->commit();return $movement;
            }
            $after=(int)$before+$amount;if($after<0)throw new DomainException('Saldo insuficiente.');
            $insert=$this->db->prepare('INSERT INTO reseller_credit_transactions(reseller_id,amount,balance_before,balance_after,reason,actor_user_id,ip,uuid) VALUES(:reseller,:amount,:before,:after,:reason,:actor,:ip,:uuid)');
            $insert->execute(['reseller'=>$resellerId,'amount'=>$amount,'before'=>$before,'after'=>$after,'reason'=>$reason,'actor'=>$actorId,'ip'=>$ip,'uuid'=>$operationId]);
            $this->db->prepare('UPDATE resellers SET credit_balance=:balance WHERE id=:id')->execute(['balance'=>$after,'id'=>$resellerId]);
            $id=(int)$this->db->lastInsertId();$this->db->commit();
            return ['id'=>$id,'reseller_id'=>$resellerId,'amount'=>$amount,'balance_before'=>(int)$before,'balance_after'=>$after,'reason'=>$reason,'actor_user_id'=>$actorId,'ip'=>$ip,'uuid'=>$operationId];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
