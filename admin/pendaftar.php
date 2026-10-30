<?php
// admin/pendaftar.php
require_once '../config/db.php';
require_once '../config/mail.php';

// Proteksi admin
if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    header("Location: login.php");
    exit();
}

$admin_role = $_SESSION['admin_role'] ?? 'panitia';
$error = '';
$success = '';

function clean_cell_val($val) {
    return trim(strip_tags((string)$val));
}

// Helper untuk format URL WhatsApp resmi
function get_wa_url($phone, $nama, $no_reg, $status_bayar, $status_seleksi) {
    if (empty($phone)) return '#';
    $clean = preg_replace('/[^0-9]/', '', (string)$phone);
    if (strpos($clean, '0') === 0) {
        $clean = '62' . substr($clean, 1);
    } elseif (strpos($clean, '62') !== 0) {
        $clean = '62' . $clean;
    }
    $pesan = "Assalamu'alaikum Wr. Wb. Bapak/Ibu wali santri *{$nama}* (No. Registrasi: *{$no_reg}*).\n\nKami dari Panitia PSB Pondok Pesantren Babul Jannah Dumai ingin menginformasikan status pendaftaran ananda:\n- Status Pembayaran: *{$status_bayar}*\n- Hasil Seleksi: *{$status_seleksi}*\n\nJika ada pertanyaan lebih lanjut, silakan balas pesan ini. Jazakumullahu khairan.";
    return "https://wa.me/{$clean}?text=" . rawurlencode($pesan);
}

// 1. Aksi Hapus Pendaftar
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0 && in_array($admin_role, ['superadmin', 'panitia'])) {
        try {
            // Ambil info bukti transfer jika ada untuk hapus file fisik
            $stmt_file = $db->prepare("SELECT bukti_bayar_tes FROM pendaftaran WHERE id = :id");
            $stmt_file->execute(['id' => $id]);
            $old_file = $stmt_file->fetchColumn();
            if ($old_file && file_exists(__DIR__ . '/../uploads/' . $old_file)) {
                @unlink(__DIR__ . '/../uploads/' . $old_file);
            }

            $stmt = $db->prepare("DELETE FROM pendaftaran WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $count_rem = (int)$db->query("SELECT COUNT(*) FROM pendaftaran")->fetchColumn();
            if ($count_rem === 0) {
                $db->query("ALTER TABLE pendaftaran AUTO_INCREMENT = 1");
            }
            $success = "Data pendaftar berhasil dihapus dari sistem.";
        } catch (PDOException $e) {
            $error = "Gagal menghapus data: " . $e->getMessage();
        }
    }
}

// 1.2. Aksi Update Status (dari Modal Inline)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (in_array($admin_role, ['superadmin', 'panitia', 'bendahara'])) {
        $id = (int)$_POST['id'];
        $status_bayar = clean_cell_val($_POST['status_pembayaran'] ?? '');
        $metode_bayar = clean_cell_val($_POST['metode_pembayaran'] ?? 'Transfer');
        $status_seleksi = clean_cell_val($_POST['status_seleksi'] ?? '');
        
        try {
            // Cek status sebelum update
            $stmt_old = $db->prepare("SELECT status_pembayaran, email, no_registrasi, nama_santri, wa_ayah FROM pendaftaran WHERE id = :id");
            $stmt_old->execute(['id' => $id]);
            $old_data = $stmt_old->fetch();

            $stmt = $db->prepare("UPDATE pendaftaran SET 
                status_pembayaran = :bayar,
                metode_pembayaran = :metode,
                status_seleksi = :seleksi,
                tgl_lunas = CASE WHEN :bayar = 'Lunas' THEN COALESCE(tgl_lunas, NOW()) ELSE NULL END 
                WHERE id = :id");
            $stmt->execute([
                'bayar' => $status_bayar,
                'metode' => $metode_bayar,
                'seleksi' => $status_seleksi,
                'id' => $id
            ]);
            
            // Jika berubah menjadi Lunas, kirim email notifikasi
            if ($old_data && $old_data['status_pembayaran'] !== 'Lunas' && $status_bayar === 'Lunas') {
                send_email_verifikasi_lunas($old_data);
            }

            $success = "Status santri berhasil diperbarui.";
        } catch (PDOException $e) {
            $error = "Gagal memperbarui status: " . $e->getMessage();
        }
    } else {
        $error = "Akses ditolak. Anda tidak memiliki izin untuk mengubah status.";
    }
}

// 1.3. Konfirmasi / Tolak Pembayaran (dari Tombol Bukti Bayar)
if (isset($_GET['action']) && in_array($_GET['action'], ['confirm_payment', 'reject_payment'])) {
    if (in_array($admin_role, ['superadmin', 'bendahara', 'panitia'])) {
        $id = (int)($_GET['id'] ?? 0);
        $new_status = ($_GET['action'] === 'confirm_payment') ? 'Lunas' : 'Belum Bayar';
        
        if ($id > 0) {
            try {
                $stmt_old = $db->prepare("SELECT status_pembayaran, email, no_registrasi, nama_santri, wa_ayah FROM pendaftaran WHERE id = :id");
                $stmt_old->execute(['id' => $id]);
                $old_data = $stmt_old->fetch();
                
                $stmt = $db->prepare("UPDATE pendaftaran SET 
                    status_pembayaran = :status,
                    tgl_lunas = CASE WHEN :status = 'Lunas' THEN COALESCE(tgl_lunas, NOW()) ELSE NULL END 
                    WHERE id = :id");
                $stmt->execute(['status' => $new_status, 'id' => $id]);
                
                if ($_GET['action'] === 'confirm_payment') {
                    $success = "Pembayaran berhasil diverifikasi (Status: Lunas).";
                    if ($old_data && $old_data['status_pembayaran'] !== 'Lunas') {
                        send_email_verifikasi_lunas($old_data);
                    }
                } else {
                    $success = "Pembayaran ditolak (Status dikembalikan ke Belum Bayar).";
                }
            } catch (PDOException $e) {
                $error = "Gagal memproses pembayaran: " . $e->getMessage();
            }
        }
    } else {
        $error = "Akses ditolak. Anda tidak memiliki hak untuk memverifikasi pembayaran.";
    }
}

// 1.4. Aksi Batch Massal (Hadir Ujian / Lunas / Lulus)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_action') {
    if (in_array($admin_role, ['superadmin', 'panitia', 'bendahara'])) {
        $batch_type = clean_cell_val($_POST['batch_type'] ?? '');
        $selected_ids = array_filter(array_map('intval', $_POST['selected_ids'] ?? []));
        
        if (!empty($selected_ids)) {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            try {
                if ($batch_type === 'set_hadir' && in_array($admin_role, ['superadmin', 'panitia'])) {
                    $stmt = $db->prepare("UPDATE pendaftaran SET status_kehadiran = 'Hadir', waktu_kehadiran = NOW() WHERE id IN ($placeholders)");
                    $stmt->execute($selected_ids);
                    $success = count($selected_ids) . " santri berhasil ditandai Hadir Ujian Seleksi.";
                } elseif ($batch_type === 'set_lunas' && in_array($admin_role, ['superadmin', 'bendahara', 'panitia'])) {
                    $stmt = $db->prepare("UPDATE pendaftaran SET status_pembayaran = 'Lunas', tgl_lunas = COALESCE(tgl_lunas, NOW()) WHERE id IN ($placeholders)");
                    $stmt->execute($selected_ids);
                    $success = count($selected_ids) . " santri berhasil diverifikasi Lunas.";
                } elseif ($batch_type === 'set_lulus' && in_array($admin_role, ['superadmin', 'panitia'])) {
                    $stmt = $db->prepare("UPDATE pendaftaran SET status_seleksi = 'Lulus' WHERE id IN ($placeholders)");
                    $stmt->execute($selected_ids);
                    $success = count($selected_ids) . " santri berhasil ditandai Lulus Seleksi.";
                }
            } catch (PDOException $e) {
                $error = "Gagal memproses aksi massal: " . $e->getMessage();
            }
        } else {
            $error = "Pilih setidaknya satu santri untuk melakukan aksi massal.";
        }
    }
}

