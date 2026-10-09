<?php

/**
 * Hutang supplier and supplier debt payment endpoints.
 */

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/DBConnection.php');
require_once(__DIR__ . '/NumericInput.php');

class HutangSupplier extends DBConnection
{
    private function bindParams($stmt, $types, array $params)
    {
        if ($types === '' || empty($params)) {
            return;
        }

        $refs = [$types];
        foreach ($params as $key => $value) {
            $refs[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }

    private function parseCurrency($value)
    {
        return NumericInput::money($value);
    }

    private function currentUserId()
    {
        return (int)($_SESSION['userdata']['id'] ?? $_SESSION['user_id'] ?? 0);
    }

    private function statusForDebt($total, $paid, $dueDate)
    {
        $remaining = max((float)$total - (float)$paid, 0);
        if ($remaining <= 0) {
            return 'LUNAS';
        }

        if (!empty($dueDate) && strtotime($dueDate) < strtotime(date('Y-m-d'))) {
            return 'OVERDUE';
        }

        return 'BELUM';
    }

    private function statusBadge($status)
    {
        switch (strtoupper((string)$status)) {
            case 'LUNAS':
                return '<span class="badge bg-success">Lunas</span>';
            case 'OVERDUE':
                return '<span class="badge bg-danger">Terlambat</span>';
            default:
                return '<span class="badge bg-warning text-dark">Belum Lunas</span>';
        }
    }

    private function nextNumber($table, $field, $prefix, $idField = 'id')
    {
        $like = $prefix . '%';
        $sql = "SELECT {$field} AS no_doc FROM {$table} WHERE {$field} LIKE ? ORDER BY {$idField} DESC LIMIT 1";
        $stmt = $this->prepare($sql);
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $next = 1;
        if (!empty($row['no_doc']) && preg_match('/(\d+)$/', $row['no_doc'], $matches)) {
            $next = ((int)$matches[1]) + 1;
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    private function generate_no_hutang()
    {
        return $this->nextNumber('hutang_supplier', 'no_hutang', 'HUT-' . date('Y-m-'));
    }

    private function generate_no_pembayaran()
    {
        return $this->nextNumber('pembayaran_hutang', 'no_pembayaran', 'BHT-' . date('Y-m-'));
    }

    private function update_sisa_hutang($hutang_id)
    {
        $hutang_id = (int)$hutang_id;
        if ($hutang_id <= 0) {
            throw new Exception('ID hutang tidak valid');
        }

        $stmt = $this->prepare("SELECT total_hutang, tanggal_jatuh_tempo FROM hutang_supplier WHERE id = ?");
        $stmt->bind_param('i', $hutang_id);
        $stmt->execute();
        $hutang = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$hutang) {
            throw new Exception('Data hutang tidak ditemukan');
        }

        $stmt = $this->prepare("SELECT COALESCE(SUM(jumlah_bayar), 0) AS total_dibayar FROM pembayaran_hutang WHERE hutang_id = ?");
        $stmt->bind_param('i', $hutang_id);
        $stmt->execute();
        $paidRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $total = (float)$hutang['total_hutang'];
        $totalPaid = (float)($paidRow['total_dibayar'] ?? 0);
        $remaining = max($total - $totalPaid, 0);
        $status = $this->statusForDebt($total, $totalPaid, $hutang['tanggal_jatuh_tempo']);

        $stmt = $this->prepare("
            UPDATE hutang_supplier
               SET total_dibayar = ?,
                   sisa_hutang = ?,
                   status = ?,
                   updated_at = NOW()
             WHERE id = ?
        ");
        $stmt->bind_param('ddsi', $totalPaid, $remaining, $status, $hutang_id);
        $stmt->execute();
        $stmt->close();
    }

    public function get_data_hutang()
    {
        $draw = (int)($_POST['draw'] ?? 1);
        $start = max(0, (int)($_POST['start'] ?? 0));
        $length = (int)($_POST['length'] ?? 10);
        if ($length <= 0) {
            $length = 10;
        }

        $search = trim($_POST['search']['value'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $supplierFilter = (int)($_POST['supplier_filter'] ?? 0);
        $orderIndex = (int)($_POST['order'][0]['column'] ?? 0);
        $orderDir = strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $columns = [
            0 => 'h.id',
            1 => 'h.tanggal_hutang',
            2 => 'h.no_hutang',
            3 => 's.nama',
            4 => 'h.total_hutang',
            5 => 'h.sisa_hutang',
            6 => 'h.tanggal_jatuh_tempo',
            7 => 'h.status',
        ];
        $orderColumn = $columns[$orderIndex] ?? 'h.id';

        try {
            $whereParts = [];
            $params = [];
            $types = '';

            if ($search !== '') {
                $whereParts[] = "(h.no_hutang LIKE ? OR h.ref_pembelian LIKE ? OR h.keterangan LIKE ? OR s.nama LIKE ?)";
                $like = '%' . $search . '%';
                array_push($params, $like, $like, $like, $like);
                $types .= 'ssss';
            }

            if ($status !== '') {
                $whereParts[] = "h.status = ?";
                $params[] = $status;
                $types .= 's';
            }

            if ($supplierFilter > 0) {
                $whereParts[] = "h.supplier_id = ?";
                $params[] = $supplierFilter;
                $types .= 'i';
            }

            $where = empty($whereParts) ? '' : ' WHERE ' . implode(' AND ', $whereParts);

            $totalRecords = (int)($this->fetchOne("SELECT COUNT(*) AS cnt FROM hutang_supplier")['cnt'] ?? 0);

            $countSql = "SELECT COUNT(*) AS cnt
                           FROM hutang_supplier h
                           LEFT JOIN supplier s ON s.id = h.supplier_id
                         {$where}";
            $stmt = $this->prepare($countSql);
            $this->bindParams($stmt, $types, $params);
            $stmt->execute();
            $recordsFiltered = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $stmt->close();

            $sql = "SELECT h.id,
                           h.no_hutang,
                           h.tanggal_hutang,
                           h.tanggal_jatuh_tempo,
                           h.total_hutang,
                           h.total_dibayar,
                           h.sisa_hutang,
                           h.status,
                           h.keterangan,
                           h.ref_pembelian,
                           s.nama AS nama_supplier,
                           s.kode AS kode_supplier,
                           DATEDIFF(h.tanggal_jatuh_tempo, CURDATE()) AS hari_jatuh_tempo
                      FROM hutang_supplier h
                      LEFT JOIN supplier s ON s.id = h.supplier_id
                    {$where}
                     ORDER BY {$orderColumn} {$orderDir}
                     LIMIT ?, ?";

            $queryParams = $params;
            $queryParams[] = $start;
            $queryParams[] = $length;
            $queryTypes = $types . 'ii';

            $stmt = $this->prepare($sql);
            $this->bindParams($stmt, $queryTypes, $queryParams);
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            $no = $start + 1;
            while ($row = $result->fetch_assoc()) {
                $hari = $row['hari_jatuh_tempo'];
                if ($hari === null) {
                    $jatuhTempo = '<span class="text-muted">-</span>';
                } elseif ((int)$hari < 0) {
                    $jatuhTempo = '<span class="text-danger">Terlambat ' . abs((int)$hari) . ' hari</span>';
                } elseif ((int)$hari <= 7) {
                    $jatuhTempo = '<span class="text-warning">Jatuh tempo ' . (int)$hari . ' hari lagi</span>';
                } else {
                    $jatuhTempo = '<span class="text-muted">' . (int)$hari . ' hari lagi</span>';
                }

                $data[] = [
                    'no' => $no++,
                    'id' => (int)$row['id'],
                    'tanggal_hutang' => !empty($row['tanggal_hutang']) ? date('d/m/Y', strtotime($row['tanggal_hutang'])) : '-',
                    'tanggal_jatuh_tempo' => !empty($row['tanggal_jatuh_tempo']) ? date('d/m/Y', strtotime($row['tanggal_jatuh_tempo'])) : '-',
                    'jatuh_tempo' => $jatuhTempo,
                    'no_hutang' => $row['no_hutang'] ?: '-',
                    'nama_supplier' => $row['nama_supplier'] ?: '-',
                    'kode_supplier' => $row['kode_supplier'] ?: '-',
                    'total_hutang' => number_format((float)$row['total_hutang'], 0, ',', '.'),
                    'total_dibayar' => number_format((float)$row['total_dibayar'], 0, ',', '.'),
                    'sisa_hutang' => number_format((float)$row['sisa_hutang'], 0, ',', '.'),
                    'status' => $row['status'],
                    'status_badge' => $this->statusBadge($row['status']),
                    'keterangan' => $row['keterangan'] ?: '-',
                    'ref_pembelian' => $row['ref_pembelian'] ?: '-',
                ];
            }
            $stmt->close();

            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            error_log('[GET_DATA_HUTANG ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage(),
            ]);
        }
        exit;
    }

    public function save_hutang()
    {
        $transactionStarted = false;
        $writeLock = false;
        try {
            $id = (int)($_POST['id'] ?? 0);
            $supplierId = (int)($_POST['supplier_id'] ?? 0);
            $tanggalHutang = $_POST['tanggal_hutang'] ?? date('Y-m-d');
            $tanggalJatuhTempo = $_POST['tanggal_jatuh_tempo'] ?? $tanggalHutang;
            $totalHutang = $this->parseCurrency($_POST['total_hutang'] ?? 0);
            $keterangan = trim($_POST['keterangan'] ?? '');
            $refPembelian = trim($_POST['ref_pembelian'] ?? '');
            $userId = $this->currentUserId();

            if ($supplierId <= 0) {
                throw new Exception('Supplier wajib dipilih');
            }
            if ($totalHutang <= 0) {
                throw new Exception('Total hutang harus lebih dari 0');
            }

            $lockResult = $this->query("SELECT GET_LOCK('sarinah_hutang_write', 10) AS acquired");
            $writeLock = $lockResult && (int)($lockResult->fetch_assoc()['acquired'] ?? 0) === 1;
            if (!$writeLock) {
                throw new Exception('Data hutang lain sedang diproses. Silakan coba kembali.');
            }
            $this->conn->begin_transaction();
            $transactionStarted = true;

            if ($id <= 0) {
                $noHutang = $this->generate_no_hutang();
                $status = $this->statusForDebt($totalHutang, 0, $tanggalJatuhTempo);
                $stmt = $this->prepare("
                    INSERT INTO hutang_supplier
                        (no_hutang, tanggal_hutang, tanggal_jatuh_tempo, supplier_id, total_hutang,
                         total_dibayar, sisa_hutang, status, keterangan, ref_pembelian, user_id, tgl_input)
                    VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param(
                    'sssiddsssi',
                    $noHutang,
                    $tanggalHutang,
                    $tanggalJatuhTempo,
                    $supplierId,
                    $totalHutang,
                    $totalHutang,
                    $status,
                    $keterangan,
                    $refPembelian,
                    $userId
                );
                $stmt->execute();
                $id = $stmt->insert_id;
                $stmt->close();
            } else {
                $paidStmt = $this->prepare("SELECT total_dibayar FROM hutang_supplier WHERE id = ? FOR UPDATE");
                $paidStmt->bind_param('i', $id);
                $paidStmt->execute();
                $existing = $paidStmt->get_result()->fetch_assoc();
                $paidStmt->close();
                if (!$existing) {
                    throw new Exception('Data hutang tidak ditemukan');
                }
                if ((float)$existing['total_dibayar'] > $totalHutang + 0.01) {
                    throw new Exception('Total hutang tidak boleh lebih kecil dari pembayaran yang sudah tercatat');
                }
                $stmt = $this->prepare("
                    UPDATE hutang_supplier
                       SET tanggal_hutang = ?,
                           tanggal_jatuh_tempo = ?,
                           supplier_id = ?,
                           total_hutang = ?,
                           keterangan = ?,
                           ref_pembelian = ?,
                           updated_at = NOW()
                     WHERE id = ?
                ");
                $stmt->bind_param('ssidssi', $tanggalHutang, $tanggalJatuhTempo, $supplierId, $totalHutang, $keterangan, $refPembelian, $id);
                $stmt->execute();
                $stmt->close();
                $this->update_sisa_hutang($id);
            }

            $this->conn->commit();
            $transactionStarted = false;

            echo json_encode(['status' => 'success', 'msg' => 'Hutang berhasil disimpan', 'id' => $id]);
        } catch (Exception $e) {
            if ($transactionStarted) {
                $this->conn->rollback();
            }
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        } finally {
            if ($writeLock) {
                $this->conn->query("SELECT RELEASE_LOCK('sarinah_hutang_write')");
            }
        }
        exit;
    }

    public function save_pembayaran()
    {
        $transactionStarted = false;
        $numberLock = false;
        try {
            $hutangId = (int)($_POST['hutang_id'] ?? 0);
            $tanggalBayar = $_POST['tanggal_bayar'] ?? date('Y-m-d');
            $jumlahBayar = $this->parseCurrency($_POST['jumlah_bayar'] ?? 0);
            $metodeBayar = trim($_POST['metode_bayar'] ?? 'tunai');
            $noBukti = trim($_POST['no_bukti'] ?? $_POST['no_referensi'] ?? '');
            $keterangan = trim($_POST['keterangan'] ?? '');
            $userId = $this->currentUserId();

            if ($hutangId <= 0) {
                throw new Exception('Hutang wajib dipilih');
            }
            if ($jumlahBayar <= 0) {
                throw new Exception('Jumlah bayar harus lebih dari 0');
            }

            $lockResult = $this->query("SELECT GET_LOCK('sarinah_hutang_write', 10) AS acquired");
            $numberLock = $lockResult && (int)($lockResult->fetch_assoc()['acquired'] ?? 0) === 1;
            if (!$numberLock) {
                throw new Exception('Pembayaran lain sedang diproses. Silakan coba kembali.');
            }
            $this->conn->begin_transaction();
            $transactionStarted = true;

            $stmt = $this->prepare("SELECT sisa_hutang FROM hutang_supplier WHERE id = ? FOR UPDATE");
            $stmt->bind_param('i', $hutangId);
            $stmt->execute();
            $hutang = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$hutang) {
                throw new Exception('Hutang tidak ditemukan');
            }
            if ($jumlahBayar > (float)$hutang['sisa_hutang']) {
                throw new Exception('Jumlah pembayaran tidak boleh lebih dari sisa hutang');
            }

            $noPembayaran = $this->generate_no_pembayaran();
            $stmt = $this->prepare("
                INSERT INTO pembayaran_hutang
                    (no_pembayaran, hutang_id, tanggal_bayar, jumlah_bayar, metode_bayar,
                     no_bukti, keterangan, user_id, tgl_input)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param('sisdsssi', $noPembayaran, $hutangId, $tanggalBayar, $jumlahBayar, $metodeBayar, $noBukti, $keterangan, $userId);
            $stmt->execute();
            $paymentId = $stmt->insert_id;
            $stmt->close();

            $this->update_sisa_hutang($hutangId);

            $this->conn->commit();
            $transactionStarted = false;

            echo json_encode([
                'status' => 'success',
                'msg' => 'Pembayaran berhasil disimpan',
                'id' => $paymentId,
                'no_pembayaran' => $noPembayaran,
            ]);
        } catch (Exception $e) {
            if ($transactionStarted) {
                $this->conn->rollback();
            }
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        } finally {
            if ($numberLock) {
                $this->conn->query("SELECT RELEASE_LOCK('sarinah_hutang_write')");
            }
        }
        exit;
    }

    public function get_hutang_detail()
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $stmt = $this->prepare("
            SELECT h.*, s.nama AS nama_supplier, s.kode AS kode_supplier
              FROM hutang_supplier h
              LEFT JOIN supplier s ON s.id = h.supplier_id
             WHERE h.id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        echo json_encode($data ?: ['status' => 'failed', 'msg' => 'Data hutang tidak ditemukan']);
        exit;
    }

    public function get_pembayaran_history()
    {
        $hutangId = (int)($_POST['hutang_id'] ?? $_GET['hutang_id'] ?? 0);
        $stmt = $this->prepare("SELECT * FROM pembayaran_hutang WHERE hutang_id = ? ORDER BY tanggal_bayar DESC, id DESC");
        $stmt->bind_param('i', $hutangId);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $row['tanggal_bayar'] = date('d/m/Y', strtotime($row['tanggal_bayar']));
            $row['jumlah_bayar'] = number_format((float)$row['jumlah_bayar'], 0, ',', '.');
            $data[] = $row;
        }
        $stmt->close();

        echo json_encode($data);
        exit;
    }

    public function get_pembayaran_detail()
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => 'ID pembayaran tidak valid']);
            exit;
        }

        try {
            $stmt = $this->prepare("
                SELECT p.id,
                       p.no_pembayaran,
                       p.tanggal_bayar,
                       p.jumlah_bayar,
                       p.metode_bayar,
                       p.no_bukti,
                       p.keterangan,
                       h.no_hutang,
                       h.ref_pembelian,
                       h.total_hutang,
                       h.sisa_hutang,
                       s.nama AS nama_supplier,
                       s.kode AS kode_supplier,
                       s.alamat AS alamat_supplier
                  FROM pembayaran_hutang p
                  LEFT JOIN hutang_supplier h ON h.id = p.hutang_id
                  LEFT JOIN supplier s ON s.id = h.supplier_id
                 WHERE p.id = ?
                 LIMIT 1
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                throw new Exception('Data pembayaran tidak ditemukan');
            }

            $row['tanggal'] = !empty($row['tanggal_bayar']) ? date('d/m/Y', strtotime($row['tanggal_bayar'])) : '-';
            $row['jumlah_bayar_format'] = 'Rp ' . number_format((float)$row['jumlah_bayar'], 0, ',', '.');
            $row['total_hutang_format'] = 'Rp ' . number_format((float)$row['total_hutang'], 0, ',', '.');
            $row['sisa_hutang_format'] = 'Rp ' . number_format((float)$row['sisa_hutang'], 0, ',', '.');

            echo json_encode(['status' => 'success', 'data' => $row]);
        } catch (Exception $e) {
            http_response_code(404);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function delete_hutang()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID hutang tidak valid');
            }

            $stmt = $this->prepare("SELECT COUNT(*) AS cnt FROM pembayaran_hutang WHERE hutang_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $count = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $stmt->close();

            if ($count > 0) {
                throw new Exception('Tidak dapat menghapus hutang yang sudah memiliki pembayaran');
            }

            $stmt = $this->prepare("DELETE FROM hutang_supplier WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();

            if ($deleted <= 0) {
                throw new Exception('Data hutang tidak ditemukan');
            }

            echo json_encode(['status' => 'success', 'msg' => 'Hutang berhasil dihapus']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function search_suppliers()
    {
        $term = trim($_GET['term'] ?? $_GET['q'] ?? '');
        $like = '%' . $term . '%';
        $stmt = $this->prepare("
            SELECT id, nama, kode, alamat
              FROM supplier
             WHERE status = 1 AND (nama LIKE ? OR kode LIKE ?)
             ORDER BY nama ASC
             LIMIT 30
        ");
        $stmt->bind_param('ss', $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'id' => (int)$row['id'],
                'text' => $row['nama'] . (!empty($row['kode']) ? ' (' . $row['kode'] . ')' : ''),
                'nama' => $row['nama'],
                'kode' => $row['kode'],
                'alamat' => $row['alamat'],
            ];
        }
        $stmt->close();

        echo json_encode($data);
        exit;
    }

    public function search_hutang()
    {
        $term = trim($_GET['term'] ?? $_GET['q'] ?? '');
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            $whereParts = [];
            $params = [];
            $types = '';

            if ($id > 0) {
                $whereParts[] = 'h.id = ?';
                $whereParts[] = "h.status != 'LUNAS'";
                $whereParts[] = 'h.sisa_hutang > 0';
                $params[] = $id;
                $types .= 'i';
            } else {
                $whereParts[] = "h.status != 'LUNAS'";
                $whereParts[] = 'h.sisa_hutang > 0';

                if ($term !== '') {
                    $whereParts[] = "(h.no_hutang LIKE ? OR h.ref_pembelian LIKE ? OR s.nama LIKE ? OR s.kode LIKE ?)";
                    $like = '%' . $term . '%';
                    array_push($params, $like, $like, $like, $like);
                    $types .= 'ssss';
                }
            }

            $where = ' WHERE ' . implode(' AND ', $whereParts);
            $sql = "
                SELECT h.id,
                       h.no_hutang,
                       h.ref_pembelian,
                       h.tanggal_hutang,
                       h.tanggal_jatuh_tempo,
                       h.total_hutang,
                       h.total_dibayar,
                       h.sisa_hutang,
                       h.status,
                       s.nama AS nama_supplier,
                       s.kode AS kode_supplier
                  FROM hutang_supplier h
                  LEFT JOIN supplier s ON s.id = h.supplier_id
                {$where}
                 ORDER BY h.tanggal_hutang DESC, h.id DESC
                 LIMIT 30
            ";

            $stmt = $this->prepare($sql);
            $this->bindParams($stmt, $types, $params);
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $sisa = (float)$row['sisa_hutang'];
                $nama = $row['nama_supplier'] ?: '-';
                $kode = !empty($row['kode_supplier']) ? ' (' . $row['kode_supplier'] . ')' : '';
                $data[] = [
                    'id' => (int)$row['id'],
                    'text' => ($row['no_hutang'] ?: '-') . ' - ' . $nama . $kode . ' - Sisa Rp ' . number_format($sisa, 0, ',', '.'),
                    'no_hutang' => $row['no_hutang'] ?: '-',
                    'ref_pembelian' => $row['ref_pembelian'] ?: '-',
                    'nama_supplier' => $nama,
                    'kode_supplier' => $row['kode_supplier'] ?: '',
                    'tanggal_hutang' => !empty($row['tanggal_hutang']) ? date('d/m/Y', strtotime($row['tanggal_hutang'])) : '-',
                    'tanggal_jatuh_tempo' => !empty($row['tanggal_jatuh_tempo']) ? date('d/m/Y', strtotime($row['tanggal_jatuh_tempo'])) : '-',
                    'total_hutang' => (float)$row['total_hutang'],
                    'total_hutang_format' => 'Rp ' . number_format((float)$row['total_hutang'], 0, ',', '.'),
                    'total_dibayar' => (float)$row['total_dibayar'],
                    'total_dibayar_format' => 'Rp ' . number_format((float)$row['total_dibayar'], 0, ',', '.'),
                    'sisa_hutang' => $sisa,
                    'sisa_hutang_format' => 'Rp ' . number_format($sisa, 0, ',', '.'),
                    'status' => $row['status'] ?: 'BELUM',
                ];
            }
            $stmt->close();

            echo json_encode($data);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function get_pembayaran_list()
    {
        $draw = (int)($_POST['draw'] ?? 1);
        $start = max(0, (int)($_POST['start'] ?? 0));
        $length = (int)($_POST['length'] ?? 25);
        if ($length <= 0) {
            $length = 25;
        }

        $search = trim($_POST['search']['value'] ?? '');
        $tanggalFilter = trim($_POST['tanggal_filter'] ?? '');
        $supplierFilter = (int)($_POST['supplier_filter'] ?? 0);
        $hutangFilter = (int)($_POST['hutang_id'] ?? $_GET['hutang_id'] ?? 0);
        $orderIndex = (int)($_POST['order'][0]['column'] ?? 1);
        $orderDir = strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $columns = [
            0 => 'p.id',
            1 => 'p.no_pembayaran',
            2 => 'h.no_hutang',
            3 => 's.nama',
            4 => 'p.tanggal_bayar',
            5 => 'p.jumlah_bayar',
            6 => 'p.metode_bayar',
            7 => 'p.keterangan',
        ];
        $orderColumn = $columns[$orderIndex] ?? 'p.id';

        try {
            $whereParts = [];
            $params = [];
            $types = '';

            if ($search !== '') {
                $whereParts[] = "(p.no_pembayaran LIKE ? OR p.no_bukti LIKE ? OR h.no_hutang LIKE ? OR s.nama LIKE ?)";
                $like = '%' . $search . '%';
                array_push($params, $like, $like, $like, $like);
                $types .= 'ssss';
            }

            if ($tanggalFilter !== '') {
                $whereParts[] = "DATE(p.tanggal_bayar) = ?";
                $params[] = $tanggalFilter;
                $types .= 's';
            }

            if ($supplierFilter > 0) {
                $whereParts[] = "h.supplier_id = ?";
                $params[] = $supplierFilter;
                $types .= 'i';
            }

            if ($hutangFilter > 0) {
                $whereParts[] = "p.hutang_id = ?";
                $params[] = $hutangFilter;
                $types .= 'i';
            }

            $where = empty($whereParts) ? '' : ' WHERE ' . implode(' AND ', $whereParts);

            $totalRecords = (int)($this->fetchOne("SELECT COUNT(*) AS cnt FROM pembayaran_hutang")['cnt'] ?? 0);

            $countSql = "SELECT COUNT(*) AS cnt
                           FROM pembayaran_hutang p
                           LEFT JOIN hutang_supplier h ON h.id = p.hutang_id
                           LEFT JOIN supplier s ON s.id = h.supplier_id
                         {$where}";
            $stmt = $this->prepare($countSql);
            $this->bindParams($stmt, $types, $params);
            $stmt->execute();
            $recordsFiltered = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $stmt->close();

            $sql = "SELECT p.id,
                           p.no_pembayaran,
                           p.tanggal_bayar,
                           p.jumlah_bayar,
                           p.metode_bayar,
                           p.no_bukti,
                           p.keterangan,
                           h.no_hutang,
                           s.nama AS nama_supplier
                      FROM pembayaran_hutang p
                      LEFT JOIN hutang_supplier h ON h.id = p.hutang_id
                      LEFT JOIN supplier s ON s.id = h.supplier_id
                    {$where}
                     ORDER BY {$orderColumn} {$orderDir}
                     LIMIT ?, ?";

            $queryParams = $params;
            $queryParams[] = $start;
            $queryParams[] = $length;
            $queryTypes = $types . 'ii';

            $stmt = $this->prepare($sql);
            $this->bindParams($stmt, $queryTypes, $queryParams);
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            $no = $start + 1;
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'no' => $no++,
                    'id' => (int)$row['id'],
                    'no_pembayaran' => $row['no_pembayaran'] ?: '-',
                    'no_hutang' => $row['no_hutang'] ?: '-',
                    'nama_supplier' => $row['nama_supplier'] ?: '-',
                    'tanggal' => !empty($row['tanggal_bayar']) ? date('d/m/Y', strtotime($row['tanggal_bayar'])) : '-',
                    'jumlah_bayar' => 'Rp ' . number_format((float)$row['jumlah_bayar'], 0, ',', '.'),
                    'metode' => ucfirst($row['metode_bayar'] ?: 'tunai'),
                    'keterangan' => $row['keterangan'] ?: '-',
                    'no_bukti' => $row['no_bukti'] ?: '-',
                ];
            }
            $stmt->close();

            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            error_log('[GET_PEMBAYARAN_LIST ERROR] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage(),
            ]);
        }
        exit;
    }

    public function get_pembayaran_stats()
    {
        try {
            $today = date('Y-m-d');
            $hutangFilter = (int)($_POST['hutang_id'] ?? $_GET['hutang_id'] ?? 0);

            if ($hutangFilter > 0) {
                $stmt = $this->prepare("SELECT COALESCE(SUM(jumlah_bayar), 0) AS total, COUNT(*) AS cnt FROM pembayaran_hutang WHERE DATE(tanggal_bayar) = ? AND hutang_id = ?");
                $stmt->bind_param('si', $today, $hutangFilter);
            } else {
                $stmt = $this->prepare("SELECT COALESCE(SUM(jumlah_bayar), 0) AS total, COUNT(*) AS cnt FROM pembayaran_hutang WHERE DATE(tanggal_bayar) = ?");
                $stmt->bind_param('s', $today);
            }
            $stmt->execute();
            $todayStats = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($hutangFilter > 0) {
                $stmt = $this->prepare("SELECT COALESCE(SUM(sisa_hutang), 0) AS total FROM hutang_supplier WHERE status != 'LUNAS' AND id = ?");
                $stmt->bind_param('i', $hutangFilter);
                $stmt->execute();
                $belum = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $stmt = $this->prepare("SELECT COALESCE(SUM(sisa_hutang), 0) AS total FROM hutang_supplier WHERE status = 'OVERDUE' AND id = ?");
                $stmt->bind_param('i', $hutangFilter);
                $stmt->execute();
                $overdue = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            } else {
                $belum = $this->fetchOne("SELECT COALESCE(SUM(sisa_hutang), 0) AS total FROM hutang_supplier WHERE status != 'LUNAS'");
                $overdue = $this->fetchOne("SELECT COALESCE(SUM(sisa_hutang), 0) AS total FROM hutang_supplier WHERE status = 'OVERDUE'");
            }

            echo json_encode([
                'status' => 'success',
                'total_pembayaran' => 'Rp ' . number_format((float)($todayStats['total'] ?? 0), 0, ',', '.'),
                'jumlah_transaksi' => number_format((int)($todayStats['cnt'] ?? 0), 0, ',', '.'),
                'hutang_belum_lunas' => 'Rp ' . number_format((float)($belum['total'] ?? 0), 0, ',', '.'),
                'hutang_overdue' => 'Rp ' . number_format((float)($overdue['total'] ?? 0), 0, ',', '.'),
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
        }
        exit;
    }
}

if (is_direct_script(__FILE__)) {
    require_login(true);
    require_csrf_for_unsafe_request(true);

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    try {
        $hutang = new HutangSupplier();
        $action = request_action('none');

        $allowed_actions = [
            'get_data_hutang',
            'save_hutang',
            'save_pembayaran',
            'get_hutang_detail',
            'get_pembayaran_history',
            'get_pembayaran_detail',
            'get_pembayaran_list',
            'get_pembayaran_stats',
            'delete_hutang',
            'search_suppliers',
            'search_hutang',
        ];

        if (!in_array($action, $allowed_actions, true)) {
            http_response_code(404);
            echo json_encode(['status' => 'failed', 'msg' => 'Unknown function']);
            exit;
        }

        $hutang->$action();
    } catch (Exception $e) {
        error_log('[HUTANG SUPPLIER ROUTER ERROR] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
    }
    exit;
}
