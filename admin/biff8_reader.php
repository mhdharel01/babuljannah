<?php
// admin/biff8_reader.php
// Lightweight Vanilla PHP BIFF8 (Excel 97-2003 Binary .xls) Table Extractor

class Biff8Reader {
    public static function parse($content) {
        $rows_data = [];
        
        // 1. Cek Signature OLE Compound Document
        if (substr($content, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            return $rows_data;
        }
        
        // 2. Ekstrak stream Workbook dari dalam struktur OLE atau langsung pindai block BIFF
        // Dalam file BIFF8 standar, record SST (0x00FC) menyimpan semua shared strings.
        // Record LabelSST (0x00FD) menghubungkan [Row, Col] dengan indeks SST.
        // Record Label (0x0204) langsung menyimpan teks di [Row, Col].
        
        $len = strlen($content);
        $sst = [];
        $cells = [];
        
        // Pemindaian Stream Record BIFF langsung dari buffer biner
        // Record format: [ID (2 bytes)] [Size (2 bytes)] [Data (Size bytes)]
        $pos = 0;
        while ($pos < $len - 4) {
            $code = ord($content[$pos]) | (ord($content[$pos + 1]) << 8);
            $size = ord($content[$pos + 2]) | (ord($content[$pos + 3]) << 8);
            
            if ($pos + 4 + $size > $len) {
                $pos++;
                continue;
            }
            
            $data = substr($content, $pos + 4, $size);
            
            // Record SST (Shared String Table) -> ID 0x00FC (252)
            if ($code === 0x00FC && $size >= 8) {
                self::parseSST($data, $sst);
            }
            // Record LabelSST (Cell pointing to SST) -> ID 0x00FD (253)
            elseif ($code === 0x00FD && $size >= 10) {
                $row = ord($data[0]) | (ord($data[1]) << 8);
                $col = ord($data[2]) | (ord($data[3]) << 8);
                $sst_idx = ord($data[6]) | (ord($data[7]) << 8) | (ord($data[8]) << 16) | (ord($data[9]) << 24);
                
                if (isset($sst[$sst_idx])) {
                    $cells[$row][$col] = trim($sst[$sst_idx]);
                }
            }
            // Record Label (Unshared String Cell) -> ID 0x0204 (516)
            elseif ($code === 0x0204 && $size >= 8) {
                $row = ord($data[0]) | (ord($data[1]) << 8);
                $col = ord($data[2]) | (ord($data[3]) << 8);
                $str_len = ord($data[6]) | (ord($data[7]) << 8);
                $flag = ord($data[8]);
                if ($flag === 0) { // ASCII
                    $val = substr($data, 9, $str_len);
                } else { // UTF-16LE
                    $val = preg_replace('/([\x20-\x7E])\x00/', '$1', substr($data, 9, $str_len * 2));
                }
                $cells[$row][$col] = trim($val);
            }
            
            // Langkah maju berdasarkan ukuran record (atau langkah kecil 1 byte jika stream terenkapsulasi OLE)
            if ($code === 0x00FC || $code === 0x00FD || $code === 0x0204 || $code === 0x0809 || $code === 0x000A) {
                $pos += 4 + $size;
            } else {
                $pos++;
            }
        }
        
        // Susun menjadi array 2D terurut
        if (!empty($cells)) {
            ksort($cells);
            foreach ($cells as $r_idx => $cols) {
                ksort($cols);
                $max_col = empty($cols) ? 0 : max(array_keys($cols));
                $row_vals = [];
                for ($c = 0; $c <= $max_col; $c++) {
                    $row_vals[] = isset($cols[$c]) ? $cols[$c] : '';
                }
                if (!empty(array_filter($row_vals))) {
                    $rows_data[] = $row_vals;
                }
            }
        }
        
        return $rows_data;
    }
    
    private static function parseSST($data, &$sst) {
        $len = strlen($data);
        if ($len < 8) return;
        
        $pos = 8; // Lewati Total Strings (4 bytes) & Unique Strings (4 bytes)
        while ($pos < $len) {
            if ($pos + 3 > $len) break;
            $str_len = ord($data[$pos]) | (ord($data[$pos + 1]) << 8);
            $flag = ord($data[$pos + 2]);
            $pos += 3;
            
            // Format string BIFF8: flag bit 0 = 0 (8-bit ASCII), bit 0 = 1 (16-bit UTF-16LE)
            $is_unicode = ($flag & 0x01) !== 0;
            $has_rich = ($flag & 0x08) !== 0;
            $has_fareast = ($flag & 0x04) !== 0;
            
            $rt_len = 0;
            $sz_len = 0;
            if ($has_rich && $pos + 2 <= $len) {
                $rt_len = ord($data[$pos]) | (ord($data[$pos + 1]) << 8);
                $pos += 2;
            }
            if ($has_fareast && $pos + 4 <= $len) {
                $sz_len = ord($data[$pos]) | (ord($data[$pos + 1]) << 8) | (ord($data[$pos + 2]) << 16) | (ord($data[$pos + 3]) << 24);
                $pos += 4;
            }
            
            $byte_len = $is_unicode ? ($str_len * 2) : $str_len;
            if ($pos + $byte_len > $len) break;
            
            $raw_str = substr($data, $pos, $byte_len);
            if ($is_unicode) {
                $str = preg_replace('/([\x20-\x7E])\x00/', '$1', $raw_str);
            } else {
                $str = $raw_str;
            }
            $sst[] = $str;
            
            $pos += $byte_len + ($rt_len * 4) + $sz_len;
        }
    }
}
?>
