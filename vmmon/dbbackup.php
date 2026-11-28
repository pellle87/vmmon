<?php // Bakgrundsjobb: dumpar databasen och lägger den komprimerad (som database.sql) i en redan skapad backup-zip. Startas av update.php: php dbbackup.php <zip>
// Förloppet skrivs till <zip>.job så att uppdateringssidan kan visa det. Filerna i backupen är redan på plats när jobbet startar.
if(PHP_SAPI!=='cli') exit('Endast CLI');
chdir(__DIR__); set_time_limit(0); ini_set('memory_limit','512M'); ignore_user_abort(true);
require __DIR__.'/config.php'; require __DIR__.'/lib.php'; require __DIR__.'/dump_lib.php'; $_SESSION=[];
$zip=$argv[1]??''; $bd=realpath(dirname($zip)); if($zip===''||!is_file($zip)||!preg_match('~/vmmon-[0-9][0-9.]*-\d{8}-\d{6}\.zip$~',$zip)) exit(1);
$job=$zip.'.job'; $last=0; $st=['state'=>'running','t'=>time(),'pid'=>getmypid(),'msg'=>''];
$save=function($force=false) use(&$st,$job,&$last){ if(!$force&&time()-$last<2) return; $last=time(); $st['t']=time(); @file_put_contents($job,json_encode($st)); };
$save(true); $dump=dirname($zip).'/tmp/dump-'.bin2hex(random_bytes(4)).'.sql'; if(!is_dir(dirname($dump))) @mkdir(dirname($dump),0750,true);
try{
 vm_dump_db($dump,function($m) use(&$st,$save){ $st['msg']=$m; $save(); });
 $st['msg']=sprintf(t('Komprimerar databasdumpen (%s MB)','Compressing the database dump (%s MB)'),round(filesize($dump)/1048576)); $save(true);
 $z=new ZipArchive; if($z->open($zip)!==true) throw new Exception(t('Kan inte öppna backupen','Cannot open the backup')); $z->addFile($dump,'database.sql'); if(method_exists($z,'setCompressionName')) $z->setCompressionName('database.sql',ZipArchive::CM_DEFLATE,3);
 if(!$z->close()) throw new Exception(t('Backupen kunde inte skrivas (diskutrymme?)','The backup could not be written (disk space?)'));
 @unlink($dump); @unlink($job); applog('info','config','Database backup completed: '.basename($zip));
}catch(Throwable $e){ @unlink($dump); $st['state']='failed'; $st['msg']=$e->getMessage(); $save(true); applog('error','config','Database backup failed: '.$e->getMessage()); exit(1); }