// 1.5. Aksi Import Status (Excel/CSV) Massal
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_status') {
    if (in_array($admin_role, ['superadmin', 'panitia']) && isset($_FILES['file_import'])) {
        $file = $_FILES['file_import'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $tmp_name = $file['tmp_name'];
            $content = file_get_contents($tmp_name);
            
            $updated_count = 0;
            $lunas_count = 0;
            $rows_data = [];
            
            if ($ext === 'csv') {
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    if (trim($line) !== '') {
                        $sep = strpos($line, "\t") !== false ? "\t" : ",";
                        $rows_data[] = str_getcsv($line, $sep);
                    }
                }
            } elseif ($ext === 'xls' || $ext === 'xlsx') {
                if (strpos($content, '<table') !== false) {
                    preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $content, $matches);
                    if (!empty($matches[1])) {
                        foreach ($matches[1] as $tr) {
                            preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/is', $tr, $tds);
                            if (!empty($tds[1])) {
                                $row = [];
                                foreach ($tds[1] as $td) {
                                    $row[] = trim(strip_tags($td));
                                }
                                $rows_data[] = $row;
                            }
                        }
                    }
                } elseif (strpos($content, '<?xml') !== false && strpos($content, 'Workbook') !== false) {
                    preg_match_all('/<Row[^>]*>(.*?)<\/Row>/is', $content, $rows_matches);
                    if (!empty($rows_matches[1])) {
                        foreach ($rows_matches[1] as $row_xml) {
                            preg_match_all('/<Data[^>]*>(.*?)<\/Data>/is', $row_xml, $data_matches);
                            if (!empty($data_matches[1])) {
                                $row = [];
                                foreach ($data_matches[1] as $data_val) {
                                    $row[] = trim(strip_tags($data_val));
                                }
                                $rows_data[] = $row;
                            }
                        }
                    }
                } else {
                    if (file_exists('biff8_reader.php')) {
                        require_once 'biff8_reader.php';
                        if (function_exists('read_biff8_simple')) {
                            $biff8 = read_biff8_simple($content);
                            if ($biff8 && is_array($biff8)) {
                                $rows_data = $biff8;
                            }
                        }
                    }
                    if (empty($rows_data)) {
                        $lines = explode("\n", $content);
                        foreach ($lines as $line) {
                            if (trim($line) !== '') {
                                $sep = strpos($line, "\t") !== false ? "\t" : ",";
                                $rows_data[] = str_getcsv($line, $sep);
                            }
                        }
                    }
                }
            }
            
            // Cleanup & Normalize Array
            if (!empty($rows_data)) {
                $new_rows = [];
                foreach ($rows_data as $r) {
                    if (is_array($r) && count($r) === 1 && strpos($r[0], ',') !== false) {
                        $sep = ',';
                        $cell_str = $r[0];
                        if (strpos($cell_str, ';') !== false && substr_count($cell_str, ';') > substr_count($cell_str, ',')) {
                            $sep = ';';
                        }
                        $split_vals = str_getcsv($cell_str, $sep);
                        $new_rows[] = array_map('clean_cell_val', $split_vals);
                    } else {
                        $new_rows[] = is_array($r) ? array_map('clean_cell_val', $r) : [$r];
                    }
                }
                $rows_data = $new_rows;
            }

            if (!empty($rows_data)) {
                $header_idx = -1;
                foreach ($rows_data as $r_idx => $row) {
                    if (count($row) < 5) continue;
                    foreach ($row as $cell) {
                        $c_low = strtolower(trim($cell));
                        if (strpos($c_low, 'reg') !== false || strpos($c_low, 'no.') !== false || strpos($c_low, 'nomor') !== false || strpos($c_low, 'status') !== false || $c_low === 'id') {
                            $header_idx = $r_idx;
                            break 2;
                        }
                    }
                }
                
                if ($header_idx !== -1) {
                    $headers = $rows_data[$header_idx];
                    $data_rows = array_slice($rows_data, $header_idx + 1);
                } else {
                    $headers = $rows_data[0];
                    $data_rows = $rows_data;
                }
                
                $col_reg = -1;
                $col_bayar = -1;
                $col_seleksi = -1;
                $col_hadir = -1;
                
                foreach ($headers as $idx => $h) {
                    $clean_h = strtolower(trim($h));
                    if ($col_reg === -1 && (strpos($clean_h, 'reg') !== false || strpos($clean_h, 'no.') !== false || strpos($clean_h, 'nomor') !== false || $clean_h === 'id' || strpos($clean_h, 'kode') !== false)) {
                        $col_reg = $idx;
                    } elseif ($col_bayar === -1 && (strpos($clean_h, 'status pembayaran') !== false || strpos($clean_h, 'bayar') !== false || strpos($clean_h, 'pembayaran') !== false || strpos($clean_h, 'lunas') !== false || strpos($clean_h, 'tagihan') !== false || strpos($clean_h, 'payment') !== false)) {
                        $col_bayar = $idx;
                    } elseif ($col_seleksi === -1 && (strpos($clean_h, 'status seleksi') !== false || strpos($clean_h, 'seleksi') !== false || strpos($clean_h, 'lulus') !== false || strpos($clean_h, 'hasil seleksi') !== false || strpos($clean_h, 'terima') !== false)) {
                        $col_seleksi = $idx;
                    } elseif ($col_hadir === -1 && (strpos($clean_h, 'kehadiran') !== false || strpos($clean_h, 'hadir') !== false || strpos($clean_h, 'absen') !== false)) {
                        $col_hadir = $idx;
                    }
                }
                
                if ($col_reg === -1 || $col_bayar === -1 || $col_seleksi === -1 || $col_hadir === -1) {
                    foreach (array_slice($data_rows, 0, 10) as $sample_row) {
                        foreach ($sample_row as $idx => $val) {
                            $v_clean = trim((string)$val);
                            $v_low = strtolower($v_clean);
                            if ($col_reg === -1 && (stripos($v_clean, 'REG-') === 0 || preg_match('/^REG[\-\_\s\d]+/i', $v_clean))) {
                                $col_reg = $idx;
                            }
                            if ($col_bayar === -1 && (in_array($v_low, ['lunas', 'belum bayar', 'menunggu verifikasi', 'verifikasi', 'cicil']) || strpos($v_low, 'lunas') !== false || strpos($v_low, 'bayar') !== false)) {
                                $col_bayar = $idx;
                            }
                            if ($col_seleksi === -1 && (in_array($v_low, ['lulus', 'tidak lulus', 'gagal', 'belum seleksi', 'diterima', 'cadangan']) || $v_low === 'lulus' || $v_low === 'gagal')) {
                                $col_seleksi = $idx;
                            }
                            if ($col_hadir === -1 && (in_array($v_low, ['hadir', 'belum hadir', 'absen', 'tidak hadir']))) {
                                $col_hadir = $idx;
                            }
                        }
                    }
                }

                if ($col_reg === -1) $col_reg = 0;
                if ($col_bayar === -1 && count($headers) >= 31) $col_bayar = 30;
                elseif ($col_bayar === -1 && count($headers) >= 3) $col_bayar = 1;
                
                if ($col_seleksi === -1 && count($headers) >= 32) $col_seleksi = 31;
                elseif ($col_seleksi === -1 && count($headers) >= 3) $col_seleksi = 2;
                
                if ($col_reg !== -1 && ($col_bayar !== -1 || $col_seleksi !== -1 || $col_hadir !== -1)) {
                    $stmt_check = $db->prepare("SELECT id, status_pembayaran, status_seleksi, status_kehadiran, no_registrasi, nama_santri, wa_ayah, email FROM pendaftaran WHERE no_registrasi = :no_reg");
                    
                    foreach ($data_rows as $row) {
                        if (!isset($row[$col_reg]) || empty(trim($row[$col_reg]))) continue;
                        
                        $no_reg = trim($row[$col_reg]);
                        $new_bayar = ($col_bayar !== -1 && isset($row[$col_bayar])) ? $row[$col_bayar] : '';
                        $new_seleksi = ($col_seleksi !== -1 && isset($row[$col_seleksi])) ? $row[$col_seleksi] : '';
                        $new_hadir = ($col_hadir !== -1 && isset($row[$col_hadir])) ? $row[$col_hadir] : '';
                        
                        $stmt_check->execute(['no_reg' => $no_reg]);
                        $curr_data = $stmt_check->fetch();
                        
                        if ($curr_data) {
                            $update_fields = [];
                            $update_params = ['id' => $curr_data['id']];
                            
                            $clean_bayar = strtolower($new_bayar);
                            $target_bayar = '';
                            if (strpos($clean_bayar, 'belum bayar') !== false && strpos($clean_bayar, 'lunas') !== false) {
                                $target_bayar = '';
                            } elseif (strpos($clean_bayar, 'lunas') !== false || strpos($clean_bayar, 'sudah bayar') !== false || strpos($clean_bayar, 'paid') !== false) {
                                $target_bayar = 'Lunas';
                            } elseif (strpos($clean_bayar, 'verifi') !== false || strpos($clean_bayar, 'tunggu') !== false || strpos($clean_bayar, 'cicil') !== false) {
                                $target_bayar = 'Menunggu Verifikasi';
                            } elseif (strpos($clean_bayar, 'belum') !== false || strpos($clean_bayar, 'unpaid') !== false) {
                                $target_bayar = 'Belum Bayar';
                            }
                            
                            if ($target_bayar !== '' && $target_bayar !== $curr_data['status_pembayaran']) {
                                $update_fields[] = "status_pembayaran = :bayar";
                                $update_params['bayar'] = $target_bayar;
                                if ($target_bayar === 'Lunas') {
                                    $update_fields[] = "tgl_lunas = COALESCE(tgl_lunas, NOW())";
                                    $lunas_count++;
                                }
                            }
                            
                            $clean_seleksi = strtolower($new_seleksi);
                            $target_seleksi = '';
                            if (strpos($clean_seleksi, 'belum seleksi') !== false && strpos($clean_seleksi, 'tidak lulus') !== false) {
                                $target_seleksi = '';
                            } elseif (strpos($clean_seleksi, 'tidak lulus') !== false || strpos($clean_seleksi, 'gagal') !== false || strpos($clean_seleksi, 'tolak') !== false) {
                                $target_seleksi = 'Tidak Lulus';
                            } elseif (strpos($clean_seleksi, 'lulus') !== false || strpos($clean_seleksi, 'diterima') !== false || strpos($clean_seleksi, 'pass') !== false) {
                                $target_seleksi = 'Lulus';
                            } elseif (strpos($clean_seleksi, 'belum') !== false || strpos($clean_seleksi, 'proses') !== false) {
                                $target_seleksi = 'Belum Seleksi';
                            }
                            
                            if ($target_seleksi !== '' && $target_seleksi !== $curr_data['status_seleksi']) {
                                $update_fields[] = "status_seleksi = :seleksi";
                                $update_params['seleksi'] = $target_seleksi;
                            }
                            
                            $clean_hadir = strtolower($new_hadir);
                            $target_hadir = '';
                            if (strpos($clean_hadir, 'belum') !== false || strpos($clean_hadir, 'antri') !== false || strpos($clean_hadir, 'tidak') !== false) {
                                $target_hadir = 'Belum Hadir';
                            } elseif (strpos($clean_hadir, 'hadir') !== false) {
                                $target_hadir = 'Hadir';
                            }
                            
                            if ($target_hadir !== '' && $target_hadir !== $curr_data['status_kehadiran']) {
                                $update_fields[] = "status_kehadiran = :hadir";
                                $update_params['hadir'] = $target_hadir;
                                if ($target_hadir === 'Hadir') {
                                    $update_fields[] = "waktu_kehadiran = NOW()";
                                }
                            }
                            
                            if (!empty($update_fields)) {
                                $sql_update = "UPDATE pendaftaran SET " . implode(', ', $update_fields) . " WHERE id = :id";
                                $stmt_do_update = $db->prepare($sql_update);
                                $stmt_do_update->execute($update_params);
                                $updated_count++;
                                
                                if (isset($update_params['bayar']) && $update_params['bayar'] === 'Lunas') {
                                    send_email_verifikasi_lunas($curr_data);
                                }
                            }
                        }
                    }
                }
            }
            
            if ($updated_count > 0) {
                $success = "Berhasil mengimpor dan memperbarui status <strong>$updated_count</strong> santri sekaligus (<strong>$lunas_count</strong> santri otomatis terverifikasi Lunas).";
            } else {
                $error = "File berhasil diproses, namun tidak ada perubahan status yang disimpan. Pastikan Anda telah mengedit kolom Status Pembayaran atau Status Seleksi sebelum mengunggah.";
            }
        }
    } else {
        $error = "Silakan pilih file Excel (.xls) atau CSV yang valid terlebih dahulu.";
    }
}

