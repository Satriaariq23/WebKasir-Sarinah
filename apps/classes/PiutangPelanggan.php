<?php

/**
 * Customer receivable and payment receipt endpoints.
 */

require_once(__DIR__ . '/../config.php');
require_once(__DIR__ . '/DBConnection.php');
require_once(__DIR__ . '/NumericInput.php');

class PiutangPelanggan extends DBConnection
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

    private function receivableStatus($total, $received, $dueDate)
    {
        $remaining = max((float)$total - (float)$received, 0);
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
            case 'SEBAGIAN':
                // Kompatibilitas data lama: pembayaran parsial tetap berstatus belum lunas.
                return '<span class="badge bg-warning text-dark">Belum Lunas</span>';
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

    private function generate_no_piutang()
    {
        return $this->nextNumber('piutang_pelanggan', 'no_piutang', 'PUT-' . date('Y-m-'));
    }

    private function generate_no_penerimaan()
    {
        return $this->nextNumber('penerimaan_piutang', 'no_penerimaan', 'TPT-' . date('Y-m-'));
    }

    private function update_sisa_piutang($piutangId)
    {
        $piutangId = (int)$piutangId;
        if ($piutangId <= 0) {
            throw new Exception('ID piutang tidak valid');
        }

        $stmt = $this->prepare("SELECT total_piutang, tanggal_jatuh_tempo FROM piutang_pelanggan WHERE id = ?");
        $stmt->bind_param('i', $piutangId);
        $stmt->execute();
        $piutang = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$piutang) {
            throw new Exception('Data piutang tidak ditemukan');
        }

        $stmt = $this->prepare("SELECT COALESCE(SUM(jumlah_penerimaan), 0) AS total_diterima FROM penerimaan_piutang WHERE piutang_id = ?");
        $stmt->bind_param('i', $piutangId);
        $stmt->execute();
        $receivedRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $total = (float)$piutang['total_piutang'];
        $totalReceived = (float)($receivedRow['total_diterima'] ?? 0);
        $remaining = max($total - $totalReceived, 0);
        $status = $this->receivableStatus($total, $totalReceived, $piutang['tanggal_jatuh_tempo']);

        $stmt = $this->prepare("
            UPDATE piutang_pelanggan
               SET total_diterima = ?,
                   sisa_piutang = ?,
                   status = ?,
                   updated_at = NOW()
             WHERE id = ?
        ");
        $stmt->bind_param('ddsi', $totalReceived, $remaining, $status, $piutangId);
        $stmt->execute();
        $stmt->close();
    }

    public function get_data_piutang()
    {
        $draw = (int)($_POST['draw'] ?? 1);
        $start = max(0, (int)($_POST['start'] ?? 0));
        $length = (int)($_POST['length'] ?? 10);
        if ($length <= 0) {
            $length = 10;
        }

        $search = trim($_POST['search']['value'] ?? '');
        $status = trim($_POST['status'] ?? '');
        $pelangganFilter = (int)($_POST['pelanggan_filter'] ?? 0);
        $orderIndex = (int)($_POST['order'][0]['column'] ?? 0);
        $orderDir = strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $columns = [
            0 => 'p.id',
            1 => 'p.tanggal_piutang',
            2 => 'p.no_piutang',
            3 => 'pel.nama',
            4 => 'p.total_piutang',
            5 => 'p.sisa_piutang',
            6 => 'p.tanggal_jatuh_tempo',
            7 => 'p.status',
        ];
        $orderColumn = $columns[$orderIndex] ?? 'p.id';

        try {
            $whereParts = [];
            $params = [];
            $types = '';

            if ($search !== '') {
                $whereParts[] = "(p.no_piutang LIKE ? OR p.ref_penjualan LIKE ? OR p.keterangan LIKE ? OR pel.nama LIKE ?)";
                $like = '%' . $search . '%';
                array_push($params, $like, $like, $like, $like);
                $types .= 'ssss';
            }

            if ($status !== '') {
                $whereParts[] = "p.status = ?";
                $params[] = $status;
                $types .= 's';
            }

            if ($pelangganFilter > 0) {
                $whereParts[] = "p.pelanggan_id = ?";
                $params[] = $pelangganFilter;
                $types .= 'i';
            }

            $where = empty($whereParts) ? '' : ' WHERE ' . implode(' AND ', $whereParts);

            $totalRecords = (int)($this->fetchOne("SELECT COUNT(*) AS cnt FROM piutang_pelanggan")['cnt'] ?? 0);

            $countSql = "SELECT COUNT(*) AS cnt
                           FROM piutang_pelanggan p
                           LEFT JOIN pelanggan pel ON pel.id = p.pelanggan_id
                         {$where}";
            $stmt = $this->prepare($countSql);
            $this->bindParams($stmt, $types, $params);
            $stmt->execute();
            $recordsFiltered = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $stmt->close();

            $sql = "SELECT p.id,
                           p.no_piutang,
                           p.tanggal_piutang,
                           p.tanggal_jatuh_tempo,
                           p.total_piutang,
                           p.total_diterima,
                           p.sisa_piutang,
                           p.status,
                           p.keterangan,
                           p.ref_penjualan,
                           pel.nama AS nama_pelanggan,
                           pel.kode AS kode_pelanggan,
                           DATEDIFF(p.tanggal_jatuh_tempo, CURDATE()) AS hari_jatuh_tempo
                      FROM piutang_pelanggan p
                      LEFT JOIN pelanggan pel ON pel.id = p.pelanggan_id
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
                    'tanggal_piutang' => !empty($row['tanggal_piutang']) ? date('d/m/Y', strtotime($row['tanggal_piutang'])) : '-',
                    'tanggal_jatuh_tempo' => !empty($row['tanggal_jatuh_tempo']) ? date('d/m/Y', strtotime($row['tanggal_jatuh_tempo'])) : '-',
                    'jatuh_tempo' => $jatuhTempo,
                    'no_piutang' => $row['no_piutang'] ?: '-',
                    'nama_pelanggan' => $row['nama_pelanggan'] ?: '-',
                    'kode_pelanggan' => $row['kode_pelanggan'] ?: '-',
                    'total_piutang' => number_format((float)$row['total_piutang'], 0, ',', '.'),
                    'total_diterima' => number_format((float)$row['total_diterima'], 0, ',', '.'),
                    'sisa_piutang' => number_format((float)$row['sisa_piutang'], 0, ',', '.'),
                    'status' => $row['status'],
                    'status_badge' => $this->statusBadge($row['status']),
                    'keterangan' => $row['keterangan'] ?: '-',
                    'ref_penjualan' => $row['ref_penjualan'] ?: '-',
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
            error_log('[GET_DATA_PIUTANG ERROR] ' . $e->getMessage());
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

    public function save_piutang()
    {
        $transactionStarted = false;
        $writeLock = false;
        try {
            $id = (int)($_POST['id'] ?? 0);
            $pelangganId = (int)($_POST['pelanggan_id'] ?? 0);
            $tanggalPiutang = $_POST['tanggal_piutang'] ?? date('Y-m-d');
            $tanggalJatuhTempo = $_POST['tanggal_jatuh_tempo'] ?? $tanggalPiutang;
            $totalPiutang = $this->parseCurrency($_POST['total_piutang'] ?? 0);
            $keterangan = trim($_POST['keterangan'] ?? '');
            $refPenjualan = trim($_POST['ref_penjualan'] ?? '');
            $userId = $this->currentUserId();

            if ($pelangganId <= 0) {
                throw new Exception('Pelanggan wajib dipilih');
            }
            if ($totalPiutang <= 0) {
                throw new Exception('Total piutang harus lebih dari 0');
            }

            $lockResult = $this->query("SELECT GET_LOCK('sarinah_piutang_write', 10) AS acquired");
            $writeLock = $lockResult && (int)($lockResult->fetch_assoc()['acquired'] ?? 0) === 1;
            if (!$writeLock) {
                throw new Exception('Data piutang lain sedang diproses. Silakan coba kembali.');
            }
            $this->conn->begin_transaction();
            $transactionStarted = true;

            if ($id <= 0) {
                $noPiutang = $this->generate_no_piutang();
                $status = $this->receivableStatus($totalPiutang, 0, $tanggalJatuhTempo);
                $stmt = $this->prepare("
                    INSERT INTO piutang_pelanggan
                        (no_piutang, tanggal_piutang, tanggal_jatuh_tempo, pelanggan_id, total_piutang,
                         total_diterima, sisa_piutang, status, keterangan, ref_penjualan, user_id, tgl_input)
                    VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param(
                    'sssiddsssi',
                    $noPiutang,
                    $tanggalPiutang,
                    $tanggalJatuhTempo,
                    $pelangganId,
                    $totalPiutang,
                    $totalPiutang,
                    $status,
                    $keterangan,
                    $refPenjualan,
                    $userId
                );
                $stmt->execute();
                $id = $stmt->insert_id;
                $stmt->close();
            } else {
                $receivedStmt = $this->prepare("SELECT total_diterima FROM piutang_pelanggan WHERE id = ? FOR UPDATE");
                $receivedStmt->bind_param('i', $id);
                $receivedStmt->execute();
                $existing = $receivedStmt->get_result()->fetch_assoc();
                $receivedStmt->close();
                if (!$existing) {
                    throw new Exception('Data piutang tidak ditemukan');
                }
                if ((float)$existing['total_diterima'] > $totalPiutang + 0.01) {
                    throw new Exception('Total piutang tidak boleh lebih kecil dari penerimaan yang sudah tercatat');
                }
                $stmt = $this->prepare("
                    UPDATE piutang_pelanggan
                       SET tanggal_piutang = ?,
                           tanggal_jatuh_tempo = ?,
                           pelanggan_id = ?,
                           total_piutang = ?,
                           keterangan = ?,
                           ref_penjualan = ?,
                           updated_at = NOW()
                     WHERE id = ?
                ");
                $stmt->bind_param('ssidssi', $tanggalPiutang, $tanggalJatuhTempo, $pelangganId, $totalPiutang, $keterangan, $refPenjualan, $id);
                $stmt->execute();
                $stmt->close();
                $this->update_sisa_piutang($id);
            }

            $this->conn->commit();
            $transactionStarted = false;

            echo json_encode(['status' => 'success', 'msg' => 'Piutang berhasil disimpan', 'id' => $id]);
        } catch (Exception $e) {
            if ($transactionStarted) {
                $this->conn->rollback();
            }
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        } finally {
            if ($writeLock) {
                $this->conn->query("SELECT RELEASE_LOCK('sarinah_piutang_write')");
            }
        }
        exit;
    }

    public function save_penerimaan()
    {
        $transactionStarted = false;
        $numberLock = false;
        try {
            $piutangId = (int)($_POST['piutang_id'] ?? 0);
            $tanggalTerima = $_POST['tanggal_terima'] ?? $_POST['tanggal_penerimaan'] ?? date('Y-m-d');
            $jumlahTerima = $this->parseCurrency($_POST['jumlah_terima'] ?? $_POST['jumlah_penerimaan'] ?? 0);
            $metodeTerima = trim($_POST['metode_bayar'] ?? $_POST['metode_penerimaan'] ?? 'tunai');
            $noBukti = trim($_POST['no_bukti'] ?? $_POST['no_referensi'] ?? '');
            $keterangan = trim($_POST['keterangan'] ?? '');
            $userId = $this->currentUserId();

            if ($piutangId <= 0) {
                throw new Exception('Piutang wajib dipilih');
            }
            if ($jumlahTerima <= 0) {
                throw new Exception('Jumlah penerimaan harus lebih dari 0');
            }

            $lockResult = $this->query("SELECT GET_LOCK('sarinah_piutang_write', 10) AS acquired");
            $numberLock = $lockResult && (int)($lockResult->fetch_assoc()['acquired'] ?? 0) === 1;
            if (!$numberLock) {
                throw new Exception('Penerimaan lain sedang diproses. Silakan coba kembali.');
            }
            $this->conn->begin_transaction();
            $transactionStarted = true;

            $stmt = $this->prepare("SELECT sisa_piutang FROM piutang_pelanggan WHERE id = ? FOR UPDATE");
            $stmt->bind_param('i', $piutangId);
            $stmt->execute();
            $piutang = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$piutang) {
                throw new Exception('Data piutang tidak ditemukan');
            }
            if ($jumlahTerima > (float)$piutang['sisa_piutang']) {
                throw new Exception('Jumlah penerimaan tidak boleh lebih dari sisa piutang');
            }

            $noPenerimaan = $this->generate_no_penerimaan();
            $stmt = $this->prepare("
                INSERT INTO penerimaan_piutang
                    (no_penerimaan, piutang_id, tanggal_penerimaan, jumlah_penerimaan,
                     metode_penerimaan, no_bukti, keterangan, user_id, tgl_input)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param('sisdsssi', $noPenerimaan, $piutangId, $tanggalTerima, $jumlahTerima, $metodeTerima, $noBukti, $keterangan, $userId);
            $stmt->execute();
            $receiptId = $stmt->insert_id;
            $stmt->close();

            $this->update_sisa_piutang($piutangId);

            $this->conn->commit();
            $transactionStarted = false;

            echo json_encode([
                'status' => 'success',
                'msg' => 'Penerimaan berhasil disimpan',
                'id' => $receiptId,
                'no_penerimaan' => $noPenerimaan,
            ]);
        } catch (Exception $e) {
            if ($transactionStarted) {
                $this->conn->rollback();
            }
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        } finally {
            if ($numberLock) {
                $this->conn->query("SELECT RELEASE_LOCK('sarinah_piutang_write')");
            }
        }
        exit;
    }

    public function get_piutang_detail()
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        $stmt = $this->prepare("
            SELECT p.*, pel.nama AS nama_pelanggan, pel.kode AS kode_pelanggan
              FROM piutang_pelanggan p
              LEFT JOIN pelanggan pel ON pel.id = p.pelanggan_id
             WHERE p.id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        echo json_encode($data ?: ['status' => 'failed', 'msg' => 'Data piutang tidak ditemukan']);
        exit;
    }

    public function get_penerimaan_history()
    {
        $piutangId = (int)($_POST['piutang_id'] ?? $_GET['piutang_id'] ?? 0);
        $stmt = $this->prepare("SELECT * FROM penerimaan_piutang WHERE piutang_id = ? ORDER BY tanggal_penerimaan DESC, id DESC");
        $stmt->bind_param('i', $piutangId);
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $row['tanggal_penerimaan'] = date('d/m/Y', strtotime($row['tanggal_penerimaan']));
            $row['jumlah_penerimaan'] = number_format((float)$row['jumlah_penerimaan'], 0, ',', '.');
            $data[] = $row;
        }
        $stmt->close();

        echo json_encode($data);
        exit;
    }

    public function get_penerimaan_detail()
    {
        $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => 'ID penerimaan tidak valid']);
            exit;
        }

        try {
            $stmt = $this->prepare("
                SELECT r.id,
                       r.no_penerimaan,
                       r.tanggal_penerimaan,
                       r.jumlah_penerimaan,
                       r.metode_penerimaan,
                       r.no_bukti,
                       r.keterangan,
                       p.no_piutang,
                       p.ref_penjualan,
                       p.total_piutang,
                       p.sisa_piutang,
                       pel.nama AS nama_pelanggan,
                       pel.kode AS kode_pelanggan,
                       pel.alamat AS alamat_pelanggan
                  FROM penerimaan_piutang r
                  LEFT JOIN piutang_pelanggan p ON p.id = r.piutang_id
                  LEFT JOIN pelanggan pel ON pel.id = p.pelanggan_id
                 WHERE r.id = ?
                 LIMIT 1
            ");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                throw new Exception('Data penerimaan tidak ditemukan');
            }

            $row['tanggal'] = !empty($row['tanggal_penerimaan']) ? date('d/m/Y', strtotime($row['tanggal_penerimaan'])) : '-';
            $row['jumlah_penerimaan_format'] = 'Rp ' . number_format((float)$row['jumlah_penerimaan'], 0, ',', '.');
            $row['total_piutang_format'] = 'Rp ' . number_format((float)$row['total_piutang'], 0, ',', '.');
            $row['sisa_piutang_format'] = 'Rp ' . number_format((float)$row['sisa_piutang'], 0, ',', '.');

            echo json_encode(['status' => 'success', 'data' => $row]);
        } catch (Exception $e) {
            http_response_code(404);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function delete_piutang()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID piutang tidak valid');
            }

            $stmt = $this->prepare("SELECT COUNT(*) AS cnt FROM penerimaan_piutang WHERE piutang_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $count = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $stmt->close();

            if ($count > 0) {
                throw new Exception('Tidak dapat menghapus piutang yang sudah memiliki penerimaan');
            }

            $stmt = $this->prepare("DELETE FROM piutang_pelanggan WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
            $stmt->close();

            if ($deleted <= 0) {
                throw new Exception('Data piutang tidak ditemukan');
            }

            echo json_encode(['status' => 'success', 'msg' => 'Piutang berhasil dihapus']);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function search_pelanggan()
    {
        $term = trim($_GET['term'] ?? $_GET['q'] ?? '');
        $like = '%' . $term . '%';
        $stmt = $this->prepare("
            SELECT id, nama, kode, alamat
              FROM pelanggan
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

    public function search_piutang()
    {
        $term = trim($_GET['term'] ?? $_GET['q'] ?? '');
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

        try {
            $whereParts = [];
            $params = [];
            $types = '';

            if ($id > 0) {
                $whereParts[] = 'p.id = ?';
                $whereParts[] = "p.status != 'LUNAS'";
                $whereParts[] = 'p.sisa_piutang > 0';
                $params[] = $id;
                $types .= 'i';
            } else {
                $whereParts[] = "p.status != 'LUNAS'";
                $whereParts[] = 'p.sisa_piutang > 0';

                if ($term !== '') {
                    $whereParts[] = "(p.no_piutang LIKE ? OR p.ref_penjualan LIKE ? OR pel.nama LIKE ? OR pel.kode LIKE ?)";
                    $like = '%' . $term . '%';
                    array_push($params, $like, $like, $like, $like);
                    $types .= 'ssss';
                }
            }

            $where = ' WHERE ' . implode(' AND ', $whereParts);
            $sql = "
                SELECT p.id,
                       p.no_piutang,
                       p.ref_penjualan,
                       p.tanggal_piutang,
                       p.tanggal_jatuh_tempo,
                       p.total_piutang,
                       p.total_diterima,
                       p.sisa_piutang,
                       p.status,
                       pel.nama AS nama_pelanggan,
                       pel.kode AS kode_pelanggan
                  FROM piutang_pelanggan p
                  LEFT JOIN pelanggan pel ON pel.id = p.pelanggan_id
                {$where}
                 ORDER BY p.tanggal_piutang DESC, p.id DESC
                 LIMIT 30
            ";

            $stmt = $this->prepare($sql);
            $this->bindParams($stmt, $types, $params);
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $sisa = (float)$row['sisa_piutang'];
                $nama = $row['nama_pelanggan'] ?: '-';
                $kode = !empty($row['kode_pelanggan']) ? ' (' . $row['kode_pelanggan'] . ')' : '';
                $data[] = [
                    'id' => (int)$row['id'],
                    'text' => ($row['no_piutang'] ?: '-') . ' - ' . $nama . $kode . ' - Sisa Rp ' . number_format($sisa, 0, ',', '.'),
                    'no_piutang' => $row['no_piutang'] ?: '-',
                    'ref_penjualan' => $row['ref_penjualan'] ?: '-',
                    'nama_pelanggan' => $nama,
                    'kode_pelanggan' => $row['kode_pelanggan'] ?: '',
                    'tanggal_piutang' => !empty($row['tanggal_piutang']) ? date('d/m/Y', strtotime($row['tanggal_piutang'])) : '-',
                    'tanggal_jatuh_tempo' => !empty($row['tanggal_jatuh_tempo']) ? date('d/m/Y', strtotime($row['tanggal_jatuh_tempo'])) : '-',
                    'total_piutang' => (float)$row['total_piutang'],
                    'total_piutang_format' => 'Rp ' . number_format((float)$row['total_piutang'], 0, ',', '.'),
                    'total_diterima' => (float)$row['total_diterima'],
                    'total_diterima_format' => 'Rp ' . number_format((float)$row['total_diterima'], 0, ',', '.'),
                    'sisa_piutang' => $sisa,
                    'sisa_piutang_format' => 'Rp ' . number_format($sisa, 0, ',', '.'),
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

    public function get_penerimaan_list()
    {
        $draw = (int)($_POST['draw'] ?? 1);
        $start = max(0, (int)($_POST['start'] ?? 0));
        $length = (int)($_POST['length'] ?? 25);
        if ($length <= 0) {
            $length = 25;
        }

        $search = trim($_POST['search']['value'] ?? '');
        $tanggalFilter = trim($_POST['tanggal_filter'] ?? '');
        $pelangganFilter = (int)($_POST['pelanggan_filter'] ?? 0);
        $piutangFilter = (int)($_POST['piutang_id'] ?? $_GET['piutang_id'] ?? 0);
        $orderIndex = (int)($_POST['order'][0]['column'] ?? 1);
        $orderDir = strtolower($_POST['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $columns = [
            0 => 'r.id',
            1 => 'r.no_penerimaan',
            2 => 'p.no_piutang',
            3 => 'pel.nama',
            4 => 'r.tanggal_penerimaan',
            5 => 'r.jumlah_penerimaan',
            6 => 'r.metode_penerimaan',
            7 => 'r.keterangan',
        ];
        $orderColumn = $columns[$orderIndex] ?? 'r.id';

        try {
            $whereParts = [];
            $params = [];
            $types = '';

            if ($search !== '') {
                $whereParts[] = "(r.no_penerimaan LIKE ? OR r.no_bukti LIKE ? OR p.no_piutang LIKE ? OR pel.nama LIKE ?)";
                $like = '%' . $search . '%';
                array_push($params, $like, $like, $like, $like);
                $types .= 'ssss';
            }

            if ($tanggalFilter !== '') {
                $whereParts[] = "DATE(r.tanggal_penerimaan) = ?";
                $params[] = $tanggalFilter;
                $types .= 's';
            }

            if ($pelangganFilter > 0) {
                $whereParts[] = "p.pelanggan_id = ?";
                $params[] = $pelangganFilter;
                $types .= 'i';
            }

            if ($piutangFilter > 0) {
                $whereParts[] = "r.piutang_id = ?";
                $params[] = $piutangFilter;
                $types .= 'i';
            }

            $where = empty($whereParts) ? '' : ' WHERE ' . implode(' AND ', $whereParts);

            $totalRecords = (int)($this->fetchOne("SELECT COUNT(*) AS cnt FROM penerimaan_piutang")['cnt'] ?? 0);

            $countSql = "SELECT COUNT(*) AS cnt
                           FROM penerimaan_piutang r
                           LEFT JOIN piutang_pelanggan p ON p.id = r.piutang_id
                           LEFT JOIN pelanggan pel ON pel.id = p.pelanggan_id
                         {$where}";
            $stmt = $this->prepare($countSql);
            $this->bindParams($stmt, $types, $params);
            $stmt->execute();
            $recordsFiltered = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $stmt->close();

            $sql = "SELECT r.id,
                           r.no_penerimaan,
                           r.tanggal_penerimaan,
                           r.jumlah_penerimaan,
                           r.metode_penerimaan,
                           r.no_bukti,
                           r.keterangan,
                           p.no_piutang,
                           pel.nama AS nama_pelanggan
                      FROM penerimaan_piutang r
                      LEFT JOIN piutang_pelanggan p ON p.id = r.piutang_id
                      LEFT JOIN pelanggan pel ON pel.id = p.pelanggan_id
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
                    'no_penerimaan' => $row['no_penerimaan'] ?: '-',
                    'no_piutang' => $row['no_piutang'] ?: '-',
                    'nama_pelanggan' => $row['nama_pelanggan'] ?: '-',
                    'tanggal' => !empty($row['tanggal_penerimaan']) ? date('d/m/Y', strtotime($row['tanggal_penerimaan'])) : '-',
                    'jumlah_terima' => 'Rp ' . number_format((float)$row['jumlah_penerimaan'], 0, ',', '.'),
                    'metode' => ucfirst($row['metode_penerimaan'] ?: 'tunai'),
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
            error_log('[GET_PENERIMAAN_LIST ERROR] ' . $e->getMessage());
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

    public function get_penerimaan_stats()
    {
        try {
            $today = date('Y-m-d');
            $piutangFilter = (int)($_POST['piutang_id'] ?? $_GET['piutang_id'] ?? 0);

            if ($piutangFilter > 0) {
                $stmt = $this->prepare("SELECT COALESCE(SUM(jumlah_penerimaan), 0) AS total, COUNT(*) AS cnt FROM penerimaan_piutang WHERE DATE(tanggal_penerimaan) = ? AND piutang_id = ?");
                $stmt->bind_param('si', $today, $piutangFilter);
            } else {
                $stmt = $this->prepare("SELECT COALESCE(SUM(jumlah_penerimaan), 0) AS total, COUNT(*) AS cnt FROM penerimaan_piutang WHERE DATE(tanggal_penerimaan) = ?");
                $stmt->bind_param('s', $today);
            }
            $stmt->execute();
            $todayStats = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($piutangFilter > 0) {
                $stmt = $this->prepare("SELECT COALESCE(SUM(sisa_piutang), 0) AS total FROM piutang_pelanggan WHERE status != 'LUNAS' AND id = ?");
                $stmt->bind_param('i', $piutangFilter);
                $stmt->execute();
                $belum = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $stmt = $this->prepare("SELECT COALESCE(SUM(sisa_piutang), 0) AS total FROM piutang_pelanggan WHERE status = 'OVERDUE' AND id = ?");
                $stmt->bind_param('i', $piutangFilter);
                $stmt->execute();
                $overdue = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            } else {
                $belum = $this->fetchOne("SELECT COALESCE(SUM(sisa_piutang), 0) AS total FROM piutang_pelanggan WHERE status != 'LUNAS'");
                $overdue = $this->fetchOne("SELECT COALESCE(SUM(sisa_piutang), 0) AS total FROM piutang_pelanggan WHERE status = 'OVERDUE'");
            }

            echo json_encode([
                'status' => 'success',
                'total_penerimaan' => 'Rp ' . number_format((float)($todayStats['total'] ?? 0), 0, ',', '.'),
                'jumlah_transaksi' => number_format((int)($todayStats['cnt'] ?? 0), 0, ',', '.'),
                'piutang_belum_lunas' => 'Rp ' . number_format((float)($belum['total'] ?? 0), 0, ',', '.'),
                'piutang_overdue' => 'Rp ' . number_format((float)($overdue['total'] ?? 0), 0, ',', '.'),
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
        $piutang = new PiutangPelanggan();
        $action = request_action('none');

        $allowed_actions = [
            'get_data_piutang',
            'save_piutang',
            'save_penerimaan',
            'get_piutang_detail',
            'get_penerimaan_history',
            'get_penerimaan_detail',
            'get_penerimaan_list',
            'get_penerimaan_stats',
            'delete_piutang',
            'search_pelanggan',
            'search_piutang',
        ];

        if (!in_array($action, $allowed_actions, true)) {
            http_response_code(404);
            echo json_encode(['status' => 'failed', 'msg' => 'Unknown function']);
            exit;
        }

        $piutang->$action();
    } catch (Exception $e) {
        error_log('[PIUTANG PELANGGAN ROUTER ERROR] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
    }
    exit;
}
