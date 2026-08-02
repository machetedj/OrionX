<?php
declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->load();

function config(string $key,?string $default=null): ?string {
    $process=getenv($key);
    if($process!==false&&$process!=='') return $process;
    $value=$_ENV[$key]??$_SERVER[$key]??null;
    return $value===null||$value===''?$default:(string)$value;
}

$execute=in_array('--execute',$argv,true);
$replace=in_array('--replace',$argv,true);
$prefix=(string)config('XUI_IMPORT_PREFIX','xui_legacy__');
$batchSize=max(100,min(10000,(int)config('XUI_IMPORT_BATCH_SIZE','1000')));
if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/',$prefix)) throw new RuntimeException('XUI_IMPORT_PREFIX inválido.');

function connection(bool $source): PDO {
    $p=$source?'XUI_':'';
    foreach(['DB_HOST','DB_DATABASE','DB_USERNAME'] as $key) if(config($p.$key)===null) throw new RuntimeException("Falta {$p}{$key} en la configuración del proceso");
    $dsn=sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',config($p.'DB_HOST'),config($p.'DB_PORT','3306'),config($p.'DB_DATABASE'));
    return new PDO($dsn,config($p.'DB_USERNAME'),config($p.'DB_PASSWORD',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
}

function id(string $name): string { return '`'.str_replace('`','``',$name).'`'; }
function destinationName(string $prefix,string $table): string {
    $name=$prefix.$table;
    return strlen($name)<=64?$name:substr($name,0,51).'_'.substr(hash('sha256',$table),0,12);
}
function countRows(PDO $db,string $table): int { return (int)$db->query('SELECT COUNT(*) FROM '.id($table))->fetchColumn(); }
function createSql(PDO $source,string $table,array $map): string {
    $row=$source->query('SHOW CREATE TABLE '.id($table))->fetch(PDO::FETCH_NUM);
    if(!$row||!isset($row[1])) throw new RuntimeException("No se pudo leer {$table}");
    $sql=(string)$row[1];
    $createPattern='/^(CREATE TABLE\s+)'.preg_quote(id($table),'/').'/i';
    $sql=(string)preg_replace($createPattern,'$1'.id($map[$table]),$sql,1);
    foreach($map as $old=>$new){
        $referencePattern='/(\bREFERENCES\s+)'.preg_quote(id($old),'/').'/i';
        $sql=(string)preg_replace($referencePattern,'$1'.id($new),$sql);
    }
    $sql=(string)preg_replace('/\butf8mb4_general_ci\b/i','utf8mb4_unicode_ci',$sql);
    return $sql;
}

$source=connection(true);
$destination=connection(false);
$sameHost=config('XUI_DB_HOST','')===config('DB_HOST','');
if($sameHost&&config('XUI_DB_DATABASE','')===config('DB_DATABASE','')) throw new RuntimeException('Origen y destino deben ser bases distintas.');
$tables=array_map('strval',$source->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN));
if(!$tables) throw new RuntimeException('La base XUI no contiene tablas.');
$source->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$source->beginTransaction();
$map=[];
foreach($tables as $table) $map[$table]=destinationName($prefix,$table);

echo 'Modo: '.($execute?'IMPORTACIÓN':'AUDITORÍA (sin cambios)').PHP_EOL;
foreach($tables as $table) echo sprintf("  %-36s %12d -> %s\n",$table,countRows($source,$table),$map[$table]);
if(!$execute){ $source->rollBack(); echo "Usa --execute para copiar; --replace sustituye una réplica anterior.\n"; exit(0); }

$fingerprint=hash('sha256',config('XUI_DB_DATABASE','')."\n".implode("\n",$tables));
$track=$destination->prepare('INSERT INTO xui_imports(source_fingerprint,source_database,table_prefix,tables_total) VALUES(?,?,?,?)');
$track->execute([$fingerprint,config('XUI_DB_DATABASE'),$prefix,count($tables)]);
$importId=(int)$destination->lastInsertId();
$totalRows=0;
$completed=0;
$destination->exec('SET FOREIGN_KEY_CHECKS=0');
try {
    foreach($tables as $table){
        $target=$map[$table];
        $exists=(bool)$destination->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=".$destination->quote($target))->fetchColumn();
        if($exists&&!$replace) throw new RuntimeException("Ya existe {$target}; usa otro prefijo o --replace.");
        if($exists) $destination->exec('DROP TABLE '.id($target));
        $destination->exec(createSql($source,$table,$map));
        $sourceRows=countRows($source,$table);
        $destination->prepare("INSERT INTO xui_import_tables(import_id,source_table,destination_table,source_rows,status,started_at) VALUES(?,?,?,?,'copying',NOW())")->execute([$importId,$table,$target,$sourceRows]);
        $columnQuery=$source->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND EXTRA NOT LIKE '%GENERATED%' ORDER BY ORDINAL_POSITION");
        $columnQuery->execute([$table]);
        $columns=array_map('strval',$columnQuery->fetchAll(PDO::FETCH_COLUMN));
        $columnSql=implode(',',array_map('id',$columns));
        $insert=$destination->prepare('INSERT INTO '.id($target).' ('.$columnSql.') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');
        $offset=0;
        $hash=hash_init('sha256');
        while($offset<$sourceRows){
            $rows=$source->query('SELECT * FROM '.id($table).' LIMIT '.$batchSize.' OFFSET '.$offset)->fetchAll(PDO::FETCH_NUM);
            if(!$rows) break;
            $destination->beginTransaction();
            try {
                foreach($rows as $row){
                    $insert->execute($row);
                    hash_update($hash,(string)json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PARTIAL_OUTPUT_ON_ERROR)."\n");
                }
                $destination->commit();
            } catch(Throwable $e){ if($destination->inTransaction()) $destination->rollBack(); throw $e; }
            $offset+=count($rows);
        }
        $actual=countRows($destination,$target);
        if($actual!==$sourceRows) throw new RuntimeException("Conteo inválido en {$table}: {$sourceRows} != {$actual}");
        $destination->prepare("UPDATE xui_import_tables SET copied_rows=?,checksum_sha256=?,status='completed',completed_at=NOW() WHERE import_id=? AND source_table=?")->execute([$actual,hash_final($hash),$importId,$table]);
        $totalRows+=$actual; $completed++;
        $destination->prepare('UPDATE xui_imports SET tables_copied=?,rows_copied=? WHERE id=?')->execute([$completed,$totalRows,$importId]);
        echo "  {$table}: {$actual} filas OK\n";
    }
    $destination->prepare("UPDATE xui_imports SET status='completed',completed_at=NOW() WHERE id=?")->execute([$importId]);
    echo "Importación {$importId} completada: {$completed} tablas, {$totalRows} filas.\n";
} catch(Throwable $e){
    $destination->prepare("UPDATE xui_imports SET status='failed',error_message=?,completed_at=NOW() WHERE id=?")->execute([substr($e->getMessage(),0,65535),$importId]);
    throw $e;
} finally {
    if($source->inTransaction()) $source->rollBack();
    $destination->exec('SET FOREIGN_KEY_CHECKS=1');
}
