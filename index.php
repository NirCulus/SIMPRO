<?php
require 'config.php';
$u=$_SESSION['u']??null; $msg='';
if(($_GET['p']??'')==='logout'){session_destroy();header('Location: index.php');exit;}
if(isset($_POST['login'])){
  $r=row("SELECT * FROM users WHERE username=?",[$_POST['username']]);
  if($r&&password_verify($_POST['password'],$r['password'])){$_SESSION['u']=$r;header('Location: index.php');exit;}
  $msg='Username atau password salah.';
}
if(!$u){ ?>
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login SIMPRO Himsika</title><link rel="stylesheet" href="style.css"></head>
<body class="login"><form method="post" class="card"><h1>SIMPRO Himsika</h1><p class="muted">Sistem Manajemen Program Kerja<br>Himpunan Mahasiswa Sistem Informasi</p>
<?php if($msg)echo "<div class='alert'>".h($msg)."</div>"; ?>
<label>Username</label><input name="username" required autofocus><label>Password</label><input type="password" name="password" required>
<button name="login">Masuk</button><p class="muted small">Demo: admin / keplak / anggota — password himsika123</p></form></body></html>
<?php exit; }
$role=$u['role']; $uid=$u['id']; $isAdmin=$role==='admin'; $isKeplak=$role==='keplak';
function myProkers(){global $isAdmin,$uid;return $isAdmin?rows("SELECT * FROM proker ORDER BY id"):rows("SELECT * FROM proker WHERE keplak_id=?",[$uid]);}
function canManage($pid){global $isAdmin,$uid;return $isAdmin||row("SELECT id FROM proker WHERE id=? AND keplak_id=?",[$pid,$uid]);}
function prog($pid){
  $t=row("SELECT COUNT(*) c FROM pendaftaran p JOIN tupoksi t ON t.divisi_id=p.divisi_id WHERE p.proker_id=? AND p.acc_keplak=1 AND p.acc_psdm=1",[$pid])['c'];
  $d=row("SELECT COUNT(*) c FROM pendaftaran p JOIN tupoksi t ON t.divisi_id=p.divisi_id JOIN tupoksi_progress g ON g.tupoksi_id=t.id AND g.user_id=p.user_id AND g.selesai=1 WHERE p.proker_id=? AND p.acc_keplak=1 AND p.acc_psdm=1",[$pid])['c'];
  return $t?round($d/$t*100):0;}
function bar($n){return "<div class='bar'><i style='width:$n%'></i></div><small>$n%</small>";}
function statusTeks($v){return [0=>'Menunggu',1=>'Disetujui',2=>'Ditolak'][$v];}
function notif($to,$pesan,$link=''){q("INSERT INTO notifikasi(user_id,pesan,link) VALUES(?,?,?)",[$to,$pesan,$link]);}
function notifRole($role,$pesan,$link=''){foreach(rows("SELECT id FROM users WHERE role=?",[$role]) as $x)notif($x['id'],$pesan,$link);}