// 2. Filter & Pencarian
$search = sanitize($_GET['search'] ?? '');
$filter_jalur = sanitize($_GET['filter_jalur'] ?? '');
$filter_gelombang = sanitize($_GET['filter_gelombang'] ?? '');
$filter_bayar = sanitize($_GET['filter_bayar'] ?? '');
$filter_seleksi = sanitize($_GET['filter_seleksi'] ?? '');

$query_sql = "SELECT p.*, COALESCE(g.nama_gelombang, '-') as nama_gelombang FROM pendaftaran p LEFT JOIN gelombang g ON p.gelombang_id = g.id WHERE 1=1";
$count_sql = "SELECT COUNT(*) FROM pendaftaran p LEFT JOIN gelombang g ON p.gelombang_id = g.id WHERE 1=1";
$params = [];

if (!empty($search)) {
    $search_clause = " AND (p.nama_santri LIKE :search OR p.no_registrasi LIKE :search OR p.nik_santri LIKE :search OR p.nisn LIKE :search OR p.kabupaten LIKE :search)";
    $query_sql .= $search_clause;
    $count_sql .= $search_clause;
    $params['search'] = '%' . $search . '%';
}

if (!empty($filter_jalur)) {
    $jalur_clause = " AND p.jalur_pendaftaran = :jalur";
    $query_sql .= $jalur_clause;
    $count_sql .= $jalur_clause;
    $params['jalur'] = $filter_jalur;
}

if (!empty($filter_bayar)) {
    $bayar_clause = " AND p.status_pembayaran = :bayar";
    $query_sql .= $bayar_clause;
    $count_sql .= $bayar_clause;
    $params['bayar'] = $filter_bayar;
}

if (!empty($filter_seleksi)) {
    $seleksi_clause = " AND p.status_seleksi = :seleksi";
    $query_sql .= $seleksi_clause;
    $count_sql .= $seleksi_clause;
    $params['seleksi'] = $filter_seleksi;
}

if (!empty($filter_gelombang)) {
    $gel_clause = " AND p.gelombang_id = :gelombang";
    $query_sql .= $gel_clause;
    $count_sql .= $gel_clause;
    $params['gelombang'] = $filter_gelombang;
}

// Pagination Logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Count total
$stmt_count = $db->prepare($count_sql);
$stmt_count->execute($params);
$total_data = (int)$stmt_count->fetchColumn();
$total_pages = max(1, ceil($total_data / $limit));

$query_sql .= " ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
$stmt_list = $db->prepare($query_sql);
$stmt_list->execute($params);
$pendaftar_list = $stmt_list->fetchAll();

