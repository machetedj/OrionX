<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
final readonly class PackageRepository
{
 public function __construct(private PDO $db){}
 public function all():array{return $this->db->query("SELECT p.*,(SELECT COUNT(*) FROM account_packages ap WHERE ap.package_id=p.id) accounts_count,(SELECT COUNT(*) FROM reseller_packages rp WHERE rp.package_id=p.id AND rp.active=1) resellers_count,(SELECT COUNT(*) FROM package_content_items pc WHERE pc.package_id=p.id) items_count,(SELECT COUNT(*) FROM package_categories pc WHERE pc.package_id=p.id) categories_count,GROUP_CONCAT(DISTINCT c.name ORDER BY c.type,c.name SEPARATOR ', ') category_names FROM packages p LEFT JOIN package_categories pc ON pc.package_id=p.id LEFT JOIN categories c ON c.id=pc.category_id GROUP BY p.id ORDER BY p.id DESC")->fetchAll();}
 public function categories():array{return $this->db->query("SELECT id,name,type,(SELECT COUNT(*) FROM content_items i WHERE i.category_id=categories.id) items_count FROM categories WHERE active=1 ORDER BY FIELD(type,'live','movie','series'),name")->fetchAll();}
 public function categoryMap():array{$out=[];foreach($this->db->query('SELECT package_id,category_id FROM package_categories')->fetchAll() as $row)$out[(int)$row['package_id']][]=(int)$row['category_id'];return $out;}
 public function bouquets():array{return $this->db->query('SELECT id,name FROM bouquets WHERE active=1 ORDER BY name')->fetchAll();}
 public function bouquetMap():array{$out=[];foreach($this->db->query('SELECT package_id,bouquet_id FROM package_bouquets') as $r)$out[(int)$r['package_id']][]=(int)$r['bouquet_id'];return $out;}
}
