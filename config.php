<?php
// Ubah sesuai MySQL Anda (XAMPP default: root tanpa password)
$DB=['host'=>'localhost','name'=>'simpro_himsika','user'=>'root','pass'=>''];
session_start();
try{$pdo=new PDO("mysql:host={$DB['host']};dbname={$DB['name']};charset=utf8mb4",$DB['user'],$DB['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
catch(Exception $e){die('Koneksi database gagal: '.htmlspecialchars($e->getMessage()));}
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function q($sql,$a=[]){global $pdo;$s=$pdo->prepare($sql);$s->execute($a);return $s;}
function rows($sql,$a=[]){return q($sql,$a)->fetchAll();}
function row($sql,$a=[]){return q($sql,$a)->fetch();}