// 3. Ambil Statistik Pendaftar Terlengkap (Real-time)
$stmt_stat = $db->query("SELECT 
    COUNT(*) as total_semua,
    SUM(CASE WHEN jalur_pendaftaran = 'SMP IT' THEN 1 ELSE 0 END) as total_smp,
    SUM(CASE WHEN jalur_pendaftaran = 'SMA IT' THEN 1 ELSE 0 END) as total_sma,
    SUM(CASE WHEN status_pembayaran = 'Menunggu Verifikasi' THEN 1 ELSE 0 END) as total_verifikasi,
    SUM(CASE WHEN status_pembayaran = 'Lunas' THEN 1 ELSE 0 END) as total_lunas,
    SUM(CASE WHEN status_seleksi = 'Lulus' THEN 1 ELSE 0 END) as total_lulus
FROM pendaftaran");
$stat = $stmt_stat->fetch(PDO::FETCH_ASSOC);

// 4. Ambil Daftar Gelombang untuk Filter
$stmt_gel = $db->query("SELECT id, nama_gelombang, tahun_ajaran FROM gelombang ORDER BY id DESC");
$gelombang_list = $stmt_gel->fetchAll(PDO::FETCH_ASSOC);

// Helper Query String Builder untuk Pagination & Filter Chips
function build_filter_query($overrides = []) {
    $params = [
        'search' => $_GET['search'] ?? '',
        'filter_jalur' => $_GET['filter_jalur'] ?? '',
        'filter_gelombang' => $_GET['filter_gelombang'] ?? '',
        'filter_bayar' => $_GET['filter_bayar'] ?? '',
        'filter_seleksi' => $_GET['filter_seleksi'] ?? ''
    ];
    $merged = array_merge($params, $overrides);
    return http_build_query(array_filter($merged, function($v) { return $v !== '' && $v !== null; }));
}

$page_title = 'Data Pendaftar Santri';
include 'includes/head.php';
?>
<body class="bg-gray-100 flex h-screen overflow-hidden text-[#2F2522]">

    <!-- Desktop Sidebar Navigation -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-grow flex flex-col h-full overflow-hidden min-w-0">
        
        <!-- Header Navbar -->
        <?php include 'includes/header.php'; ?>

        <!-- Scrollable Dashboard Main Grid -->
        <main class="flex-grow p-4 sm:p-6 lg:p-7 overflow-y-auto space-y-6 custom-scrollbar bg-gray-50/70">
            
            <!-- Alert Notifikasi -->
            <?php if ($error): ?>
            <div class="bg-red-50 border-2 border-red-400 text-red-900 px-5 py-4 rounded-2xl text-xs sm:text-sm flex items-center justify-between shadow-sm animate-fadeIn">
                <div class="flex items-center space-x-3">
                    <i class="fa-solid fa-circle-exclamation text-red-600 text-xl flex-shrink-0"></i>
                    <div class="font-bold whitespace-pre-wrap break-words leading-relaxed"><?= $error ?></div>
                </div>
                <button onclick="this.parentElement.style.display='none'" class="text-red-600 hover:text-red-900 font-bold p-1"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="bg-emerald-50 border-2 border-emerald-400 text-emerald-900 px-5 py-4 rounded-2xl text-xs sm:text-sm flex items-center justify-between shadow-sm animate-fadeIn">
                <div class="flex items-center space-x-3">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-xl flex-shrink-0"></i>
                    <div class="font-bold whitespace-pre-wrap break-words leading-relaxed"><?= $success ?></div>
                </div>
                <button onclick="this.parentElement.style.display='none'" class="text-emerald-700 hover:text-emerald-950 font-bold p-1"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <?php endif; ?>

            <!-- Ringkasan Statistik 4 KPI Cards (Clean, Solid Brand Styling) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                
                <!-- Card 1: Total Pendaftar -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Total Calon Santri</span>
                        <div class="w-11 h-11 rounded-xl bg-[#5C3E35]/10 text-brand-primary flex items-center justify-center text-xl shadow-sm border border-[#5C3E35]/20">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-3xl font-black text-[#2F2522] tracking-tight">
                            <?= number_format($stat['total_semua'] ?? 0) ?>
                        </div>
                        <div class="flex items-center space-x-2 mt-2.5 pt-2.5 border-t border-gray-100 text-xs font-semibold text-gray-600">
                            <span class="px-2 py-0.5 bg-gray-100 rounded-md font-bold text-gray-800">SMP: <?= $stat['total_smp'] ?? 0 ?></span>
                            <span class="px-2 py-0.5 bg-gray-100 rounded-md font-bold text-gray-800">SMA: <?= $stat['total_sma'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Menunggu Verifikasi Pembayaran -->
                <a href="pendaftar.php?filter_bayar=Menunggu+Verifikasi" class="block bg-white p-5 rounded-2xl shadow-sm border border-gray-200 hover:border-amber-400 hover:shadow-md transition-all group">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Verifikasi Bayar</span>
                        <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shadow-sm border border-amber-200 group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-receipt <?= ($stat['total_verifikasi'] ?? 0) > 0 ? 'animate-pulse' : '' ?>"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-3xl font-black text-amber-600 tracking-tight">
                            <?= number_format($stat['total_verifikasi'] ?? 0) ?>
                        </div>
                        <div class="flex items-center space-x-1.5 mt-2.5 pt-2.5 border-t border-gray-100 text-xs font-semibold">
                            <?php if (($stat['total_verifikasi'] ?? 0) > 0): ?>
                                <span class="w-2 h-2 rounded-full bg-amber-500 animate-ping"></span>
                                <span class="text-amber-800 font-bold">Klik untuk validasi struk &rarr;</span>
                            <?php else: ?>
                                <i class="fa-solid fa-check text-emerald-600 text-xs"></i>
                                <span class="text-emerald-700 font-semibold">Semua sudah diverifikasi</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>

                <!-- Card 3: Lunas Biaya Tes -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Lunas Biaya Tes</span>
                        <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-xl shadow-sm border border-emerald-200">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-3xl font-black text-emerald-700 tracking-tight">
                            <?= number_format($stat['total_lunas'] ?? 0) ?>
                        </div>
                        <div class="flex items-center justify-between mt-2.5 pt-2.5 border-t border-gray-100 text-xs font-semibold text-gray-600">
                            <span>Total Kas:</span>
                            <span class="font-bold text-emerald-800">Rp <?= number_format(($stat['total_lunas'] ?? 0) * 350000, 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Lulus Seleksi -->
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 hover:shadow-md transition-all">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold text-gray-500 uppercase tracking-wider">Lulus Seleksi</span>
                        <div class="w-11 h-11 rounded-xl bg-brand-secondary/20 text-brand-primary flex items-center justify-center text-xl shadow-sm border border-brand-secondary/30">
                            <i class="fa-solid fa-award"></i>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-3xl font-black text-brand-primary tracking-tight">
                            <?= number_format($stat['total_lulus'] ?? 0) ?>
                        </div>
                        <div class="flex items-center space-x-1.5 mt-2.5 pt-2.5 border-t border-gray-100 text-xs font-semibold text-gray-600">
                            <i class="fa-solid fa-chart-pie text-brand-secondary text-xs"></i>
                            <span><?= ($stat['total_semua'] ?? 0) > 0 ? round((($stat['total_lulus'] ?? 0) / $stat['total_semua']) * 100) : 0 ?>% dari total santri</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Quick Workflow Preset Chips -->
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-extrabold text-gray-500 uppercase tracking-wider mr-1">Filter Cepat:</span>
                
                <a href="pendaftar.php" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= empty($filter_bayar) && empty($filter_seleksi) && empty($filter_jalur) ? 'bg-brand-primary text-white shadow-sm' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200' ?>">
                    Semua Data (<?= $stat['total_semua'] ?? 0 ?>)
                </a>

                <a href="pendaftar.php?filter_bayar=Menunggu+Verifikasi" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 <?= $filter_bayar === 'Menunggu Verifikasi' ? 'bg-amber-600 text-white shadow-sm' : 'bg-white text-amber-800 hover:bg-amber-50 border border-amber-300' ?>">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <span>Butuh Verifikasi (<?= $stat['total_verifikasi'] ?? 0 ?>)</span>
                </a>

                <a href="pendaftar.php?filter_bayar=Lunas" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 <?= $filter_bayar === 'Lunas' ? 'bg-emerald-700 text-white shadow-sm' : 'bg-white text-emerald-800 hover:bg-emerald-50 border border-emerald-300' ?>">
                    <i class="fa-solid fa-check text-[10px]"></i>
                    <span>Lunas (<?= $stat['total_lunas'] ?? 0 ?>)</span>
                </a>

                <a href="pendaftar.php?filter_bayar=Belum+Bayar" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition <?= $filter_bayar === 'Belum Bayar' ? 'bg-gray-800 text-white shadow-sm' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200' ?>">
                    Belum Bayar
                </a>

                <a href="pendaftar.php?filter_seleksi=Lulus" class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition flex items-center space-x-1.5 <?= $filter_seleksi === 'Lulus' ? 'bg-brand-secondary text-white shadow-sm' : 'bg-white text-[#705047] hover:bg-amber-50 border border-brand-secondary/40' ?>">
                    <i class="fa-solid fa-award text-[10px]"></i>
                    <span>Lulus Seleksi (<?= $stat['total_lulus'] ?? 0 ?>)</span>
                </a>
            </div>

            <!-- Panel Filter & Pencarian Lanjutan -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 sm:p-6 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between pb-3 border-b border-gray-100 gap-3">
                    <h3 class="font-extrabold text-[#2F2522] text-sm uppercase tracking-wider flex items-center">
                        <i class="fa-solid fa-filter mr-2 text-brand-primary"></i> Pencarian &amp; Filter Lanjutan
                    </h3>
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Tombol Tambah Santri -->
                        <?php if (in_array($admin_role, ['superadmin', 'panitia'])): ?>
                        <a href="tambah_pendaftar.php" class="inline-flex items-center space-x-1.5 bg-brand-primary hover:bg-brand-deep text-white font-bold px-3.5 py-2 rounded-xl text-xs shadow-sm transition">
                            <i class="fa-solid fa-user-plus text-xs"></i>
                            <span>+ Input Santri Baru</span>
                        </a>
                        <?php endif; ?>

                        <!-- Tombol Ekspor Excel -->
                        <a href="export.php?<?= build_filter_query() ?>" class="btn-export-excel inline-flex items-center space-x-1.5 font-bold px-3.5 py-2 rounded-xl text-xs shadow-sm transition" style="background-color: #15803D !important; color: #FFFFFF !important; border: 1px solid #166534 !important;">
                            <i class="fa-solid fa-file-excel text-xs"></i>
                            <span>Ekspor Excel</span>
                        </a>

                        <!-- Tombol Impor Excel -->
                        <?php if (in_array($admin_role, ['superadmin', 'panitia'])): ?>
                        <button type="button" onclick="showImportModal()" class="btn-import-excel inline-flex items-center space-x-1.5 font-bold px-3.5 py-2 rounded-xl text-xs shadow-sm transition" style="background-color: #1D4ED8 !important; color: #FFFFFF !important; border: 1px solid #1E40AF !important;">
                            <i class="fa-solid fa-file-import text-xs"></i>
                            <span>Impor Status</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <form action="pendaftar.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
                    
                    <!-- Search Input -->
                    <div class="lg:col-span-2">
                        <label for="search" class="block text-xs font-extrabold text-gray-700 uppercase mb-1.5">Kata Kunci Pencarian</label>
                        <div class="relative">
                            <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>" placeholder="Nama, No. Registrasi, NIK, Kota..." class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl pl-9 pr-4 py-2.5 text-xs font-semibold text-gray-800 bg-gray-50 focus:bg-white transition focus:outline-none">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-gray-400 text-xs"></i>
                        </div>
                    </div>

                    <!-- Filter Jalur -->
                    <div>
                        <label for="filter_jalur" class="block text-xs font-extrabold text-gray-700 uppercase mb-1.5">Jalur / Jenjang</label>
                        <select name="filter_jalur" id="filter_jalur" class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-3 py-2.5 text-xs font-semibold text-gray-800 bg-gray-50 focus:bg-white transition focus:outline-none">
                            <option value="">Semua Jalur</option>
                            <option value="SMP IT" <?= $filter_jalur === 'SMP IT' ? 'selected' : '' ?>>SMP IT</option>
                            <option value="SMA IT" <?= $filter_jalur === 'SMA IT' ? 'selected' : '' ?>>SMA IT</option>
                            <option value="Kepondokan" <?= $filter_jalur === 'Kepondokan' ? 'selected' : '' ?>>Kepondokan</option>
                        </select>
                    </div>

                    <!-- Filter Gelombang -->
                    <div>
                        <label for="filter_gelombang" class="block text-xs font-extrabold text-gray-700 uppercase mb-1.5">Gelombang</label>
                        <select name="filter_gelombang" id="filter_gelombang" class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-3 py-2.5 text-xs font-semibold text-gray-800 bg-gray-50 focus:bg-white transition focus:outline-none">
                            <option value="">Semua Gelombang</option>
                            <?php foreach ($gelombang_list as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= $filter_gelombang == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_gelombang'] . ' (' . $g['tahun_ajaran'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Status Bayar -->
                    <div>
                        <label for="filter_bayar" class="block text-xs font-extrabold text-gray-700 uppercase mb-1.5">Status Bayar</label>
                        <select name="filter_bayar" id="filter_bayar" class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-3 py-2.5 text-xs font-semibold text-gray-800 bg-gray-50 focus:bg-white transition focus:outline-none">
                            <option value="">Semua Status</option>
                            <option value="Belum Bayar" <?= $filter_bayar === 'Belum Bayar' ? 'selected' : '' ?>>Belum Bayar</option>
                            <option value="Menunggu Verifikasi" <?= $filter_bayar === 'Menunggu Verifikasi' ? 'selected' : '' ?>>Menunggu Verifikasi</option>
                            <option value="Lunas" <?= $filter_bayar === 'Lunas' ? 'selected' : '' ?>>Lunas</option>
                        </select>
                    </div>

                    <!-- Filter Status Seleksi -->
                    <div>
                        <label for="filter_seleksi" class="block text-xs font-extrabold text-gray-700 uppercase mb-1.5">Hasil Seleksi</label>
                        <select name="filter_seleksi" id="filter_seleksi" class="w-full border border-gray-300 focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 rounded-xl px-3 py-2.5 text-xs font-semibold text-gray-800 bg-gray-50 focus:bg-white transition focus:outline-none">
                            <option value="">Semua Hasil</option>
                            <option value="Belum Seleksi" <?= $filter_seleksi === 'Belum Seleksi' ? 'selected' : '' ?>>Belum Seleksi</option>
                            <option value="Lulus" <?= $filter_seleksi === 'Lulus' ? 'selected' : '' ?>>Lulus</option>
                            <option value="Tidak Lulus" <?= $filter_seleksi === 'Tidak Lulus' ? 'selected' : '' ?>>Tidak Lulus</option>
                        </select>
                    </div>

                    <!-- Submit & Reset Buttons -->
                    <div class="sm:col-span-2 lg:col-span-6 flex justify-end space-x-2 pt-2 border-t border-gray-100">
                        <a href="pendaftar.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-4 py-2 rounded-xl text-xs transition border border-gray-200">Reset</a>
                        <button type="submit" class="bg-brand-primary hover:bg-brand-deep text-white font-bold px-5 py-2 rounded-xl text-xs shadow transition">Terapkan Filter</button>
                    </div>

                </form>
            </div>

            <!-- Sticky Batch Action Toolbar (Hadir / Lunas / Lulus Massal) -->
            <form id="form-batch" action="pendaftar.php" method="POST">
                <input type="hidden" name="action" value="batch_action">
                <input type="hidden" id="batch-type" name="batch_type" value="">

                <div id="batch-toolbar" class="hidden mb-3 p-3.5 bg-brand-deep text-white rounded-2xl shadow-lg border border-[#3A241E] items-center justify-between flex-wrap gap-3 animate-fadeIn">
                    <div class="flex items-center space-x-3 text-xs font-bold">
                        <i class="fa-solid fa-list-check text-brand-secondary text-base"></i>
                        <span><span id="selected-count" class="font-extrabold text-white bg-brand-secondary px-2 py-0.5 rounded-md">0</span> Santri Terpilih</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <?php if (in_array($admin_role, ['superadmin', 'panitia'])): ?>
                        <button type="button" onclick="submitBatch('set_hadir')" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition flex items-center space-x-1 shadow-sm">
                            <i class="fa-solid fa-user-check"></i>
                            <span>Tandai Hadir Ujian</span>
                        </button>
                        <button type="button" onclick="submitBatch('set_lulus')" class="px-3 py-1.5 rounded-xl bg-brand-secondary hover:bg-[#8D6559] text-white font-bold text-xs transition flex items-center space-x-1 shadow-sm">
                            <i class="fa-solid fa-award"></i>
                            <span>Tandai Lulus Seleksi</span>
                        </button>
                        <?php endif; ?>

                        <?php if (in_array($admin_role, ['superadmin', 'bendahara', 'panitia'])): ?>
                        <button type="button" onclick="submitBatch('set_lunas')" class="px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs transition flex items-center space-x-1 shadow-sm">
                            <i class="fa-solid fa-circle-check"></i>
                            <span>Validasi Lunas</span>
                        </button>
                        <?php endif; ?>

                        <button type="button" onclick="clearBatchSelection()" class="px-3 py-1.5 rounded-xl bg-white/15 hover:bg-white/25 text-white font-bold text-xs transition">
                            Batal
                        </button>
                    </div>
                </div>

                <!-- Tabel Data Pendaftar (Sleek Modern UI-UX Pro Max) -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead>
                                <tr class="bg-gray-100 border-b border-gray-200 text-gray-700 uppercase tracking-wider font-extrabold text-[11px]">
                                    <th class="px-4 py-3.5 text-center w-10">
                                        <input type="checkbox" id="check-all" onchange="toggleSelectAll(this)" class="w-4 h-4 rounded text-brand-primary focus:ring-brand-primary border-gray-300 cursor-pointer">
                                    </th>
                                    <th class="px-4 py-3.5">Calon Santri &amp; No. Reg</th>
                                    <th class="px-4 py-3.5">Jalur &amp; Gelombang</th>
                                    <th class="px-4 py-3.5">Wali &amp; Kontak</th>
                                    <th class="px-4 py-3.5">Status Pembayaran</th>
                                    <th class="px-4 py-3.5">Hasil Seleksi &amp; Presensi</th>
                                    <th class="px-4 py-3.5 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                                <?php if (count($pendaftar_list) === 0): ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                            <i class="fa-solid fa-folder-open text-4xl mb-3 text-gray-300 block"></i>
                                            <p class="font-bold text-gray-700 text-sm">Tidak ada data pendaftar yang cocok dengan filter pencarian.</p>
                                            <p class="text-xs text-gray-400 mt-1">Coba gunakan kata kunci lain atau klik tombol Reset Filter di atas.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pendaftar_list as $p): ?>
                                        <tr class="hover:bg-amber-50/30 transition-colors">
                                            <!-- Checkbox Selection -->
                                            <td class="px-4 py-3.5 text-center">
                                                <input type="checkbox" name="selected_ids[]" value="<?= $p['id'] ?>" onchange="updateBatchToolbar()" class="row-checkbox w-4 h-4 rounded text-brand-primary focus:ring-brand-primary border-gray-300 cursor-pointer">
                                            </td>

                                            <!-- Santri Info & No Reg -->
                                            <td class="px-4 py-3.5">
                                                <div class="flex items-center space-x-3">
                                                    <?php 
                                                    $is_putra = (strtoupper($p['jk'] ?? '') === 'L' || stripos($p['jk'] ?? '', 'LAKI') !== false);
                                                    ?>
                                                    <div class="w-9 h-9 rounded-xl <?= $is_putra ? 'bg-blue-100 text-blue-700 border border-blue-200' : 'bg-pink-100 text-pink-700 border border-pink-200' ?> font-black text-xs flex items-center justify-center flex-shrink-0 shadow-2xs" title="<?= $is_putra ? 'Santri Putra' : 'Santri Putri' ?>">
                                                        <?= htmlspecialchars(strtoupper(substr($p['nama_santri'], 0, 1))) ?>
                                                    </div>
                                                    <div>
                                                        <div class="flex items-center space-x-1.5">
                                                            <a href="detail_pendaftar.php?id=<?= $p['id'] ?>" class="font-extrabold text-gray-900 hover:text-brand-primary text-xs uppercase leading-tight transition" title="Lihat Detail Santri">
                                                                <?= htmlspecialchars($p['nama_santri']) ?>
                                                            </a>
                                                            <?php if (isset($p['kategori_santri']) && $p['kategori_santri'] === 'Pindahan'): ?>
                                                                <span class="bg-indigo-50 text-indigo-700 border border-indigo-200 text-[9px] px-1.5 py-0.2 rounded font-black">Pindahan</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-[10px] text-gray-500 font-medium mt-1 flex items-center space-x-2">
                                                            <span class="font-mono font-bold text-brand-primary bg-amber-50/80 px-1.5 py-0.5 rounded border border-brand-secondary/30"><?= htmlspecialchars($p['no_registrasi']) ?></span>
                                                            <span>&bull;</span>
                                                            <span><?= htmlspecialchars($p['kabupaten'] ?: 'Indonesia') ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <!-- Jalur & Gelombang -->
                                            <td class="px-4 py-3.5">
                                                <span class="inline-block px-2.5 py-0.5 rounded-md text-[11px] font-bold bg-[#5C3E35]/10 text-brand-primary border border-[#5C3E35]/20">
                                                    <?= htmlspecialchars($p['jalur_pendaftaran']) ?>
                                                </span>
                                                <span class="block text-[10px] text-gray-500 font-semibold mt-1">
                                                    <?= htmlspecialchars($p['nama_gelombang']) ?>
                                                </span>
                                            </td>
                                            
                                            <!-- Kontak Wali & WhatsApp -->
                                            <td class="px-4 py-3.5">
                                                <div class="text-xs">
                                                    <span class="font-bold text-gray-800 block text-[11px] truncate max-w-[130px]"><?= htmlspecialchars($p['nama_ayah'] ?: ($p['nama_ibu'] ?: 'Wali Santri')) ?></span>
                                                    <?php if (!empty($p['wa_ayah'])): ?>
                                                        <a href="<?= get_wa_url($p['wa_ayah'], $p['nama_santri'], $p['no_registrasi'], $p['status_pembayaran'], $p['status_seleksi']) ?>" target="_blank" class="inline-flex items-center space-x-1 text-[10px] font-bold text-emerald-700 hover:text-emerald-800 mt-0.5 group" title="Chat WhatsApp Wali">
                                                            <i class="fa-brands fa-whatsapp text-emerald-600 text-xs"></i>
                                                            <span class="font-mono"><?= htmlspecialchars($p['wa_ayah']) ?></span>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-[10px] text-gray-400 italic">Tanpa Kontak</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <!-- Status Bayar & Bukti -->
                                            <td class="px-4 py-3.5">
                                                <div class="flex items-center space-x-2">
                                                    <?php if (in_array($admin_role, ['superadmin', 'panitia', 'bendahara'])): ?>
                                                    <div onclick="showControlModal(<?= $p['id'] ?>, '<?= $p['no_registrasi'] ?>', '<?= addslashes($p['nama_santri']) ?>', '<?= $p['status_pembayaran'] ?>', '<?= $p['status_seleksi'] ?>', '<?= htmlspecialchars($p['metode_pembayaran'] ?? 'Transfer') ?>')" class="cursor-pointer transition hover:scale-105 inline-block" title="Klik untuk ubah status pembayaran">
                                                    <?php else: ?>
                                                    <div class="inline-block">
                                                    <?php endif; ?>
                                                        <?php if ($p['status_pembayaran'] === 'Belum Bayar'): ?>
                                                            <span class="bg-gray-100 text-gray-700 text-[10px] px-2.5 py-1 rounded-full border border-gray-300 font-bold inline-flex items-center">
                                                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400 mr-1.5"></span> Belum Bayar
                                                            </span>
                                                        <?php elseif ($p['status_pembayaran'] === 'Menunggu Verifikasi'): ?>
                                                            <span class="bg-amber-100 text-amber-900 text-[10px] px-2.5 py-1 rounded-full border border-amber-300 font-bold inline-flex items-center animate-pulse">
                                                                <i class="fa-solid fa-spinner animate-spin mr-1 text-amber-600"></i> Menunggu
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="bg-emerald-50 text-emerald-800 text-[10px] px-2.5 py-1 rounded-full border border-emerald-300 font-bold inline-flex items-center">
                                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 mr-1.5"></span> Lunas
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if (!empty($p['bukti_bayar_tes'])): ?>
                                                        <?php $bukti_full_url = get_storage_url('', $p['bukti_bayar_tes'], '', true); ?>
                                                        <button type="button" onclick="showBuktiModal('<?= htmlspecialchars($bukti_full_url, ENT_QUOTES) ?>', <?= $p['id'] ?>, '<?= $p['status_pembayaran'] ?>', '<?= htmlspecialchars($p['bukti_bayar_tes'], ENT_QUOTES) ?>')" class="p-1.5 text-brand-primary hover:text-brand-deep hover:bg-brand-primary/10 rounded-md transition" title="Lihat Struk Transfer">
                                                            <i class="fa-solid fa-image text-xs"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <!-- Status Seleksi & Kehadiran -->
                                            <td class="px-4 py-3.5">
                                                <div class="flex items-center space-x-2">
                                                    <?php if (in_array($admin_role, ['superadmin', 'panitia'])): ?>
                                                    <div onclick="showControlModal(<?= $p['id'] ?>, '<?= $p['no_registrasi'] ?>', '<?= addslashes($p['nama_santri']) ?>', '<?= $p['status_pembayaran'] ?>', '<?= $p['status_seleksi'] ?>', '<?= htmlspecialchars($p['metode_pembayaran'] ?? 'Transfer') ?>')" class="cursor-pointer transition hover:scale-105 inline-block" title="Klik untuk ubah hasil seleksi">
                                                    <?php else: ?>
                                                    <div class="inline-block">
                                                    <?php endif; ?>
                                                        <?php if ($p['status_seleksi'] === 'Belum Seleksi'): ?>
                                                            <span class="bg-gray-100 text-gray-600 text-[10px] px-2 py-0.5 rounded-full font-bold inline-flex items-center border border-gray-200">
                                                                Belum Seleksi
                                                            </span>
                                                        <?php elseif ($p['status_seleksi'] === 'Tidak Lulus'): ?>
                                                            <span class="bg-red-50 text-red-700 text-[10px] px-2 py-0.5 rounded-full border border-red-200 font-bold inline-flex items-center">
                                                                <i class="fa-solid fa-xmark mr-1"></i> Tidak Lulus
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="bg-brand-secondary/20 text-[#6E493F] text-[10px] px-2 py-0.5 rounded-full border border-brand-secondary/40 font-bold inline-flex items-center">
                                                                <i class="fa-solid fa-award mr-1 text-brand-primary"></i> Lulus
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if ($p['status_kehadiran'] === 'Hadir'): ?>
                                                        <span class="bg-emerald-100 text-emerald-800 text-[9px] px-1.5 py-0.5 rounded font-bold inline-flex items-center shadow-2xs" title="Hadir Ujian (<?= date('d/m H:i', strtotime($p['waktu_kehadiran'])) ?>)">
                                                            <i class="fa-solid fa-check mr-0.5"></i> Hadir
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-gray-400 text-[9px] font-medium" title="Belum Hadir">
                                                            <i class="fa-regular fa-clock"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <!-- Aksi Per Row -->
                                            <td class="px-4 py-3.5 text-center">
                                                <div class="flex items-center justify-center space-x-1.5">
                                                    <!-- Tombol Detail Lengkap (Utama) -->
                                                    <a href="detail_pendaftar.php?id=<?= $p['id'] ?>" class="btn-detail-santri inline-flex items-center space-x-1 font-bold px-2.5 py-1.5 rounded-lg text-xs shadow-xs transition" title="Lihat Detail Lengkap Santri">
                                                        <i class="fa-solid fa-eye text-[11px]"></i>
                                                        <span class="hidden sm:inline">Detail</span>
                                                    </a>

                                                    <!-- Tombol Status Modal -->
                                                    <?php if (in_array($admin_role, ['superadmin', 'panitia', 'bendahara'])): ?>
                                                    <button type="button" onclick="showControlModal(<?= $p['id'] ?>, '<?= $p['no_registrasi'] ?>', '<?= addslashes($p['nama_santri']) ?>', '<?= $p['status_pembayaran'] ?>', '<?= $p['status_seleksi'] ?>', '<?= htmlspecialchars($p['metode_pembayaran'] ?? 'Transfer') ?>')" class="w-7 h-7 bg-gray-100 hover:bg-[#5C3E35] hover:text-white text-gray-700 rounded-lg transition border border-gray-200 flex items-center justify-center" title="Ubah Status Pembayaran / Seleksi">
                                                        <i class="fa-solid fa-sliders text-xs"></i>
                                                    </button>
                                                    <?php endif; ?>

                                                    <!-- Tombol Edit Data Lengkap -->
                                                    <?php if (in_array($admin_role, ['superadmin', 'panitia'])): ?>
                                                    <a href="edit_pendaftar.php?id=<?= $p['id'] ?>" class="w-7 h-7 bg-gray-100 hover:bg-brand-primary hover:text-white text-gray-700 rounded-lg transition border border-gray-200 flex items-center justify-center" title="Edit Biodata Lengkap">
                                                        <i class="fa-solid fa-user-pen text-xs"></i>
                                                    </a>
                                                    <?php endif; ?>

                                                    <!-- Tombol Hapus -->
                                                    <?php if (in_array($admin_role, ['superadmin', 'panitia'])): ?>
                                                    <button type="button" onclick="confirmDeletePendaftar(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['nama_santri'])) ?>', '<?= $p['no_registrasi'] ?>')" class="w-7 h-7 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white rounded-lg transition border border-red-200 flex items-center justify-center" title="Hapus Data Santri">
                                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Bar (Preserves All Filters) -->
                    <div class="border-t border-gray-200 bg-gray-50/80 p-4 sm:p-5 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div class="text-xs text-gray-500 font-semibold">
                            Menampilkan <span class="font-extrabold text-gray-800"><?= min($offset + 1, $total_data) ?></span> &ndash; <span class="font-extrabold text-gray-800"><?= min($offset + $limit, $total_data) ?></span> dari total <span class="font-black text-brand-primary bg-[#5C3E35]/10 px-2 py-0.5 rounded"><?= $total_data ?></span> calon santri
                        </div>

                        <?php if ($total_pages > 1): ?>
                        <nav class="flex items-center space-x-1">
                            <!-- Prev Button -->
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>&<?= build_filter_query() ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 transition shadow-xs">
                                    <i class="fa-solid fa-chevron-left text-xs"></i>
                                </a>
                            <?php else: ?>
                                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 border border-gray-200 text-gray-300 cursor-not-allowed">
                                    <i class="fa-solid fa-chevron-left text-xs"></i>
                                </span>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <div class="flex space-x-1 px-1">
                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                if ($start_page > 1) {
                                    echo '<a href="?page=1&'.build_filter_query().'" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-300 text-xs font-bold text-gray-700 hover:bg-gray-100 transition shadow-xs">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span class="w-8 h-8 flex items-center justify-center text-gray-400 text-xs">...</span>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                    <?php if ($i == $page): ?>
                                        <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-brand-primary text-xs font-black text-white shadow-sm"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?= $i ?>&<?= build_filter_query() ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-300 text-xs font-bold text-gray-700 hover:bg-gray-100 transition shadow-xs"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="w-8 h-8 flex items-center justify-center text-gray-400 text-xs">...</span>';
                                    }
                                    echo '<a href="?page='.$total_pages.'&'.build_filter_query().'" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-300 text-xs font-bold text-gray-700 hover:bg-gray-100 transition shadow-xs">'.$total_pages.'</a>';
                                }
                                ?>
                            </div>

                            <!-- Next Button -->
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?= $page + 1 ?>&<?= build_filter_query() ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 transition shadow-xs">
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </a>
                            <?php else: ?>
                                <span class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 border border-gray-200 text-gray-300 cursor-not-allowed">
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </span>
                            <?php endif; ?>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

        </main>
    </div>

    <!-- Modal Bukti Bayar (Modern Zoom & Actions) -->
    <div id="bukti-modal" class="hidden fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 space-y-4 shadow-2xl relative border border-gray-200">
            <button onclick="hideBuktiModal()" type="button" class="absolute right-5 top-5 text-gray-400 hover:text-gray-700 w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
            <div class="border-b border-gray-100 pb-3">
                <h3 class="font-extrabold text-brand-primary text-base flex items-center">
                    <i class="fa-solid fa-file-invoice-dollar mr-2 text-brand-secondary"></i> Verifikasi Bukti Transfer
                </h3>
                <p class="text-xs text-gray-500 mt-0.5">Periksa keaslian nomor rekening tujuan, nominal transfer, dan tanggal struk</p>
            </div>
            
            <div class="w-full flex flex-col items-center justify-center bg-gray-50 p-3 rounded-2xl border border-gray-200 min-h-[220px]">
                <img id="bukti-img" src="" alt="Bukti Transfer" class="max-h-[380px] w-auto max-w-full object-contain rounded-xl shadow-xs">
                <div id="bukti-error-fallback" class="hidden flex-col items-center justify-center py-8 text-center text-gray-400">
                    <i class="fa-solid fa-triangle-exclamation text-3xl text-amber-500 mb-2"></i>
                    <p class="text-xs font-bold text-gray-700">Gambar tidak dapat dipratinjau di browser</p>
                    <p class="text-[11px] text-gray-500 mt-0.5">Silakan gunakan tombol resolusi penuh di bawah.</p>
                </div>
            </div>

            <!-- Link Full Size / Download & Actions -->
            <div class="flex items-center justify-between pt-2">
                <a id="btn-open-newtab" href="#" target="_blank" class="inline-flex items-center space-x-1.5 text-xs font-bold text-brand-secondary hover:text-brand-primary transition underline">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    <span>Buka Ukuran Asli</span>
                </a>

                <div class="flex items-center space-x-2" id="bukti-actions-container">
                    <?php if (in_array($admin_role, ['superadmin', 'bendahara', 'panitia'])): ?>
                    <button type="button" id="btn-reject-pay" onclick="confirmRejectPay()" class="bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 font-bold px-3.5 py-2 rounded-xl text-xs transition">
                        Tolak
                    </button>
                    <button type="button" id="btn-confirm-pay" onclick="confirmApprovePay()" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-xl text-xs shadow-sm transition flex items-center space-x-1.5">
                        <i class="fa-solid fa-check"></i>
                        <span>Validasi Lunas</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Kelola Status Santri (Status Bayar, Metode Bayar, Status Seleksi) -->
    <div id="control-modal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 space-y-5 shadow-2xl relative border border-gray-200">
            <button onclick="hideControlModal()" type="button" class="absolute right-5 top-5 text-gray-400 hover:text-gray-700 w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
            <div class="border-b border-gray-100 pb-3">
                <h3 class="font-extrabold text-brand-primary text-lg flex items-center">
                    <i class="fa-solid fa-user-shield text-brand-secondary mr-2"></i> Update Status Santri
                </h3>
                <p id="ctrl-subtitle" class="text-xs text-gray-500 mt-1 font-semibold"></p>
            </div>
            
            <form action="pendaftar.php" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" id="ctrl-id" name="id" value="">

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        <i class="fa-solid fa-wallet text-brand-secondary mr-1"></i> Status Pembayaran
                    </label>
                    <select id="ctrl-bayar" name="status_pembayaran" onchange="toggleMetodeBayar()" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition" <?= !in_array($admin_role, ['superadmin', 'bendahara', 'panitia']) ? 'disabled title="Hanya bendahara/panitia yang dapat mengubah"' : '' ?>>
                        <option value="Belum Bayar">Belum Bayar</option>
                        <option value="Menunggu Verifikasi">Menunggu Verifikasi</option>
                        <option value="Lunas">Lunas</option>
                    </select>
                </div>

                <div id="metode-bayar-container" class="hidden">
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        <i class="fa-solid fa-money-bill-wave text-emerald-600 mr-1"></i> Metode Pembayaran
                    </label>
                    <select id="ctrl-metode" name="metode_pembayaran" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition">
                        <option value="Transfer">Transfer Bank</option>
                        <option value="Tunai/Cash">Tunai / Cash (Langsung ke Bendahara)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        <i class="fa-solid fa-award text-brand-secondary mr-1"></i> Hasil Seleksi Ujian
                    </label>
                    <select id="ctrl-seleksi" name="status_seleksi" class="w-full bg-gray-50 border border-gray-300 rounded-xl px-3.5 py-2.5 text-xs font-bold text-gray-800 focus:bg-white focus:ring-2 focus:ring-brand-primary/20 focus:border-brand-primary transition" <?= !in_array($admin_role, ['superadmin', 'panitia']) ? 'disabled title="Hanya panitia yang dapat mengubah"' : '' ?>>
                        <option value="Belum Seleksi">Belum Seleksi</option>
                        <option value="Lulus">Lulus Seleksi</option>
                        <option value="Tidak Lulus">Tidak Lulus</option>
                    </select>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-gray-100">
                    <button type="button" onclick="hideControlModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-100 transition">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-brand-primary text-white hover:bg-brand-deep shadow transition flex items-center space-x-1.5">
                        <i class="fa-solid fa-check"></i>
                        <span>Simpan Status</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Impor Status Massal (Excel/CSV) -->
    <div id="import-modal" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-7 shadow-2xl border border-gray-200 relative max-h-[90vh] overflow-y-auto">
            <button type="button" onclick="hideImportModal()" class="absolute top-5 right-5 text-gray-400 hover:text-gray-700 transition w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center">
                <i class="fa-solid fa-xmark"></i>
            </button>
            <div class="flex items-center space-x-3 pb-4 border-b border-gray-100 mb-4">
                <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl flex-shrink-0 shadow-xs border border-blue-200">
                    <i class="fa-solid fa-file-import"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-[#2F2522]">Impor &amp; Update Status Massal</h3>
                    <p class="text-xs text-gray-500 font-medium">Perbarui status kelulusan &amp; pembayaran ratusan santri via Excel</p>
                </div>
            </div>
            
            <div class="bg-blue-50/80 border border-blue-200 text-blue-900 p-4 rounded-xl text-xs space-y-1.5 mb-5">
                <div class="font-bold flex items-center space-x-1.5 text-blue-800">
                    <i class="fa-solid fa-circle-info text-sm"></i>
                    <span>Panduan Impor Cepat:</span>
                </div>
                <ol class="list-decimal list-inside space-y-1 text-gray-700 pl-1 leading-relaxed">
                    <li>Unduh data pendaftar terlebih dahulu menggunakan tombol <strong>Ekspor Excel</strong>.</li>
                    <li>Buka file di Microsoft Excel atau Google Sheets.</li>
                    <li>Ubah kolom <strong>Status Seleksi</strong> menjadi: <code class="bg-white px-1 py-0.5 rounded border border-blue-200 font-bold text-emerald-800">Lulus</code>, <code class="bg-white px-1 py-0.5 rounded border border-blue-200 font-bold text-red-700">Tidak Lulus</code>, atau <code class="bg-white px-1 py-0.5 rounded border border-blue-200 font-bold text-gray-700">Belum Seleksi</code>.</li>
                    <li>Ubah kolom <strong>Status Pembayaran</strong> menjadi: <code class="bg-white px-1 py-0.5 rounded border border-blue-200 font-bold text-emerald-800">Lunas</code> atau <code class="bg-white px-1 py-0.5 rounded border border-blue-200 font-bold text-gray-700">Belum Bayar</code>.</li>
                    <li>Simpan kembali file (.xls atau .csv), lalu unggah pada form di bawah.</li>
                </ol>
            </div>
            
            <form action="pendaftar.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="import_status">

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-file-excel text-emerald-600 mr-1.5"></i> Pilih File Excel (.xls, .xlsx, .csv)
                    </label>
                    <div class="border-2 border-dashed border-gray-300 hover:border-blue-500 rounded-2xl p-5 text-center transition bg-gray-50">
                        <input type="file" name="file_import" accept=".csv,.txt,.xls,.xlsx" required class="w-full text-xs text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-extrabold file:bg-blue-600 file:text-white hover:file:bg-blue-700 transition cursor-pointer">
                        <p class="text-[11px] text-gray-400 mt-2">Maksimal 5MB. Sistem otomatis mengenali kolom No. Registrasi santri.</p>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-gray-100">
                    <button type="button" onclick="hideImportModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-gray-600 hover:bg-gray-100 transition">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-blue-600 text-white hover:bg-blue-700 shadow transition flex items-center space-x-1.5">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Proses Impor Status</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts Manajemen & SweetAlert -->
    <script>
        let currentActivePayId = 0;

        function showImportModal() {
            document.getElementById('import-modal').classList.remove('hidden');
        }

        function hideImportModal() {
            document.getElementById('import-modal').classList.add('hidden');
        }

        function showBuktiModal(fileUrl, pendaftaranId, statusBayar, fileName) {
            const modal = document.getElementById('bukti-modal');
            const img = document.getElementById('bukti-img');
            const fallback = document.getElementById('bukti-error-fallback');
            const btnOpenNewtab = document.getElementById('btn-open-newtab');
            const containerActions = document.getElementById('bukti-actions-container');
            
            currentActivePayId = pendaftaranId;
            img.classList.remove('hidden');
            fallback.classList.add('hidden');
            img.src = fileUrl;
            img.onerror = function() {
                if (fileName && !this.src.includes('../uploads/')) {
                    this.src = '../uploads/' + fileName;
                } else {
                    this.classList.add('hidden');
                    fallback.classList.remove('hidden');
                }
            };

            if (btnOpenNewtab) btnOpenNewtab.href = fileUrl;
            
            if (statusBayar === 'Lunas') {
                if (containerActions) containerActions.classList.add('hidden');
            } else {
                if (containerActions) containerActions.classList.remove('hidden');
            }

            modal.classList.remove('hidden');
        }

        function hideBuktiModal() {
            document.getElementById('bukti-modal').classList.add('hidden');
        }

        function confirmApprovePay() {
            if (currentActivePayId <= 0) return;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Validasi Pembayaran Lunas?',
                    text: 'Status pembayaran santri ini akan diubah menjadi Lunas dan notifikasi email resmi akan otomatis dikirimkan.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#15803D',
                    cancelButtonColor: '#6B7280',
                    confirmButtonText: 'Ya, Validasi Lunas',
                    cancelButtonText: 'Batal'
                }).then((res) => {
                    if (res.isConfirmed) {
                        window.location.href = 'pendaftar.php?action=confirm_payment&id=' + currentActivePayId;
                    }
                });
            } else {
                if (confirm('Validasi pembayaran santri ini menjadi Lunas?')) {
                    window.location.href = 'pendaftar.php?action=confirm_payment&id=' + currentActivePayId;
                }
            }
        }

        function confirmRejectPay() {
            if (currentActivePayId <= 0) return;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Tolak Bukti Transfer?',
                    text: 'Status pembayaran akan dikembalikan menjadi Belum Bayar dan santri diminta mengunggah ulang bukti yang valid.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#DC2626',
                    cancelButtonColor: '#6B7280',
                    confirmButtonText: 'Ya, Tolak Struk',
                    cancelButtonText: 'Batal'
                }).then((res) => {
                    if (res.isConfirmed) {
                        window.location.href = 'pendaftar.php?action=reject_payment&id=' + currentActivePayId;
                    }
                });
            } else {
                if (confirm('Tolak bukti transfer santri ini?')) {
                    window.location.href = 'pendaftar.php?action=reject_payment&id=' + currentActivePayId;
                }
            }
        }

        function showControlModal(id, noReg, nama, statusBayar, statusSeleksi, metodeBayar) {
            document.getElementById('ctrl-id').value = id;
            document.getElementById('ctrl-subtitle').innerText = nama + ' (' + noReg + ')';
            document.getElementById('ctrl-bayar').value = statusBayar;
            document.getElementById('ctrl-seleksi').value = statusSeleksi;
            if (document.getElementById('ctrl-metode')) {
                document.getElementById('ctrl-metode').value = metodeBayar || 'Transfer';
            }
            toggleMetodeBayar();
            document.getElementById('control-modal').classList.remove('hidden');
        }

        function hideControlModal() {
            document.getElementById('control-modal').classList.add('hidden');
        }

        function toggleMetodeBayar() {
            const statusBayar = document.getElementById('ctrl-bayar').value;
            const metodeContainer = document.getElementById('metode-bayar-container');
            if (statusBayar === 'Lunas') {
                metodeContainer.classList.remove('hidden');
            } else {
                metodeContainer.classList.add('hidden');
            }
        }

        function confirmDeletePendaftar(id, nama, noReg) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Hapus Data Pendaftar?',
                    html: `Apakah Anda yakin ingin menghapus data calon santri <b>${nama}</b> (<code>${noReg}</code>)?<br><br><span class="text-xs text-red-600 font-bold">Tindakan ini permanen dan berkas bukti bayar terkait akan dibersihkan.</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#DC2626',
                    cancelButtonColor: '#6B7280',
                    confirmButtonText: 'Ya, Hapus Data',
                    cancelButtonText: 'Batal'
                }).then((res) => {
                    if (res.isConfirmed) {
                        window.location.href = 'pendaftar.php?action=delete&id=' + id;
                    }
                });
            } else {
                if (confirm(`Apakah Anda yakin ingin menghapus data santri ${nama} (${noReg})?`)) {
                    window.location.href = 'pendaftar.php?action=delete&id=' + id;
                }
            }
        }

        // Batch Action Checkbox Handler
        function toggleSelectAll(masterCb) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = masterCb.checked);
            updateBatchToolbar();
        }

        function updateBatchToolbar() {
            const checkboxes = document.querySelectorAll('.row-checkbox:checked');
            const toolbar = document.getElementById('batch-toolbar');
            const countLabel = document.getElementById('selected-count');
            
            if (checkboxes.length > 0) {
                toolbar.classList.remove('hidden');
                toolbar.classList.add('flex');
                countLabel.innerText = checkboxes.length;
            } else {
                toolbar.classList.add('hidden');
                toolbar.classList.remove('flex');
                const checkAll = document.getElementById('check-all');
                if (checkAll) checkAll.checked = false;
            }
        }

        function clearBatchSelection() {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            const checkAll = document.getElementById('check-all');
            if (checkAll) checkAll.checked = false;
            updateBatchToolbar();
        }

        function submitBatch(type) {
            const checkboxes = document.querySelectorAll('.row-checkbox:checked');
            if (checkboxes.length === 0) return;

            let titleText = 'Lanjutkan Aksi Massal?';
            let descText = `Anda akan memperbarui ${checkboxes.length} santri terpilih.`;
            
            if (type === 'set_hadir') {
                titleText = 'Tandai Hadir Ujian Massal?';
                descText = `${checkboxes.length} santri akan ditandai Hadir pada presensi ujian seleksi hari ini.`;
            } else if (type === 'set_lunas') {
                titleText = 'Validasi Lunas Pembayaran Massal?';
                descText = `${checkboxes.length} santri akan diverifikasi Lunas biaya tes seleksi.`;
            } else if (type === 'set_lulus') {
                titleText = 'Tandai Lulus Seleksi Massal?';
                descText = `${checkboxes.length} santri akan dinyatakan Lulus Seleksi Masuk.`;
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: titleText,
                    text: descText,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#5C3E35',
                    cancelButtonColor: '#6B7280',
                    confirmButtonText: 'Ya, Lanjutkan',
                    cancelButtonText: 'Batal'
                }).then((res) => {
                    if (res.isConfirmed) {
                        document.getElementById('batch-type').value = type;
                        document.getElementById('form-batch').submit();
                    }
                });
            } else {
                if (confirm(descText)) {
                    document.getElementById('batch-type').value = type;
                    document.getElementById('form-batch').submit();
                }
            }
        }

        // Unduh PDF Rahasia
        function unduhDokumenRahasia(jenis, noReg, btn) {
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i>';
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btn.disabled = true;

            let iframe = document.getElementById('secret-pdf-frame');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'secret-pdf-frame';
                iframe.style.position = 'fixed';
                iframe.style.right = '0';
                iframe.style.bottom = '0';
                iframe.style.width = '800px';
                iframe.style.height = '1100px';
                iframe.style.border = 'none';
                iframe.style.opacity = '0';
                iframe.style.pointerEvents = 'none';
                iframe.style.zIndex = '-9999';
                document.body.appendChild(iframe);
            }

            const targetUrl = '../' + (jenis === 'kartu' ? 'cetak_kartu.php' : 'cetak_formulir.php') + '?no_reg=' + encodeURIComponent(noReg) + '&download=1';
            
            const messageHandler = function(e) {
                if (e.data && e.data.status === 'pdf_done' && e.data.no_reg === noReg) {
                    window.removeEventListener('message', messageHandler);
                    btn.innerHTML = '<i class="fa-solid fa-check text-xs text-emerald-600"></i>';
                    btn.classList.remove('opacity-75', 'cursor-not-allowed');
                    btn.disabled = false;
                    setTimeout(() => {
                        btn.innerHTML = originalHTML;
                    }, 2500);
                }
            };
            window.addEventListener('message', messageHandler);

            iframe.src = targetUrl;
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.classList.remove('opacity-75', 'cursor-not-allowed');
                btn.disabled = false;
            }, 5000);
        }
    </script>
</body>
</html>
