<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php'; \Dotenv\Dotenv::createImmutable(dirname(__DIR__))->load();
$email=$argv[1]??'';$password=$argv[2]??'';if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12)exit("Uso: php scripts/create-admin.php email clave-de-12-o-mas\n");
$pdo=new PDO(sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',$_ENV['DB_HOST'],$_ENV['DB_DATABASE']),$_ENV['DB_USERNAME'],$_ENV['DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();$s=$pdo->prepare('INSERT INTO users(name,email,password_hash) VALUES("Administrador",:e,:p)');$s->execute(['e'=>$email,'p'=>password_hash($password,PASSWORD_ARGON2ID)]);$id=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO user_roles(user_id,role_id) VALUES(:u,1)')->execute(['u'=>$id]);$pdo->commit();echo "Administrador creado\n";
