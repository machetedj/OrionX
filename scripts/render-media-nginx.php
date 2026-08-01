<?php
declare(strict_types=1);
use App\Core\Application;
require dirname(__DIR__).'/vendor/autoload.php';$app=Application::boot(dirname(__DIR__));$db=$app->get(\PDO::class);$libraries=$db->query('SELECT id,mount_path FROM storage_libraries WHERE active=1 ORDER BY id')->fetchAll();foreach($libraries as $library){$path=realpath($library['mount_path']);if($path===false||!is_dir($path)){fwrite(STDERR,"Librería {$library['id']} omitida.\n");continue;}$path=rtrim(str_replace('\\','/',$path),'/').'/';if(preg_match('/[{};\r\n]/',$path))throw new RuntimeException('Ruta no segura para Nginx.');echo "location ^~ /__media/library-{$library['id']}/ {\n    internal;\n    autoindex off;\n    alias {$path};\n    add_header X-Content-Type-Options nosniff always;\n}\n";}