// ===== AKSI POST =====
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['a'])){
  $a=$_POST['a']; $P=$_POST; $ok=true;
  if($a==='daftar'&&$role==='anggota'){
    $dv=row("SELECT * FROM divisi WHERE id=?",[$P['divisi_id']]);
    if($dv){ q("REPLACE INTO pendaftaran(user_id,proker_id,divisi_id,acc_keplak,acc_psdm) VALUES(?,?,?,0,0)",[$uid,$dv['proker_id'],$dv['id']]);
      $pr=row("SELECT * FROM proker WHERE id=?",[$dv['proker_id']]);$nt=$u['nama']." mengajukan diri ke ".$pr['nama']." (divisi ".$dv['nama'].")";
      if($pr['keplak_id'])notif($pr['keplak_id'],$nt,'acc');notifRole('admin',$nt,'acc'); }
    $msg='Pengajuan dikirim, menunggu acc Keplak & PSDM.';
  }
  elseif($a==='acc'&&($isAdmin||$isKeplak)){
    $pd=row("SELECT * FROM pendaftaran WHERE id=?",[$P['id']]);
    if($pd&&canManage($pd['proker_id'])){$col=$isAdmin?'acc_psdm':'acc_keplak';q("UPDATE pendaftaran SET $col=? WHERE id=?",[(int)$P['v'],$pd['id']]);
      $pn=row("SELECT nama FROM proker WHERE id=?",[$pd['proker_id']])['nama'];notif($pd['user_id'],"Pengajuan Anda ke $pn ".statusTeks((int)$P['v'])." oleh ".($isAdmin?'PSDM':'Keplak'),'dashboard');}
  }
  elseif($a==='tupoksi_done'){
    q("REPLACE INTO tupoksi_progress(tupoksi_id,user_id,selesai) VALUES(?,?,?)",[$P['id'],$uid,(int)$P['v']]);
  }
  elseif($a==='lpj'&&$role==='anggota'){
    if(isset($P['v'])&&$P['v']) q("INSERT IGNORE INTO lpj_validasi VALUES(?,?)",[$P['id'],$uid]); else q("DELETE FROM lpj_validasi WHERE proker_id=? AND user_id=?",[$P['id'],$uid]);
  }
  elseif($a==='tupoksi_add'&&($isAdmin||$isKeplak)){
    $dv=row("SELECT * FROM divisi WHERE id=?",[$P['divisi_id']]);
    if($dv&&canManage($dv['proker_id'])&&trim($P['deskripsi'])!=='') {q("INSERT INTO tupoksi(divisi_id,deskripsi) VALUES(?,?)",[$dv['id'],trim($P['deskripsi'])]);
      foreach(rows("SELECT user_id FROM pendaftaran WHERE divisi_id=? AND acc_keplak=1 AND acc_psdm=1",[$dv['id']]) as $m)notif($m['user_id'],"Tupoksi baru di divisi ".$dv['nama'].": ".trim($P['deskripsi']),'tupoksi');}
  }
  elseif($a==='tupoksi_del'&&($isAdmin||$isKeplak)){
    $t=row("SELECT d.proker_id FROM tupoksi t JOIN divisi d ON d.id=t.divisi_id WHERE t.id=?",[$P['id']]);
    if($t&&canManage($t['proker_id'])) q("DELETE FROM tupoksi WHERE id=?",[$P['id']]);
  }
  elseif($a==='agenda_add'&&$isKeplak&&canManage($P['proker_id'])&&$P['tanggal']){
    $pr=row("SELECT nama FROM proker WHERE id=?",[$P['proker_id']]);
    q("INSERT INTO agenda(proker_id,jenis,keterangan,tanggal) VALUES(?,?,?,?)",[$P['proker_id'],$P['jenis'],trim($P['keterangan']),$P['tanggal']]);
    notifRole('admin',"Pengajuan tanggal {$P['jenis']} ".$pr['nama']." (".date('d/m/Y',strtotime($P['tanggal'])).") menunggu persetujuan",'tanggal');
    $msg='Tanggal diajukan ke PSDM.';
  }
  elseif($a==='agenda_del'&&$isKeplak){$ag=row("SELECT proker_id FROM agenda WHERE id=?",[$P['id']]);if($ag&&canManage($ag['proker_id']))q("DELETE FROM agenda WHERE id=?",[$P['id']]);}
  elseif($a==='agenda_acc'&&$isAdmin){
    $ag=row("SELECT a.*,pr.nama pn,pr.keplak_id FROM agenda a JOIN proker pr ON pr.id=a.proker_id WHERE a.id=?",[$P['id']]);
    if($ag){$v=$P['v']==='1'?'disetujui':'ditolak';q("UPDATE agenda SET status=? WHERE id=?",[$v,$ag['id']]);
      $tx="{$ag['jenis']} ".$ag['pn']." (".date('d/m/Y',strtotime($ag['tanggal'])).")";
      if($ag['keplak_id'])notif($ag['keplak_id'],"Tanggal $tx $v oleh PSDM",'tanggal');
      if($v==='disetujui')foreach(rows("SELECT user_id FROM pendaftaran WHERE proker_id=? AND acc_keplak=1 AND acc_psdm=1",[$ag['proker_id']]) as $m)notif($m['user_id'],"Agenda baru: $tx",'dashboard');}
  }
  elseif($a==='proker_add'&&$isAdmin){
    q("INSERT INTO proker(nama,deskripsi,keplak_id) VALUES(?,?,?)",[$P['nama'],$P['deskripsi'],$P['keplak_id']?:null]);
    if($P['keplak_id'])notif($P['keplak_id'],"Anda ditunjuk sebagai ketua pelaksana: ".$P['nama'],'kelola');
  }
  elseif($a==='divisi_add'&&($isAdmin||$isKeplak)&&canManage($P['proker_id'])){
    q("INSERT INTO divisi(proker_id,nama) VALUES(?,?)",[$P['proker_id'],$P['nama']]);
  }
  elseif($a==='user_add'&&$isAdmin){
    if(row("SELECT id FROM users WHERE username=?",[$P['username']]))$msg='Username sudah dipakai.';
    elseif(strlen($P['password'])<6)$msg='Password minimal 6 karakter.';
    else{q("INSERT INTO users(nama,username,password,role) VALUES(?,?,?,?)",[trim($P['nama']),trim($P['username']),password_hash($P['password'],PASSWORD_DEFAULT),in_array($P['role'],['anggota','keplak','admin'])?$P['role']:'anggota']);$msg='Akun berhasil dibuat.';}
  }
  elseif($a==='user_del'&&$isAdmin&&$P['id']!=$uid){q("DELETE FROM users WHERE id=?",[$P['id']]);$msg='Akun dihapus.';}
  elseif($a==='user_pass'&&$isAdmin&&strlen($P['password'])>=6){q("UPDATE users SET password=? WHERE id=?",[password_hash($P['password'],PASSWORD_DEFAULT),$P['id']]);$msg='Password diganti.';}
  elseif($a==='set_keplak'&&$isAdmin){q("UPDATE proker SET keplak_id=? WHERE id=?",[$P['keplak_id']?:null,$P['id']]);$msg='Keplak proker diperbarui.';
    if($P['keplak_id'])notif($P['keplak_id'],"Anda ditunjuk sebagai ketua pelaksana: ".row("SELECT nama FROM proker WHERE id=?",[$P['id']])['nama'],'kelola');}
  elseif($a==='eval_add'&&$isAdmin){
    q("INSERT INTO evaluasi_form(judul,pertanyaan) VALUES(?,?)",[$P['judul'],$P['pertanyaan']]);
    foreach(rows("SELECT id FROM users WHERE role<>'admin'") as $x)notif($x['id'],"Form evaluasi baru: ".$P['judul'],'evaluasi');
  }
  elseif($a==='eval_isi'&&$role!=='admin'){
    q("REPLACE INTO evaluasi_jawaban(form_id,user_id,jawaban) VALUES(?,?,?)",[$P['form_id'],$uid,json_encode(array_values($P['j']),JSON_UNESCAPED_UNICODE)]);
    $msg='Evaluasi terkirim. Terima kasih!';
  }
}
$p=$_GET['p']??'dashboard';
$menu=['dashboard'=>'Dashboard'];
if($role==='anggota'){$menu+=['proker'=>'Pilih Proker','tupoksi'=>'Tupoksi Saya','lpj'=>'Validasi LPJ','evaluasi'=>'Evaluasi Pengurus'];}
if($isKeplak){$menu+=['kelola'=>'Kelola Proker Saya','acc'=>'Acc Pengajuan','tanggal'=>'Ajukan Tanggal','evaluasi'=>'Evaluasi Pengurus'];}
if($isAdmin){$menu+=['akun'=>'Kelola Akun','kelola'=>'Kelola Proker','acc'=>'Acc Pengajuan','tanggal'=>'Acc Tanggal','evaluasi'=>'Form Evaluasi'];}
$menu['notif']='Notifikasi';
if(!isset($menu[$p]))$p='dashboard';
$nl=[];if($p==='notif'){$nl=rows("SELECT * FROM notifikasi WHERE user_id=? ORDER BY id DESC LIMIT 50",[$uid]);q("UPDATE notifikasi SET dibaca=1 WHERE user_id=?",[$uid]);}
$unread=row("SELECT COUNT(*) c FROM notifikasi WHERE user_id=? AND dibaca=0",[$uid])['c'];
?>
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SIMPRO Himsika</title><link rel="stylesheet" href="style.css"></head><body>
<aside><h2>SIMPRO<br><span>Himsika</span></h2><nav>
<?php foreach($menu as $k=>$v)echo "<a href='?p=$k'".($k===$p?" class='on'":"").">".h($v).($k==='notif'&&$unread?" <span class='bdg'>$unread</span>":"")."</a>"; ?>
<a href="?p=logout" class="out">Keluar</a></nav></aside>
<main><header><h1><?=h($menu[$p])?></h1><div class="who"><a class="bell" href="?p=notif">🔔<?php if($unread)echo "<span class='bdg'>$unread</span>"; ?></a> <?=h($u['nama'])?> <b><?=strtoupper($role==='admin'?'Admin PSDM+SCBPH':$role)?></b></div></header>
<?php if($msg)echo "<div class='alert ok'>".h($msg)."</div>";

