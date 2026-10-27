<?php
// admin/export.php
require_once '../config/db.php';

// Cek login
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header('Location: login.php');
    exit;
}

// RBAC
$admin_role = $_SESSION['admin_role'] ?? 'panitia';
if (!in_array($admin_role, ['superadmin', 'panitia', 'bendahara', 'yayasan'])) {
    die("Akses Ditolak. Anda tidak memiliki izin ke halaman ini.");
}

// Helper format XML safe string
function safe_xml($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// Filter params
$search = sanitize($_GET['search'] ?? '');
$filter_jalur = sanitize($_GET['filter_jalur'] ?? '');
$filter_bayar = sanitize($_GET['filter_bayar'] ?? '');
$filter_seleksi = sanitize($_GET['filter_seleksi'] ?? '');
$filter_gelombang = sanitize($_GET['filter_gelombang'] ?? '');
$filter_hadir = sanitize($_GET['filter_hadir'] ?? '');

$query_sql = "SELECT p.*, COALESCE(g.nama_gelombang, 'Gelombang Umum') as nama_gelombang, g.tahun_ajaran FROM pendaftaran p LEFT JOIN gelombang g ON p.gelombang_id = g.id WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query_sql .= " AND (p.nama_santri LIKE :search OR p.no_registrasi LIKE :search OR p.nik_santri LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($filter_jalur)) {
    $query_sql .= " AND p.jalur_pendaftaran = :jalur";
    $params['jalur'] = $filter_jalur;
}

if (!empty($filter_bayar)) {
    $query_sql .= " AND p.status_pembayaran = :bayar";
    $params['bayar'] = $filter_bayar;
}

if (!empty($filter_seleksi)) {
    $query_sql .= " AND p.status_seleksi = :seleksi";
    $params['seleksi'] = $filter_seleksi;
}

if (!empty($filter_gelombang)) {
    $query_sql .= " AND p.gelombang_id = :gelombang";
    $params['gelombang'] = $filter_gelombang;
}

if ($filter_hadir === 'Hadir') {
    $query_sql .= " AND p.status_kehadiran = 'Hadir'";
} elseif ($filter_hadir === 'Belum') {
    $query_sql .= " AND (p.status_kehadiran = 'Belum Hadir' OR p.status_kehadiran IS NULL)";
}

$query_sql .= " ORDER BY p.id DESC";

$stmt = $db->prepare($query_sql);
$stmt->execute($params);
$pendaftar_list = $stmt->fetchAll();

// Set Header untuk Download Excel (.xls format XML Spreadsheet 2003 resmi)
$filename = "data_psb_babul_jannah_" . date('Ymd_His') . ".xls";

header("Content-Description: File Transfer");
header("Content-Disposition: attachment; filename=$filename");
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Pragma: no-cache");
header("Expires: 0");

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal">
   <Alignment ss:Vertical="Center"/>
   <Borders/>
   <Font ss:FontName="Segoe UI" x:Family="Swiss" ss:Size="10" ss:Color="#1E293B"/>
   <Interior/>
   <NumberFormat/>
   <Protection/>
  </Style>
  <Style ss:ID="Title">
   <Alignment ss:Vertical="Center"/>
   <Font ss:FontName="Segoe UI" x:Family="Swiss" ss:Size="15" ss:Color="#064E3B" ss:Bold="1"/>
  </Style>
  <Style ss:ID="SubTitle">
   <Alignment ss:Vertical="Center"/>
   <Font ss:FontName="Segoe UI" x:Family="Swiss" ss:Size="10" ss:Color="#475569" ss:Italic="1"/>
  </Style>
  <Style ss:ID="Header">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#047857"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#047857"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#047857"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#047857"/>
   </Borders>
   <Font ss:FontName="Segoe UI" x:Family="Swiss" ss:Size="10" ss:Color="#FFFFFF" ss:Bold="1"/>
   <Interior ss:Color="#065F46" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="Cell">
   <Alignment ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="CellCenter">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="CellText">
   <Alignment ss:Vertical="Center"/>
   <NumberFormat ss:Format="@"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="CellNumber">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <NumberFormat ss:Format="0"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <!-- Style Pembayaran -->
  <Style ss:ID="BayarLunas">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Segoe UI" ss:Size="10" ss:Color="#065F46" ss:Bold="1"/>
   <Interior ss:Color="#D1FAE5" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="BayarVerif">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Segoe UI" ss:Size="10" ss:Color="#92400E" ss:Bold="1"/>
   <Interior ss:Color="#FEF3C7" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="BayarBelum">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Segoe UI" ss:Size="10" ss:Color="#991B1B" ss:Bold="1"/>
   <Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <!-- Style Seleksi -->
  <Style ss:ID="StatusLulus">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Segoe UI" ss:Size="10" ss:Color="#065F46" ss:Bold="1"/>
   <Interior ss:Color="#D1FAE5" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="StatusGagal">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Segoe UI" ss:Size="10" ss:Color="#991B1B" ss:Bold="1"/>
   <Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
  <Style ss:ID="StatusWait">
   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
   <Font ss:FontName="Segoe UI" ss:Size="10" ss:Color="#475569"/>
   <Interior ss:Color="#F1F5F9" ss:Pattern="Solid"/>
   <Borders>
    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>
   </Borders>
  </Style>
 </Styles>
 <Worksheet ss:Name="Data PSB Santri">
  <Table>
   <!-- Pengaturan Lebar Kolom (Width) agar rapi & tidak terpotong -->
   <Column ss:Width="110"/> <!-- 0: No Registrasi -->
   <Column ss:Width="100"/> <!-- 1: Gelombang -->
   <Column ss:Width="85"/>  <!-- 2: Tahun Ajaran -->
   <Column ss:Width="110"/> <!-- 3: Jalur -->
   <Column ss:Width="180"/> <!-- 4: Nama Santri -->
   <Column ss:Width="125"/> <!-- 5: NIK Santri -->
   <Column ss:Width="95"/>  <!-- 6: NISN -->
   <Column ss:Width="125"/> <!-- 7: No KK -->
   <Column ss:Width="115"/> <!-- 8: Tempat Lahir -->
   <Column ss:Width="90"/>  <!-- 9: Tanggal Lahir -->
   <Column ss:Width="85"/>  <!-- 10: JK -->
   <Column ss:Width="230"/> <!-- 11: Alamat -->
   <Column ss:Width="150"/> <!-- 12: Nama Ayah -->
   <Column ss:Width="125"/> <!-- 13: NIK Ayah -->
   <Column ss:Width="110"/> <!-- 14: WA Ayah -->
   <Column ss:Width="115"/> <!-- 15: Pekerjaan Ayah -->
   <Column ss:Width="115"/> <!-- 16: Penghasilan Ayah -->
   <Column ss:Width="150"/> <!-- 17: Nama Ibu -->
   <Column ss:Width="125"/> <!-- 18: NIK Ibu -->
   <Column ss:Width="110"/> <!-- 19: WA Ibu -->
   <Column ss:Width="115"/> <!-- 20: Pekerjaan Ibu -->
   <Column ss:Width="115"/> <!-- 21: Penghasilan Ibu -->
   <Column ss:Width="140"/> <!-- 22: Nama Wali -->
   <Column ss:Width="110"/> <!-- 23: WA Wali -->
   <Column ss:Width="160"/> <!-- 24: Sekolah Asal -->
   <Column ss:Width="95"/>  <!-- 25: NPSN -->
   <Column ss:Width="85"/>  <!-- 26: TB -->
   <Column ss:Width="85"/>  <!-- 27: BB -->
   <Column ss:Width="85"/>  <!-- 28: LK -->
   <Column ss:Width="130"/> <!-- 29: Riwayat Penyakit -->
   <Column ss:Width="130"/> <!-- 30: Status Pembayaran -->
   <Column ss:Width="120"/> <!-- 31: Status Seleksi -->
   <Column ss:Width="120"/> <!-- 32: Status Kehadiran -->
   <Column ss:Width="150"/> <!-- 33: Waktu Check-in -->
   <Column ss:Width="120"/> <!-- 34: Tanggal Daftar -->

   <!-- Judul Laporan -->
   <Row ss:Height="25">
    <Cell ss:MergeAcross="10" ss:StyleID="Title"><Data ss:Type="String">LAPORAN DATA SANTRI BARU - PSB BABUL JANNAH</Data></Cell>
   </Row>
   <Row ss:Height="18">
    <Cell ss:MergeAcross="10" ss:StyleID="SubTitle"><Data ss:Type="String">Diekspor pada: <?= date('d/m/Y H:i') ?> WIB | Total Data: <?= count($pendaftar_list) ?> Calon Santri</Data></Cell>
   </Row>
   <Row ss:Height="10"/>

   <!-- Baris Header Tabel -->
   <Row ss:Height="28">
    <Cell ss:StyleID="Header"><Data ss:Type="String">No. Registrasi</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Gelombang</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Tahun Ajaran</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Jalur Pendaftaran</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Nama Santri</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">NIK Santri</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">NISN</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">No. KK</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Tempat Lahir</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Tanggal Lahir</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Jenis Kelamin</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Alamat Lengkap</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Nama Ayah</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">NIK Ayah</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">No. HP Ayah</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Pekerjaan Ayah</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Penghasilan Ayah</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Nama Ibu</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">NIK Ibu</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">No. HP Ibu</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Pekerjaan Ibu</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Penghasilan Ibu</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Nama Wali</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">No. HP Wali</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Sekolah Asal</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">NPSN</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Tinggi Badan (cm)</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Berat Badan (kg)</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Lingkar Kepala (cm)</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Riwayat Penyakit</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Status Pembayaran</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Status Seleksi</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Status Kehadiran</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Waktu Check-in</Data></Cell>
    <Cell ss:StyleID="Header"><Data ss:Type="String">Tanggal Daftar</Data></Cell>
   </Row>
   <?php foreach ($pendaftar_list as $row): 
       // Tentukan style warna untuk Status Pembayaran
       $bayar_style = 'CellCenter';
       if ($row['status_pembayaran'] === 'Lunas') $bayar_style = 'BayarLunas';
       elseif ($row['status_pembayaran'] === 'Menunggu Verifikasi') $bayar_style = 'BayarVerif';
       elseif ($row['status_pembayaran'] === 'Belum Bayar') $bayar_style = 'BayarBelum';

       // Tentukan style warna untuk Status Seleksi
       $seleksi_style = 'StatusWait';
       if ($row['status_seleksi'] === 'Lulus') $seleksi_style = 'StatusLulus';
       elseif ($row['status_seleksi'] === 'Tidak Lulus') $seleksi_style = 'StatusGagal';
       
       $tb = is_numeric($row['tinggi_badan']) ? $row['tinggi_badan'] : 0;
       $bb = is_numeric($row['berat_badan']) ? $row['berat_badan'] : 0;
       $lk = is_numeric($row['lingkar_kepala']) ? $row['lingkar_kepala'] : 0;
   ?>
   <Row ss:Height="22">
    <Cell ss:StyleID="CellText"><Data ss:Type="String"><?= safe_xml($row['no_registrasi']) ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String"><?= safe_xml($row['nama_gelombang']) ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String"><?= safe_xml($row['tahun_ajaran']) ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String"><?= safe_xml($row['jalur_pendaftaran']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= safe_xml($row['nama_santri']) ?></Data></Cell>
    <Cell ss:StyleID="CellText"><Data ss:Type="String"><?= safe_xml($row['nik_santri']) ?></Data></Cell>
    <Cell ss:StyleID="CellText"><Data ss:Type="String"><?= safe_xml($row['nisn']) ?></Data></Cell>
    <Cell ss:StyleID="CellText"><Data ss:Type="String"><?= safe_xml($row['no_kk']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= safe_xml($row['tempat_lahir']) ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String"><?= safe_xml($row['tgl_lahir']) ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String"><?= safe_xml($row['jk']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= safe_xml($row['alamat_lengkap']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= safe_xml($row['nama_ayah']) ?></Data></Cell>
    <Cell ss:StyleID="CellText"><Data ss:Type="String"><?= safe_xml($row['nik_ayah']) ?></Data></Cell>
    <Cell ss:StyleID="CellText"><Data ss:Type="String"><?= safe_xml($row['wa_ayah']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= safe_xml($row['pekerjaan_ayah']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= safe_xml($row['penghasilan_ayah']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= safe_xml($row['nama_ibu']) ?></Data></Cell>
    <Cell ss:StyleID="CellText"><Data ss:Type="String"><?= safe_xml($row['nik_ibu']) ?></Data></Cell>
    <Cell ss:StyleID="CellText"><Data ss:Type="String"><?= safe_xml($row['wa_ibu']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= safe_xml($row['pekerjaan_ibu']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= safe_xml($row['penghasilan_ibu']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= safe_xml($row['nama_wali']) ?></Data></Cell>
    <Cell ss:StyleID="CellText"><Data ss:Type="String"><?= safe_xml($row['wa_wali']) ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= safe_xml($row['nama_sekolah_asal']) ?></Data></Cell>
    <Cell ss:StyleID="CellText"><Data ss:Type="String"><?= safe_xml($row['npsn_sekolah_asal']) ?></Data></Cell>
    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number"><?= $tb ?></Data></Cell>
    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number"><?= $bb ?></Data></Cell>
    <Cell ss:StyleID="CellNumber"><Data ss:Type="Number"><?= $lk ?></Data></Cell>
    <Cell ss:StyleID="Cell"><Data ss:Type="String"><?= safe_xml($row['riwayat_penyakit']) ?></Data></Cell>
    <Cell ss:StyleID="<?= $bayar_style ?>"><Data ss:Type="String"><?= safe_xml($row['status_pembayaran']) ?></Data></Cell>
    <Cell ss:StyleID="<?= $seleksi_style ?>"><Data ss:Type="String"><?= safe_xml($row['status_seleksi']) ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String"><?= safe_xml($row['status_kehadiran'] ?: 'Belum Hadir') ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String"><?= safe_xml($row['waktu_kehadiran'] ?: '-') ?></Data></Cell>
    <Cell ss:StyleID="CellCenter"><Data ss:Type="String"><?= safe_xml($row['created_at']) ?></Data></Cell>
   </Row>
   <?php endforeach; ?>
  </Table>
  <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
   <FreezePanes/>
   <FrozenNoSplit/>
   <SplitHorizontal>4</SplitHorizontal>
   <TopRowBottomPane>4</TopRowBottomPane>
   <ActivePane>2</ActivePane>
  </WorksheetOptions>
 </Worksheet>
</Workbook>
<?php
exit();
?>
