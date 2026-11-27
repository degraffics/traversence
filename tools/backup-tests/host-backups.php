<?php
require $argv[1] . '/app/lib/HostBackups.php';
$f=0; $t=function($n,$ok,$g=null)use(&$f){echo ($ok?"  ok  ":"FAIL  ").$n.($ok?'':' — '.json_encode($g))."\n"; $f+=$ok?0:1;};
$t('dates from strings', HostBackups::dates(['2026-11-25','2026-11-26','2026-11-26']) === ['2026-11-25','2026-11-26'], HostBackups::dates(['2026-11-25','2026-11-26']));
$t('dates from rows with a time', HostBackups::dates([['time'=>1764115200],['date'=>'20261120']]) === ['2025-11-26','2026-11-20']);
$t('dates from keys', HostBackups::dates(['2026-11-24' => 'x']) === ['2026-11-24']);
$t('nothing dated: none', HostBackups::dates([['name'=>'weekly']]) === []);
$s=['at'=>1764214800,'ok'=>true,'backups'=>['2026-11-20','2026-11-26'],'latest'=>'2026-11-26','count'=>2,'jetbackup'=>['jetbackup5'],'why'=>''];
$l=HostBackups::line($s); $t('line: count, range, latest, JetBackup, what cPanel doesn\'t say', str_contains($l,'2 kept, from 2026-11-20 to 2026-11-26') && str_contains($l,'JetBackup is on') && str_contains($l,'isn\'t in cPanel'), $l);
$t('line: none listed', str_contains(HostBackups::line(['ok'=>true,'count'=>0,'backups'=>[],'jetbackup'=>[]]+$s),'lists none'));
$t('line: a failure in its own words', str_contains(HostBackups::line(['ok'=>false,'why'=>'cPanel\'s Backup module: cPanel: The feature “backup” is not enabled.']+$s),'couldn\'t be read yet (cPanel\'s Backup module'));
$t('line: JetBackup unknown said so', str_contains(HostBackups::line(['jetbackup'=>null]+$s),'couldn\'t be read'));
putenv('CPANEL_HOST='); $r=HostBackups::read(); $t('read without cPanel keys: says so, nothing called', $r['ok']===false && str_contains($r['why'],'isn\'t connected'), $r);
echo $f ? "$f failed\n" : "all passed\n"; exit($f?1:0);