function btn($a,$f,$label,$cls=''){$x='';foreach($f as $k=>$v)$x.="<input type='hidden' name='$k' value='".h($v)."'>";return "<form method='post' class='inl'><input type='hidden' name='a' value='$a'>$x<button class='$cls'>$label</button></form>";}

if($p==='dashboard'){
  $pr=rows("SELECT p.*,u.nama kn,(SELECT MIN(tanggal) FROM agenda WHERE proker_id=p.id AND jenis='Hari H' AND status='disetujui') hh FROM proker p LEFT JOIN users u ON u.id=p.keplak_id ORDER BY p.id");
  echo "<section class='card'><h3>🔔 Notifikasi Terbaru</h3>";
  $nn=rows("SELECT * FROM notifikasi WHERE user_id=? ORDER BY id DESC LIMIT 5",[$uid]);
  if(!$nn)echo "<p class='muted'>Belum ada notifikasi.</p>";
  foreach($nn as $n)echo "<div class='nt".($n['dibaca']?'':' new')."'><span>".h($n['pesan'])."</span><small>".h(date('d M Y H:i',strtotime($n['created_at'])))."</small></div>";
  echo "<p><a href='?p=notif'>Lihat semua notifikasi →</a></p></section>";
  echo "<section class='card'><h3>Program Kerja</h3><table><tr><th>Proker</th><th>Keplak</th><th>Hari H</th><th>Progress</th></tr>";
  foreach($pr as $r)echo "<tr><td><b>".h($r['nama'])."</b><br><small>".h($r['deskripsi'])."</small></td><td>".h($r['kn']?:'-')."</td><td>".h($r['hh']?date('d/m/Y',strtotime($r['hh'])):'Belum ditetapkan')."</td><td>".bar(prog($r['id']))."</td></tr>";
  echo "</table></section>";
  // ===== Kalender setahun =====
  $y=(int)($_GET['y']??date('Y'));if($y<2000||$y>2100)$y=(int)date('Y');
  $ag=rows("SELECT a.*,pr.nama pn FROM agenda a JOIN proker pr ON pr.id=a.proker_id WHERE YEAR(a.tanggal)=? AND (a.status='disetujui' OR (a.status='diajukan' AND (?=1 OR pr.keplak_id=?))) ORDER BY a.tanggal",[$y,$isAdmin?1:0,$uid]);
  $ev=[];foreach($ag as $e)$ev[$e['tanggal']][]=$e;
  $jc=['Hari H'=>'jh','Gladi'=>'jg','Rapat'=>'jr','Survei'=>'js','Lainnya'=>'jl'];
  $bln=['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  echo "<section class='card'><div class='row'><h3 style='margin:0'>Kalender Kegiatan $y</h3><span><a href='?y=".($y-1)."'>« ".($y-1)."</a> &nbsp; <a href='?y=".date('Y')."'>Tahun ini</a> &nbsp; <a href='?y=".($y+1)."'>".($y+1)." »</a></span></div><p>";
  foreach($jc as $j=>$c)echo "<span class='lg $c'>".h($j)."</span>";
  echo "<small class='muted'>Garis putus-putus = menunggu persetujuan PSDM</small></p><div class='cals'>";
  $today=date('Y-m-d');
  for($m=1;$m<=12;$m++){
    $first=mktime(0,0,0,$m,1,$y);$dim=(int)date('t',$first);$off=(int)date('N',$first)-1;
    echo "<div class='cm'><h5>{$bln[$m]} $y</h5><div class='g'>";
    foreach(['Sn','Sl','Rb','Km','Jm','Sb','Mg'] as $dn)echo "<b>$dn</b>";
    for($i=0;$i<$off;$i++)echo "<span></span>";
    for($d=1;$d<=$dim;$d++){
      $k=sprintf('%04d-%02d-%02d',$y,$m,$d);$cls='';$tt='';
      if(isset($ev[$k])){$e0=$ev[$k][0];$cls=($jc[$e0['jenis']]??'jl').($e0['status']==='diajukan'?' pend':'');
        foreach($ev[$k] as $e)$tt.=$e['jenis'].': '.$e['pn'].($e['keterangan']?' — '.$e['keterangan']:'').($e['status']==='diajukan'?' (menunggu persetujuan)':'')."\n";}
      if($k===$today)$cls.=' t';
      echo "<i class='".trim($cls)."'".($tt?" title='".h($tt)."'":"").">$d</i>";
    }
    echo "</div></div>";
  }
  echo "</div></section><section class='card'><h3>Daftar Agenda $y</h3>";
  if(!$ag)echo "<p class='muted'>Belum ada agenda pada tahun ini.</p>";
  foreach($ag as $e)echo "<div class='row'><span><span class='lg ".($jc[$e['jenis']]??'jl')."'>".h($e['jenis'])."</span><b>".date('d M Y',strtotime($e['tanggal']))."</b> — ".h($e['pn']).($e['keterangan']?": ".h($e['keterangan']):"")."</span><small class='muted'>".($e['status']==='diajukan'?'menunggu persetujuan':'')."</small></div>";
  echo "</section>";
  if(!$isAdmin&&!$isKeplak){ echo "<section class='card'><h3>Keikutsertaan Saya</h3>";
    foreach(rows("SELECT p.*,pr.nama pn,d.nama dn FROM pendaftaran p JOIN proker pr ON pr.id=p.proker_id JOIN divisi d ON d.id=p.divisi_id WHERE p.user_id=?",[$uid]) as $r)
      echo "<p><b>".h($r['pn'])."</b> — ".h($r['dn'])." · Keplak: ".statusTeks($r['acc_keplak'])." · PSDM: ".statusTeks($r['acc_psdm'])."</p>";
    echo "</section>";}
}
elseif($p==='proker'){
  foreach(rows("SELECT * FROM proker ORDER BY id") as $r){
    $mine=row("SELECT p.*,d.nama dn FROM pendaftaran p JOIN divisi d ON d.id=p.divisi_id WHERE p.user_id=? AND p.proker_id=?",[$uid,$r['id']]);
    echo "<section class='card'><h3>".h($r['nama'])."</h3><p>".h($r['deskripsi'])."</p>";
    if($mine)echo "<p class='badge'>Divisi: ".h($mine['dn'])." · Keplak: ".statusTeks($mine['acc_keplak'])." · PSDM: ".statusTeks($mine['acc_psdm'])."</p>";
    $dv=rows("SELECT * FROM divisi WHERE proker_id=?",[$r['id']]);
    if($dv){echo "<form method='post' class='inl'><input type='hidden' name='a' value='daftar'><select name='divisi_id'>";foreach($dv as $d)echo "<option value='{$d['id']}'>".h($d['nama'])."</option>";echo "</select><button>".($mine?'Ubah Pilihan':'Ajukan Diri')."</button></form>";}else echo "<p class='muted'>Belum ada divisi.</p>";
    echo "</section>";}
}
elseif($p==='tupoksi'||($p==='kelola'&&false)){
  $list=rows("SELECT t.*,d.nama dn,pr.nama pn,COALESCE(g.selesai,0) s FROM pendaftaran p JOIN divisi d ON d.id=p.divisi_id JOIN proker pr ON pr.id=p.proker_id JOIN tupoksi t ON t.divisi_id=d.id LEFT JOIN tupoksi_progress g ON g.tupoksi_id=t.id AND g.user_id=p.user_id WHERE p.user_id=? AND p.acc_keplak=1 AND p.acc_psdm=1 ORDER BY pr.id",[$uid]);
  echo "<section class='card'>";if(!$list)echo "<p class='muted'>Belum ada tupoksi (pastikan pengajuan sudah di-acc Keplak & PSDM dan tupoksi sudah ditentukan).</p>";
  foreach($list as $t)echo "<div class='row'><span>".($t['s']?'✅':'⬜')." <b>".h($t['pn'])."</b> / ".h($t['dn']).": ".h($t['deskripsi'])."</span>".btn('tupoksi_done',['id'=>$t['id'],'v'=>$t['s']?0:1],$t['s']?'Tandai Belum':'Tandai Selesai')."</div>";
  echo "</section>";
}
elseif($p==='lpj'){
  echo "<section class='card'><p class='muted'>Validasi bahwa LPJ proker yang Anda ikuti sudah selesai.</p>";
  foreach(rows("SELECT pr.id,pr.nama,(SELECT COUNT(*) FROM lpj_validasi WHERE proker_id=pr.id AND user_id=?) v FROM pendaftaran p JOIN proker pr ON pr.id=p.proker_id WHERE p.user_id=? AND p.acc_keplak=1 AND p.acc_psdm=1",[$uid,$uid]) as $r)
    echo "<div class='row'><span>".($r['v']?'✅':'⬜')." ".h($r['nama'])."</span>".btn('lpj',['id'=>$r['id'],'v'=>$r['v']?'':1],$r['v']?'Batalkan Validasi':'Validasi LPJ Selesai')."</div>";
  echo "</section>";
}
elseif($p==='acc'){
  echo "<section class='card'><table><tr><th>Anggota</th><th>Proker / Divisi</th><th>Keplak</th><th>PSDM</th><th>Aksi (".($isAdmin?'PSDM':'Keplak').")</th></tr>";
  foreach(rows("SELECT p.*,u.nama un,pr.nama pn,d.nama dn FROM pendaftaran p JOIN users u ON u.id=p.user_id JOIN proker pr ON pr.id=p.proker_id JOIN divisi d ON d.id=p.divisi_id ORDER BY p.id DESC") as $r){
    if(!canManage($r['proker_id']))continue;
    echo "<tr><td>".h($r['un'])."</td><td>".h($r['pn'])." / ".h($r['dn'])."</td><td>".statusTeks($r['acc_keplak'])."</td><td>".statusTeks($r['acc_psdm'])."</td><td>".btn('acc',['id'=>$r['id'],'v'=>1],'Acc')." ".btn('acc',['id'=>$r['id'],'v'=>2],'Tolak','red')."</td></tr>";}
  echo "</table></section>";
}
elseif($p==='tanggal'){
  $jn=['Hari H','Gladi','Rapat','Survei','Lainnya'];
  if($isKeplak){
    $mp=myProkers();if(!$mp)echo "<section class='card'><p class='muted'>Anda belum ditunjuk sebagai ketua pelaksana proker apa pun.</p></section>";
    foreach($mp as $r){
      echo "<section class='card'><h3>".h($r['nama'])."</h3><form method='post' class='inl' style='flex-wrap:wrap'><input type='hidden' name='a' value='agenda_add'><input type='hidden' name='proker_id' value='{$r['id']}'><select name='jenis'>";
      foreach($jn as $j)echo "<option>$j</option>";
      echo "</select><input type='date' name='tanggal' required><input name='keterangan' placeholder='Keterangan (mis. Gladi resik di aula)' style='min-width:260px' required><button>Ajukan ke PSDM</button></form><table style='margin-top:12px'><tr><th>Jenis</th><th>Tanggal</th><th>Keterangan</th><th>Status</th><th></th></tr>";
      foreach(rows("SELECT * FROM agenda WHERE proker_id=? ORDER BY tanggal",[$r['id']]) as $a2)echo "<tr><td>".h($a2['jenis'])."</td><td>".date('d/m/Y',strtotime($a2['tanggal']))."</td><td>".h($a2['keterangan'])."</td><td>".h($a2['status'])."</td><td>".btn('agenda_del',['id'=>$a2['id']],'Hapus','red')."</td></tr>";
      echo "</table></section>";}
  } else {
    echo "<section class='card'><table><tr><th>Proker</th><th>Jenis</th><th>Tanggal</th><th>Keterangan</th><th>Status</th><th>Aksi</th></tr>";
    foreach(rows("SELECT a.*,pr.nama pn FROM agenda a JOIN proker pr ON pr.id=a.proker_id ORDER BY FIELD(a.status,'diajukan','disetujui','ditolak'),a.tanggal") as $a2)
      echo "<tr><td>".h($a2['pn'])."</td><td>".h($a2['jenis'])."</td><td>".date('d/m/Y',strtotime($a2['tanggal']))."</td><td>".h($a2['keterangan'])."</td><td>".h($a2['status'])."</td><td>".btn('agenda_acc',['id'=>$a2['id'],'v'=>1],'Setujui')." ".btn('agenda_acc',['id'=>$a2['id'],'v'=>0],'Tolak','red')."</td></tr>";
    echo "</table></section>";
  }
}
elseif($p==='kelola'){
  if($isAdmin){$kp=rows("SELECT id,nama FROM users WHERE role='keplak'");
    echo "<section class='card'><h3>Tambah Proker</h3><form method='post' class='stack'><input type='hidden' name='a' value='proker_add'><input name='nama' placeholder='Nama proker' required><input name='deskripsi' placeholder='Deskripsi'><select name='keplak_id'><option value=''>— Ketua pelaksana —</option>";
    foreach($kp as $k)echo "<option value='{$k['id']}'>".h($k['nama'])."</option>";echo "</select><button>Simpan</button></form></section>";}
  foreach(myProkers() as $r){
    echo "<section class='card'><h3>".h($r['nama'])."</h3><p>Progress ".bar(prog($r['id']))."</p>";
    if($isAdmin){echo "<form method='post' class='inl'><input type='hidden' name='a' value='set_keplak'><input type='hidden' name='id' value='{$r['id']}'><select name='keplak_id'><option value=''>— Belum ada keplak —</option>";foreach(rows("SELECT id,nama FROM users WHERE role='keplak'") as $k)echo "<option value='{$k['id']}'".($k['id']==$r['keplak_id']?' selected':'').">".h($k['nama'])."</option>";echo "</select><button>Simpan Keplak</button></form>";}
    foreach(rows("SELECT * FROM divisi WHERE proker_id=?",[$r['id']]) as $d){
      echo "<h4>Divisi ".h($d['nama'])."</h4>";
      foreach(rows("SELECT * FROM tupoksi WHERE divisi_id=?",[$d['id']]) as $t)echo "<div class='row'><span>• ".h($t['deskripsi'])."</span>".btn('tupoksi_del',['id'=>$t['id']],'Hapus','red')."</div>";
      echo "<form method='post' class='inl'><input type='hidden' name='a' value='tupoksi_add'><input type='hidden' name='divisi_id' value='{$d['id']}'><input name='deskripsi' placeholder='Tupoksi baru' required><button>+ Tupoksi</button></form>";}
    echo "<form method='post' class='inl' style='margin-top:12px'><input type='hidden' name='a' value='divisi_add'><input type='hidden' name='proker_id' value='{$r['id']}'><input name='nama' placeholder='Nama divisi baru' required><button>+ Divisi</button></form></section>";}
}
elseif($p==='akun'&&$isAdmin){
  echo "<section class='card'><h3>Tambah Akun</h3><form method='post' class='stack'><input type='hidden' name='a' value='user_add'><input name='nama' placeholder='Nama lengkap' required><input name='username' placeholder='Username' required><input name='password' placeholder='Password (min. 6 karakter)' required><select name='role'><option value='keplak'>Keplak (Ketua Pelaksana)</option><option value='anggota'>Anggota</option><option value='admin'>Admin (PSDM/SCBPH)</option></select><button>Buat Akun</button></form></section>";
  echo "<section class='card'><h3>Daftar Akun</h3><table><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Aksi</th></tr>";
  foreach(rows("SELECT * FROM users ORDER BY FIELD(role,'admin','keplak','anggota'),nama") as $r){
    echo "<tr><td>".h($r['nama'])."</td><td>".h($r['username'])."</td><td>".h($r['role'])."</td><td><form method='post' class='inl'><input type='hidden' name='a' value='user_pass'><input type='hidden' name='id' value='{$r['id']}'><input name='password' placeholder='Password baru' required><button>Ganti</button></form> ".($r['id']==$uid?'':btn('user_del',['id'=>$r['id']],'Hapus','red'))."</td></tr>";}
  echo "</table></section>";
}
elseif($p==='notif'){
  echo "<section class='card'>";if(!$nl)echo "<p class='muted'>Belum ada notifikasi.</p>";
  foreach($nl as $n)echo "<div class='nt".($n['dibaca']?'':' new')."'><span>".h($n['pesan'])."</span><small>".h(date('d M Y H:i',strtotime($n['created_at'])))."</small>".($n['link']?" <a href='?p=".h($n['link'])."'>Buka</a>":"")."</div>";
  echo "</section>";
}
elseif($p==='evaluasi'){
  if($isAdmin){
    echo "<section class='card'><h3>Upload Form Evaluasi</h3><form method='post' class='stack'><input type='hidden' name='a' value='eval_add'><input name='judul' placeholder='Judul form' required><textarea name='pertanyaan' rows='4' placeholder='Satu pertanyaan per baris' required></textarea><button>Upload</button></form></section>";
    foreach(rows("SELECT * FROM evaluasi_form ORDER BY id DESC") as $f){$qs=array_filter(array_map('trim',explode("\n",$f['pertanyaan'])));
      echo "<section class='card'><h3>".h($f['judul'])."</h3>";
      foreach(rows("SELECT j.jawaban,u.nama FROM evaluasi_jawaban j JOIN users u ON u.id=j.user_id WHERE form_id=?",[$f['id']]) as $j){$a=json_decode($j['jawaban'],true);echo "<div class='resp'><b>".h($j['nama'])."</b>";foreach(array_values($qs) as $i=>$qq)echo "<p><small>".h($qq)."</small><br>".h($a[$i]??'-')."</p>";echo "</div>";}
      echo "</section>";}
  } else foreach(rows("SELECT * FROM evaluasi_form ORDER BY id DESC") as $f){
    $done=row("SELECT id FROM evaluasi_jawaban WHERE form_id=? AND user_id=?",[$f['id'],$uid]);
    echo "<section class='card'><h3>".h($f['judul'])."</h3>".($done?"<p class='badge'>Sudah diisi (dapat diperbarui)</p>":"")."<form method='post' class='stack'><input type='hidden' name='a' value='eval_isi'><input type='hidden' name='form_id' value='{$f['id']}'>";
    foreach(array_values(array_filter(array_map('trim',explode("\n",$f['pertanyaan'])))) as $i=>$qq)echo "<label>".h($qq)."</label><textarea name='j[$i]' rows='2' required></textarea>";
    echo "<button>Kirim</button></form></section>";}
}
?></main></body></html>
