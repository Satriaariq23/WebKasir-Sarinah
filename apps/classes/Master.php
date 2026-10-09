<?php
require_once('../config.php');
require_once(__DIR__ . '/NumericInput.php');

class Master extends DBConnection
{
    private $settings;
    protected $error;
    protected $errno;
    public function __construct()
    {
        global $_settings;
        $this->settings = $_settings;
        parent::__construct();
    }
    public function __destruct()
    {
        parent::__destruct();
    }
    function capture_err()
    {
        if (!$this->error)
            return false;
        else {
            $resp['status'] = 'failed';
            $resp['error'] = $this->error;
            return json_encode($resp);
            exit;
        }
    }

    private function validateTieredPricing($P2, $mqty2, $P3, $mqty3, $P4, $mqty4)
    {
        $tiers = [
            ['level' => 2, 'price' => (float)$P2, 'qty' => (float)$mqty2],
            ['level' => 3, 'price' => (float)$P3, 'qty' => (float)$mqty3],
            ['level' => 4, 'price' => (float)$P4, 'qty' => (float)$mqty4],
        ];

        $activeTiers = [];
        foreach ($tiers as $tier) {
            $isActive = $tier['price'] > 0 || $tier['qty'] > 0;
            if (!$isActive) {
                continue;
            }

            if ($tier['price'] <= 0) {
                return 'Harga P' . $tier['level'] . ' wajib diisi jika Min Qty Level ' . $tier['level'] . ' diisi';
            }

            if ($tier['qty'] <= 0) {
                return 'Min Qty Level ' . $tier['level'] . ' wajib lebih dari 0 jika Harga P' . $tier['level'] . ' diisi';
            }

            $activeTiers[] = $tier;
        }

        $seenQty = [];
        foreach ($activeTiers as $tier) {
            $qtyKey = rtrim(rtrim(number_format($tier['qty'], 4, '.', ''), '0'), '.');
            if (isset($seenQty[$qtyKey])) {
                return 'Min Qty Level ' . $tier['level'] . ' tidak boleh sama dengan Level ' . $seenQty[$qtyKey] . ' (Qty ' . $qtyKey . ')';
            }

            $seenQty[$qtyKey] = $tier['level'];
        }

        $previousTier = null;
        foreach ($activeTiers as $tier) {
            if ($previousTier !== null && $tier['qty'] <= $previousTier['qty']) {
                return 'Min Qty Level ' . $tier['level'] . ' harus lebih besar dari Level ' . $previousTier['level'];
            }

            $previousTier = $tier;
        }

        return '';
    }

    private function validateProductPrices($harga_beli, $harga_newmodal, $harga_sales, $harga_kasir, $P2, $P3, $P4)
    {
        $harga_beli = (float)$harga_beli;
        $harga_newmodal = (float)$harga_newmodal;
        $salePrices = [
            'Harga Sales' => (float)$harga_sales,
            'Harga Kasir' => (float)$harga_kasir,
            'Harga P2' => (float)$P2,
            'Harga P3' => (float)$P3,
            'Harga P4' => (float)$P4,
        ];

        if ($harga_newmodal < $harga_beli) {
            return 'Harga New Modal tidak boleh kurang dari Harga Beli';
        }

        foreach ($salePrices as $label => $price) {
            if ($price > 0 && $price < $harga_newmodal) {
                return $label . ' tidak boleh kurang dari Harga New Modal';
            }
        }

        return '';
    }

    private function sanitizeWholeMoney($value)
    {
        return max(0, NumericInput::wholeMoney($value));
    }

    private function sanitizeQuantity($value)
    {
        return max(0, NumericInput::quantity($value, 4));
    }

    private function roundProductMoneyFields(&$row)
    {
        foreach (['harga_beli', 'harga_newmodal', 'harga_sales', 'harga_kasir', 'P2', 'P3', 'P4'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = (string)round((float)$row[$field]);
            }
        }
    }

    private function productImageDirectory()
    {
        return rtrim(str_replace('\\', '/', base_app), '/') . '/uploads/';
    }

    private function productImageUrl($filename)
    {
        $filename = trim((string)$filename);
        if ($filename === '') {
            return base_url . 'uploads/no-image-available.png';
        }

        return base_url . 'uploads/' . rawurlencode(basename($filename));
    }

    private function uploadProductImage($field = 'gambar_produk')
    {
        if (!isset($_FILES[$field]) || (int)$_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $_FILES[$field];
        $allowedMime = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];
        $validated = validate_uploaded_image($file, $allowedMime, 3 * 1024 * 1024, 8000, 8000, 12000000);
        $tmpName = (string)$file['tmp_name'];

        $uploadDir = $this->productImageDirectory();
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new Exception('Folder upload gambar produk tidak dapat dibuat.');
        }

        $filename = secure_upload_filename('produk', $validated['extension']);
        if (!$this->storeNormalizedProductImage($tmpName, $uploadDir . $filename, $validated)) {
            throw new Exception('Gagal menyimpan gambar produk ke server.');
        }

        return $filename;
    }

    /**
     * Re-encode gambar dengan GD bila tersedia untuk membuang metadata/payload.
     * Instalasi tanpa GD tetap memakai container validation yang ketat sebelum
     * move_uploaded_file, sehingga upload tidak berubah menjadi arbitrary file.
     */
    private function storeNormalizedProductImage($source, $destination, array $validated)
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
            return move_uploaded_file($source, $destination);
        }

        $bytes = @file_get_contents($source);
        $sourceImage = $bytes !== false ? @imagecreatefromstring($bytes) : false;
        unset($bytes);
        if ($sourceImage === false) {
            throw new RuntimeException('Gambar gagal didekode dengan aman.');
        }

        $sourceWidth = (int)$validated['width'];
        $sourceHeight = (int)$validated['height'];
        $scale = min(1, 2400 / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int)round($sourceWidth * $scale));
        $targetHeight = max(1, (int)round($sourceHeight * $scale));
        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

        if (in_array($validated['mime'], ['image/png', 'image/webp'], true)) {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
            imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        $copied = imagecopyresampled(
            $targetImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        $saved = false;
        if ($copied && $validated['mime'] === 'image/jpeg' && function_exists('imagejpeg')) {
            $saved = imagejpeg($targetImage, $destination, 88);
        } elseif ($copied && $validated['mime'] === 'image/png' && function_exists('imagepng')) {
            $saved = imagepng($targetImage, $destination, 6);
        } elseif ($copied && $validated['mime'] === 'image/webp' && function_exists('imagewebp')) {
            $saved = imagewebp($targetImage, $destination, 88);
        }

        imagedestroy($targetImage);
        imagedestroy($sourceImage);

        if (!$saved) {
            @unlink($destination);
            throw new RuntimeException('Server tidak mendukung encoding format gambar tersebut.');
        }

        @chmod($destination, 0644);
        return true;
    }

    private function deleteProductImage($filename)
    {
        $filename = basename(trim((string)$filename));
        if ($filename === '' || $filename === 'no-image-available.png') {
            return;
        }

        $path = $this->productImageDirectory() . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /* =========================
       BARANG 
    ========================== */
    function get_data_barang()
    {
        $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
        $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
        $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
        $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
        $order  = isset($_POST['order'][0]) ? $_POST['order'][0] : ['column' => 0, 'dir' => 'asc'];

        // Filter tambahan
        $filter_status = isset($_POST['filter_status']) ? $_POST['filter_status'] : '';
        $filter_ppn = isset($_POST['filter_ppn']) ? $_POST['filter_ppn'] : '';
        $filter_kategori = isset($_POST['filter_kategori']) ? $_POST['filter_kategori'] : '';

        $columns_arr = [
            "p.id",
            "p.barcode",
            "p.nama_produk",
            "s.nama_satuan",
            "p.stok",
            "p.harga_beli",
            "p.harga_newmodal",
            "p.harga_sales",
            "p.harga_kasir",
            "k.nama_kategori",
            "p.status",
            "p.is_ppn"
        ];
        $order_column = isset($columns_arr[$order['column']]) ? $columns_arr[$order['column']] : "p.id";
        $order_dir = strtolower($order['dir']) === 'desc' ? "DESC" : "ASC";

        $stmt = $this->prepare("SELECT COUNT(*) as cnt FROM produk p");
        $stmt->execute();
        $totalCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        $sql = "SELECT p.*, s.nama_satuan, k.nama_kategori
                FROM produk p 
                LEFT JOIN satuan s ON p.id_satuan = s.id 
                LEFT JOIN kategori k ON p.id_kategori = k.id 
                WHERE 1=1";
        $sql_count = "SELECT COUNT(*) as cnt 
                      FROM produk p 
                      LEFT JOIN satuan s ON p.id_satuan = s.id 
                      LEFT JOIN kategori k ON p.id_kategori = k.id 
                      WHERE 1=1";

        $params = [];
        $types = "";
        $params_count = [];
        $types_count = "";

        // Filter pencarian
        if (!empty($search)) {
            $sql .= " AND (p.nama_produk LIKE ? OR p.barcode LIKE ? OR p.deskripsi LIKE ? OR k.nama_kategori LIKE ? OR s.nama_satuan LIKE ?)";
            $sql_count .= " AND (p.nama_produk LIKE ? OR p.barcode LIKE ? OR p.deskripsi LIKE ? OR k.nama_kategori LIKE ? OR s.nama_satuan LIKE ?)";
            $like = "%{$search}%";
            $params = array_fill(0, 5, $like);
            $params_count = array_fill(0, 5, $like);
            $types = str_repeat("s", 5);
            $types_count = str_repeat("s", 5);
        }

        // Filter kategori
        if ($filter_kategori !== '') {
            $sql .= " AND p.id_kategori = ?";
            $sql_count .= " AND p.id_kategori = ?";
            $params[] = (int)$filter_kategori;
            $params_count[] = (int)$filter_kategori;
            $types .= "i";
            $types_count .= "i";
        }

        // Filter status
        if ($filter_status !== '') {
            $sql .= " AND p.status = ?";
            $sql_count .= " AND p.status = ?";
            $params[] = (int)$filter_status;
            $params_count[] = (int)$filter_status;
            $types .= "i";
            $types_count .= "i";
        }

        // Filter PPN
        if ($filter_ppn !== '') {
            $sql .= " AND p.is_ppn = ?";
            $sql_count .= " AND p.is_ppn = ?";
            $params[] = (int)$filter_ppn;
            $params_count[] = (int)$filter_ppn;
            $types .= "i";
            $types_count .= "i";
        }

        $sql .= " ORDER BY $order_column $order_dir LIMIT ? OFFSET ?";
        $params[] = $length;
        $params[] = $start;
        $types .= "ii";

        $stmt = $this->prepare($sql);
        if (!empty($types)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $stmt2 = $this->prepare($sql_count);
        if (!empty($types_count)) $stmt2->bind_param($types_count, ...$params_count);
        $stmt2->execute();
        $recordsFilterCount = $stmt2->get_result()->fetch_assoc()['cnt'];
        $stmt2->close();

        $data = [];
        $i = 1 + $start;
        while ($row = $result->fetch_assoc()) {
            $this->roundProductMoneyFields($row);
            $data[] = [
                'no' => $i++,
                'id' => $row['id'],
                'barcode' => $row['barcode'],
                'nama_produk' => $row['nama_produk'],
                'gambar_produk' => $row['gambar_produk'] ?? '',
                'gambar_url' => $this->productImageUrl($row['gambar_produk'] ?? ''),
                'nama_satuan' => $row['nama_satuan'],
                'stok' => $row['stok'],
                'harga_beli' => $row['harga_beli'],
                'harga_newmodal' => $row['harga_newmodal'],
                'harga_sales' => $row['harga_sales'],
                'harga_kasir' => $row['harga_kasir'],
                'nama_kategori' => $row['nama_kategori'],
                'status' => $row['status'],
                'is_ppn' => $row['is_ppn'],
                'is_poin' => $row['is_poin']
            ];
        }

        return json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $recordsFilterCount,
            'data' => $data
        ]);
    }

    /* =========================
       SIMPAN BARANG BARU
    ========================== */
    function save_barang()
    {
        // Ambil dan validasi data dari POST
        $barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : '';
        $nama_produk = isset($_POST['nama_produk']) ? trim($_POST['nama_produk']) : '';
        $id_kategori = isset($_POST['id_kategori']) ? (int)$_POST['id_kategori'] : 0;
        $id_satuan = isset($_POST['id_satuan']) ? (int)$_POST['id_satuan'] : 0;
        $stok = $this->sanitizeQuantity($_POST['stok'] ?? 0);
        $harga_beli = $this->sanitizeWholeMoney($_POST['harga_beli'] ?? 0);
        $harga_newmodal = $this->sanitizeWholeMoney($_POST['harga_newmodal'] ?? 0);
        $harga_sales = $this->sanitizeWholeMoney($_POST['harga_sales'] ?? 0);
        $harga_kasir = $this->sanitizeWholeMoney($_POST['harga_kasir'] ?? 0);
        // Tiered pricing fields
        $P2 = $this->sanitizeWholeMoney($_POST['P2'] ?? 0);
        $mqty2 = $this->sanitizeQuantity($_POST['mqty2'] ?? 0);
        $P3 = $this->sanitizeWholeMoney($_POST['P3'] ?? 0);
        $mqty3 = $this->sanitizeQuantity($_POST['mqty3'] ?? 0);
        $P4 = $this->sanitizeWholeMoney($_POST['P4'] ?? 0);
        $mqty4 = $this->sanitizeQuantity($_POST['mqty4'] ?? 0);
        $deskripsi = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
        $is_ppn = isset($_POST['is_ppn']) ? 1 : 0;
        $status = isset($_POST['status']) ? 1 : 0;
        $is_poin = isset($_POST['is_poin']) ? 1 : 0;

        try {
            $gambar_produk = $this->uploadProductImage();
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
        $gambar_produk = $gambar_produk ?? '';

        // Validasi input wajib
        if (empty($barcode) || empty($nama_produk) || $id_kategori <= 0 || $id_satuan <= 0) {
            $this->deleteProductImage($gambar_produk);
            $resp['status'] = 'failed';
            $resp['msg'] = 'Data wajib tidak boleh kosong';
            return json_encode($resp);
        }

        $tierValidation = $this->validateTieredPricing($P2, $mqty2, $P3, $mqty3, $P4, $mqty4);
        if ($tierValidation !== '') {
            $this->deleteProductImage($gambar_produk);
            $resp['status'] = 'failed';
            $resp['msg'] = $tierValidation;
            return json_encode($resp);
        }

        $priceValidation = $this->validateProductPrices($harga_beli, $harga_newmodal, $harga_sales, $harga_kasir, $P2, $P3, $P4);
        if ($priceValidation !== '') {
            $this->deleteProductImage($gambar_produk);
            $resp['status'] = 'failed';
            $resp['msg'] = $priceValidation;
            return json_encode($resp);
        }

        // Cek duplikasi barcode
        $check_stmt = $this->prepare("SELECT id FROM produk WHERE barcode = ?");
        $check_stmt->bind_param("s", $barcode);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $this->deleteProductImage($gambar_produk);
            $resp['status'] = 'failed';
            $resp['msg'] = 'Barcode sudah digunakan';
            $check_stmt->close();
            return json_encode($resp);
        }
        $check_stmt->close();

        // Insert dengan prepared statement
        $stmt = $this->prepare("INSERT INTO produk 
            (barcode, nama_produk, id_kategori, id_satuan, stok, 
             harga_beli, harga_newmodal, harga_sales, harga_kasir, 
             P2, mqty2, P3, mqty3, P4, mqty4,
             deskripsi, gambar_produk, is_ppn, status, is_poin) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "ssiidddddddddddssiii",
            $barcode,
            $nama_produk,
            $id_kategori,
            $id_satuan,
            $stok,
            $harga_beli,
            $harga_newmodal,
            $harga_sales,
            $harga_kasir,
            $P2,
            $mqty2,
            $P3,
            $mqty3,
            $P4,
            $mqty4,
            $deskripsi,
            $gambar_produk,
            $is_ppn,
            $status,
            $is_poin
        );

        $resp = [];
        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['msg'] = 'Data barang berhasil disimpan';
        } else {
            $this->deleteProductImage($gambar_produk);
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menyimpan data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       UPDATE BARANG
    ========================== */
    function update_barang()
    {
        // Ambil dan validasi data dari POST
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : '';
        $nama_produk = isset($_POST['nama_produk']) ? trim($_POST['nama_produk']) : '';
        $id_kategori = isset($_POST['id_kategori']) ? (int)$_POST['id_kategori'] : 0;
        $id_satuan = isset($_POST['id_satuan']) ? (int)$_POST['id_satuan'] : 0;
        $stockWasSubmitted = array_key_exists('stok', $_POST);
        $submittedStock = $stockWasSubmitted ? $this->sanitizeQuantity($_POST['stok']) : null;
        $harga_beli = $this->sanitizeWholeMoney($_POST['harga_beli'] ?? 0);
        $harga_newmodal = $this->sanitizeWholeMoney($_POST['harga_newmodal'] ?? 0);
        $harga_sales = $this->sanitizeWholeMoney($_POST['harga_sales'] ?? 0);
        $harga_kasir = $this->sanitizeWholeMoney($_POST['harga_kasir'] ?? 0);
        // Tiered pricing fields
        $P2 = $this->sanitizeWholeMoney($_POST['P2'] ?? 0);
        $mqty2 = $this->sanitizeQuantity($_POST['mqty2'] ?? 0);
        $P3 = $this->sanitizeWholeMoney($_POST['P3'] ?? 0);
        $mqty3 = $this->sanitizeQuantity($_POST['mqty3'] ?? 0);
        $P4 = $this->sanitizeWholeMoney($_POST['P4'] ?? 0);
        $mqty4 = $this->sanitizeQuantity($_POST['mqty4'] ?? 0);
        $deskripsi = isset($_POST['deskripsi']) ? trim($_POST['deskripsi']) : '';
        $is_ppn = isset($_POST['is_ppn']) ? 1 : 0;
        $status = isset($_POST['status']) ? 1 : 0;
        $is_poin = isset($_POST['is_poin']) ? 1 : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        // Validasi input wajib
        if (empty($barcode) || empty($nama_produk) || $id_kategori <= 0 || $id_satuan <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Data wajib tidak boleh kosong';
            return json_encode($resp);
        }

        // Stok adalah saldo transaksi, bukan atribut master yang boleh ditimpa.
        // Tetap cocokkan nilai yang ditampilkan form agar edit dari halaman yang
        // sudah kedaluwarsa tidak diam-diam menimpa/menyamarkan mutasi stok baru.
        $stock_stmt = $this->prepare("SELECT stok, gambar_produk FROM produk WHERE id = ? LIMIT 1");
        $stock_stmt->bind_param("i", $id);
        $stock_stmt->execute();
        $stock_result = $stock_stmt->get_result();
        $currentProduct = $stock_result->fetch_assoc();
        $stock_stmt->close();

        if (!$currentProduct) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Data barang tidak ditemukan';
            return json_encode($resp);
        }

        if ($stockWasSubmitted && abs((float)$submittedStock - (float)$currentProduct['stok']) > 0.0001) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Stok telah berubah atau diedit. Gunakan menu Stok Opname untuk koreksi stok, lalu muat ulang data barang.';
            return json_encode($resp);
        }

        $tierValidation = $this->validateTieredPricing($P2, $mqty2, $P3, $mqty3, $P4, $mqty4);
        if ($tierValidation !== '') {
            $resp['status'] = 'failed';
            $resp['msg'] = $tierValidation;
            return json_encode($resp);
        }

        $priceValidation = $this->validateProductPrices($harga_beli, $harga_newmodal, $harga_sales, $harga_kasir, $P2, $P3, $P4);
        if ($priceValidation !== '') {
            $resp['status'] = 'failed';
            $resp['msg'] = $priceValidation;
            return json_encode($resp);
        }

        $oldImage = (string)($currentProduct['gambar_produk'] ?? '');
        $removeImage = isset($_POST['hapus_gambar']) && (int)$_POST['hapus_gambar'] === 1;
        try {
            $uploadedImage = $this->uploadProductImage();
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
        $gambar_produk = $uploadedImage !== null ? $uploadedImage : ($removeImage ? '' : $oldImage);

        // Cek duplikasi barcode (kecuali untuk record yang sedang diupdate)
        $check_stmt = $this->prepare("SELECT id FROM produk WHERE barcode = ? AND id != ?");
        $check_stmt->bind_param("si", $barcode, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            if ($uploadedImage !== null) {
                $this->deleteProductImage($uploadedImage);
            }
            $resp['status'] = 'failed';
            $resp['msg'] = 'Barcode sudah digunakan oleh barang lain';
            $check_stmt->close();
            return json_encode($resp);
        }
        $check_stmt->close();

        // Update data menggunakan prepared statement
        $stmt = $this->prepare("UPDATE produk SET 
            barcode=?, nama_produk=?, id_kategori=?, id_satuan=?, 
            harga_beli=?, harga_newmodal=?, harga_sales=?, harga_kasir=?, 
            P2=?, mqty2=?, P3=?, mqty3=?, P4=?, mqty4=?, deskripsi=?, gambar_produk=?, 
            is_ppn=?, status=?, is_poin=? 
            WHERE id=?");

        // Binding parameter
        $stmt->bind_param(
            "ssiiddddddddddssiiii",
            $barcode,
            $nama_produk,
            $id_kategori,
            $id_satuan,
            $harga_beli,
            $harga_newmodal,
            $harga_sales,
            $harga_kasir,
            $P2,
            $mqty2,
            $P3,
            $mqty3,
            $P4,
            $mqty4,
            $deskripsi,
            $gambar_produk,
            $is_ppn,
            $status,
            $is_poin,
            $id
        );

        if ($stmt->execute()) {
            if ($oldImage !== $gambar_produk) {
                $this->deleteProductImage($oldImage);
            }
            $resp['status'] = 'success';
            $resp['msg'] = 'Data barang berhasil diperbarui';
        } else {
            if ($uploadedImage !== null) {
                $this->deleteProductImage($uploadedImage);
            }
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal memperbarui data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /**
     * Memperbarui gambar produk dari modal upload cepat pada tabel barang.
     * Endpoint ini sengaja tidak menerima atribut master lain agar upload gambar
     * tidak dapat mengubah stok, harga, barcode, atau pengaturan produk.
     */
    function update_barang_image()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            return json_encode(['status' => 'failed', 'msg' => 'ID barang tidak valid.']);
        }

        $current_stmt = $this->prepare("SELECT gambar_produk FROM produk WHERE id = ? LIMIT 1");
        $current_stmt->bind_param("i", $id);
        $current_stmt->execute();
        $currentProduct = $current_stmt->get_result()->fetch_assoc();
        $current_stmt->close();

        if (!$currentProduct) {
            return json_encode(['status' => 'failed', 'msg' => 'Data barang tidak ditemukan.']);
        }

        try {
            $uploadedImage = $this->uploadProductImage();
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }

        if ($uploadedImage === null) {
            return json_encode(['status' => 'failed', 'msg' => 'Pilih gambar baru yang ingin diupload.']);
        }

        $oldImage = (string)($currentProduct['gambar_produk'] ?? '');
        $stmt = $this->prepare("UPDATE produk SET gambar_produk = ? WHERE id = ? AND COALESCE(gambar_produk, '') = ?");
        $stmt->bind_param("sis", $uploadedImage, $id, $oldImage);
        $executed = $stmt->execute();
        $updated = $executed && $stmt->affected_rows === 1;
        $error = $stmt->error;
        $stmt->close();

        if (!$updated) {
            $this->deleteProductImage($uploadedImage);
            if ($executed) {
                return json_encode([
                    'status' => 'failed',
                    'msg' => 'Gambar barang telah berubah. Muat ulang data lalu coba kembali.'
                ]);
            }

            return json_encode(['status' => 'failed', 'msg' => 'Gagal memperbarui gambar: ' . $error]);
        }

        if ($oldImage !== $uploadedImage) {
            $this->deleteProductImage($oldImage);
        }

        return json_encode([
            'status' => 'success',
            'msg' => 'Gambar produk berhasil diperbarui.',
            'gambar_url' => $this->productImageUrl($uploadedImage)
        ]);
    }

    /* =========================
       HAPUS BARANG
    ========================== */
    function delete_barang()
    {
        // Ambil ID dengan aman
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $image_stmt = $this->prepare("SELECT gambar_produk FROM produk WHERE id = ? LIMIT 1");
        $image_stmt->bind_param("i", $id);
        $image_stmt->execute();
        $productImageRow = $image_stmt->get_result()->fetch_assoc();
        $productImage = (string)($productImageRow['gambar_produk'] ?? '');
        $image_stmt->close();

        $active_usage_stmt = $this->prepare("
            SELECT COUNT(*) AS total
            FROM sale_det sd
            INNER JOIN sale s ON s.saleid = sd.sale_id
            WHERE sd.produk_id = ?
            AND COALESCE(s.sale_status, 'ACTIVE') != 'DELETED'
        ");
        $active_usage_stmt->bind_param("i", $id);
        $active_usage_stmt->execute();
        $active_usage_result = $active_usage_stmt->get_result();
        $active_usage_count = (int)($active_usage_result->fetch_assoc()['total'] ?? 0);
        $active_usage_stmt->close();

        if ($active_usage_count > 0) {
            $stmt = $this->prepare("UPDATE produk SET status = 0 WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $resp['status'] = 'success';
                $resp['action'] = 'deactivated';
                $resp['msg'] = 'Barang masih memiliki riwayat transaksi aktif/void, sehingga tidak bisa dihapus permanen dan sudah dinonaktifkan';
            } else {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Gagal menonaktifkan barang: ' . $stmt->error;
            }

            $stmt->close();
            return json_encode($resp);
        }

        $deleted_usage_stmt = $this->prepare("
            SELECT COUNT(*) AS total
            FROM sale_det sd
            INNER JOIN sale s ON s.saleid = sd.sale_id
            WHERE sd.produk_id = ?
            AND s.sale_status = 'DELETED'
        ");
        $deleted_usage_stmt->bind_param("i", $id);
        $deleted_usage_stmt->execute();
        $deleted_usage_result = $deleted_usage_stmt->get_result();
        $deleted_usage_count = (int)($deleted_usage_result->fetch_assoc()['total'] ?? 0);
        $deleted_usage_stmt->close();

        if ($deleted_usage_count > 0) {
            if (!$this->conn->begin_transaction()) {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Gagal memulai transaksi database: ' . $this->conn->error;
                return json_encode($resp);
            }

            $cleanup_stmt = $this->prepare("
                DELETE sd
                FROM sale_det sd
                INNER JOIN sale s ON s.saleid = sd.sale_id
                WHERE sd.produk_id = ?
                AND s.sale_status = 'DELETED'
            ");
            $cleanup_stmt->bind_param("i", $id);

            if (!$cleanup_stmt->execute()) {
                $cleanup_error = $cleanup_stmt->error;
                $cleanup_stmt->close();
                $this->conn->rollback();

                $resp['status'] = 'failed';
                $resp['msg'] = 'Gagal membersihkan detail transaksi yang sudah dihapus: ' . $cleanup_error;
                return json_encode($resp);
            }
            $cleanup_stmt->close();

            $stmt = $this->prepare("DELETE FROM produk WHERE id=?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $this->conn->commit();
                $this->deleteProductImage($productImage);
                $resp['status'] = 'success';
                $resp['action'] = 'deleted';
                $resp['msg'] = 'Data barang berhasil dihapus. Detail transaksi yang sudah ditandai terhapus ikut dibersihkan.';
                $stmt->close();
                return json_encode($resp);
            }

            $deleteError = $stmt->error;
            $deleteErrno = $stmt->errno;
            $stmt->close();
            $this->conn->rollback();

            if ($deleteErrno === 1451 || stripos($deleteError, 'foreign key constraint') !== false) {
                $stmt = $this->prepare("UPDATE produk SET status = 0 WHERE id = ?");
                $stmt->bind_param("i", $id);

                if ($stmt->execute()) {
                    $resp['status'] = 'success';
                    $resp['action'] = 'deactivated';
                    $resp['msg'] = 'Barang masih terhubung dengan data lain, sehingga tidak bisa dihapus permanen dan sudah dinonaktifkan';
                } else {
                    $resp['status'] = 'failed';
                    $resp['msg'] = 'Gagal menonaktifkan barang: ' . $stmt->error;
                }

                $stmt->close();
                return json_encode($resp);
            }

            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menghapus data: ' . $deleteError;
            return json_encode($resp);
        }

        $stmt = $this->prepare("DELETE FROM produk WHERE id=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $this->deleteProductImage($productImage);
            $resp['status'] = 'success';
            $resp['action'] = 'deleted';
            $resp['msg'] = 'Data barang berhasil dihapus';
        } else {
            $deleteError = $stmt->error;
            $deleteErrno = $stmt->errno;
            $stmt->close();

            if ($deleteErrno === 1451 || stripos($deleteError, 'foreign key constraint') !== false) {
                $stmt = $this->prepare("UPDATE produk SET status = 0 WHERE id = ?");
                $stmt->bind_param("i", $id);

                if ($stmt->execute()) {
                    $resp['status'] = 'success';
                    $resp['action'] = 'deactivated';
                    $resp['msg'] = 'Barang memiliki riwayat data, sehingga tidak bisa dihapus permanen dan sudah dinonaktifkan';
                } else {
                    $resp['status'] = 'failed';
                    $resp['msg'] = 'Gagal menonaktifkan barang: ' . $stmt->error;
                }

                $stmt->close();
                return json_encode($resp);
            }

            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menghapus data: ' . $deleteError;
            return json_encode($resp);
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       GET SINGLE BARANG
    ========================== */
    function get_single_barang()
    {
        // Ambil ID dengan aman
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = false;
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $stmt = $this->prepare("SELECT * FROM produk WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $qry = $stmt->get_result();

        if ($qry->num_rows > 0) {
            $resp['status'] = true;
            $data = $qry->fetch_assoc();
            $this->roundProductMoneyFields($data);
            $data['gambar_url'] = $this->productImageUrl($data['gambar_produk'] ?? '');
            $resp['data'] = $data;
        } else {
            $resp['status'] = false;
            $resp['msg'] = 'Data tidak ditemukan';
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       GET DATA DROPDOWN UNTUK KATEGORI DAN SATUAN DAN SUPPLIER
    ========================== */
    function get_kategori()
    {
        $stmt = $this->prepare("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();

        return json_encode($data);
    }

    function get_satuan()
    {
        $stmt = $this->prepare("SELECT id, nama_satuan FROM satuan ORDER BY nama_satuan ASC");
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();

        return json_encode($data);
    }

    function get_supplier()
    {
        $stmt = $this->prepare("SELECT id, kode, nama FROM supplier ORDER BY nama ASC");
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();

        return json_encode($data);
    }

    /* =========================
       SATUAN
    ========================== */
    function get_data_satuan()
    {
        $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
        $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
        $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
        $search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
        $order  = isset($_POST['order'][0]) ? $_POST['order'][0] : ['column' => 0, 'dir' => 'asc'];

        // Kolom yang boleh diurutkan
        $columns_arr = ["id", "nama_satuan"];
        $order_column = isset($columns_arr[$order['column']]) ? $columns_arr[$order['column']] : "id";
        $order_dir = strtolower($order['dir']) === 'desc' ? "DESC" : "ASC";

        // Hitung total data
        $stmt = $this->prepare("SELECT COUNT(*) as cnt FROM satuan");
        if (!$stmt) {
            die("SQL Error (COUNT): " . $this->error);
        }
        $stmt->execute();
        $totalCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Query utama
        $sql = "SELECT * FROM satuan";
        $params = [];
        $types = "";

        if (!empty($search)) {
            $sql .= " WHERE nama_satuan LIKE ?";
            $like = "%{$search}%";
            $params[] = $like;
            $types .= "s";
        }

        $sql .= " ORDER BY $order_column $order_dir LIMIT ? OFFSET ?";
        $params[] = $length;
        $params[] = $start;
        $types .= "ii";

        $stmt = $this->prepare($sql);
        if (!$stmt) {
            die("SQL Error (SELECT): " . $this->error . " | SQL: " . $sql);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        // Hitung data setelah filter
        $sql_count = "SELECT COUNT(*) as cnt FROM satuan";
        $params2 = [];
        $types2 = "";
        $like = "%{$search}%"; // Define $like here too
        if (!empty($search)) {
            $sql_count .= " WHERE nama_satuan LIKE ?";
            $params2[] = $like;
            $types2 = "s";
        }
        $stmt2 = $this->prepare($sql_count);
        if (!$stmt2) {
            die("SQL Error (COUNT Filter): " . $this->error . " | SQL: " . $sql_count);
        }
        if (!empty($params2)) {
            $stmt2->bind_param($types2, ...$params2);
        }
        $stmt2->execute();
        $recordsFilterCount = $stmt2->get_result()->fetch_assoc()['cnt'];
        $stmt2->close();

        // Susun hasil
        $data = [];
        $i = 1 + $start;
        while ($row = $result->fetch_assoc()) {
            $row['no'] = $i++;
            $data[] = $row;
        }
        $stmt->close();

        // Return JSON
        return json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $recordsFilterCount,
            'data' => $data
        ]);
    }

    /* =========================
       SIMPAN satuan BARU
    ========================== */
    function save_satuan()
    {
        $nama_satuan      = isset($_POST['nama_satuan']) ? trim($_POST['nama_satuan']) : '';

        // Validasi nama_satuan tidak kosong
        if (empty($nama_satuan)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Nama satuan tidak boleh kosong';
            return json_encode($resp);
        }

        // Cek apakah nama_satuan sudah ada
        $stmt_check = $this->prepare("SELECT id FROM satuan WHERE nama_satuan = ?");
        $stmt_check->bind_param("s", $nama_satuan);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        if ($result->num_rows > 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Nama satuan sudah ada';
            $stmt_check->close();
            return json_encode($resp);
        }
        $stmt_check->close();

        // Insert pakai prepared statement (aman dari SQL injection)
        $stmt = $this->prepare("INSERT INTO satuan
            (nama_satuan)
            VALUES (?)");
        $stmt->bind_param("s", $nama_satuan);

        $resp = [];
        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['nama_satuan'] = $nama_satuan;
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       UPDATE satuan
    ========================== */
    function update_satuan()
    {
        $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nama_satuan = isset($_POST['nama_satuan']) ? trim($_POST['nama_satuan']) : '';

        $resp = [];

        // Validasi ID
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        // Validasi nama_satuan
        if ($nama_satuan === '') {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Nama satuan tidak boleh kosong';
            return json_encode($resp);
        }

        // Query update aman
        $stmt = $this->prepare("UPDATE satuan SET nama_satuan=? WHERE id=?");
        if (!$stmt) {
            $resp['status'] = 'failed';
            $resp['msg'] = $this->error;
            return json_encode($resp);
        }

        $stmt->bind_param("si", $nama_satuan, $id);

        if ($stmt->execute()) {
            $resp['status'] = 'success';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = $stmt->error;
        }
        $stmt->close();

        return json_encode($resp);
    }

    /* =========================
       HAPUS satuan
    ========================== */
    function delete_satuan()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $stmt = $this->prepare("DELETE FROM satuan WHERE id=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $resp['status'] = 'success';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       GET SINGLE satuan
    ========================== */
    function get_single_satuan()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = false;
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $stmt = $this->prepare("SELECT * FROM satuan WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $qry = $stmt->get_result();

        if ($qry->num_rows > 0) {
            $resp['status'] = true;
            $resp['data'] = $qry->fetch_assoc();
        } else {
            $resp['status'] = false;
            $resp['msg'] = 'Data tidak ditemukan';
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       KATEGORI
    ========================== */
    function get_data_kategori()
    {
        $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
        $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
        $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
        $search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';
        $order  = isset($_POST['order'][0]) ? $_POST['order'][0] : ['column' => 0, 'dir' => 'asc'];

        // Kolom yang boleh diurutkan
        $columns_arr = ["id", "nama_kategori"];
        $order_column = isset($columns_arr[$order['column']]) ? $columns_arr[$order['column']] : "id";
        $order_dir = strtolower($order['dir']) === 'desc' ? "DESC" : "ASC";

        // Hitung total data
        $stmt = $this->prepare("SELECT COUNT(*) as cnt FROM kategori");
        if (!$stmt) {
            die("SQL Error (COUNT): " . $this->error);
        }
        $stmt->execute();
        $totalCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Query utama
        $sql = "SELECT * FROM kategori";
        $params = [];
        $types = "";

        if (!empty($search)) {
            $sql .= " WHERE nama_kategori LIKE ?";
            $like = "%{$search}%";
            $params[] = $like;
            $types .= "s";
        }

        $sql .= " ORDER BY $order_column $order_dir LIMIT ? OFFSET ?";
        $params[] = $length;
        $params[] = $start;
        $types .= "ii";

        $stmt = $this->prepare($sql);
        if (!$stmt) {
            die("SQL Error (SELECT): " . $this->error . " | SQL: " . $sql);
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        // Hitung data setelah filter
        $sql_count = "SELECT COUNT(*) as cnt FROM kategori";
        $params2 = [];
        $types2 = "";
        $like = "%{$search}%"; // Define $like here too
        if (!empty($search)) {
            $sql_count .= " WHERE nama_kategori LIKE ?";
            $params2[] = $like;
            $types2 = "s";
        }
        $stmt2 = $this->prepare($sql_count);
        if (!$stmt2) {
            die("SQL Error (COUNT Filter): " . $this->error . " | SQL: " . $sql_count);
        }
        if (!empty($params2)) {
            $stmt2->bind_param($types2, ...$params2);
        }
        $stmt2->execute();
        $recordsFilterCount = $stmt2->get_result()->fetch_assoc()['cnt'];
        $stmt2->close();

        // Susun hasil
        $data = [];
        $i = 1 + $start;
        while ($row = $result->fetch_assoc()) {
            $row['no'] = $i++;
            $data[] = $row;
        }
        $stmt->close();

        // Return JSON
        return json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $recordsFilterCount,
            'data' => $data
        ]);
    }

    /* =========================
       SIMPAN kategori BARU
    ========================== */
    function save_kategori()
    {
        $nama_kategori      = isset($_POST['nama_kategori']) ? trim($_POST['nama_kategori']) : '';

        // Validasi nama_kategori tidak kosong
        if (empty($nama_kategori)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Nama kategori tidak boleh kosong';
            return json_encode($resp);
        }

        // Cek apakah nama_kategori sudah ada
        $stmt_check = $this->prepare("SELECT id FROM kategori WHERE nama_kategori = ?");
        $stmt_check->bind_param("s", $nama_kategori);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        if ($result->num_rows > 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Nama kategori sudah ada';
            $stmt_check->close();
            return json_encode($resp);
        }
        $stmt_check->close();

        // Insert pakai prepared statement (aman dari SQL injection)
        $stmt = $this->prepare("INSERT INTO kategori
            (nama_kategori)
            VALUES (?)");
        $stmt->bind_param("s", $nama_kategori);

        $resp = [];
        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['nama_kategori'] = $nama_kategori;
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       UPDATE kategori
    ========================== */
    function update_kategori()
    {
        $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nama_kategori = isset($_POST['nama_kategori']) ? trim($_POST['nama_kategori']) : '';

        $resp = [];

        // Validasi ID
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        // Validasi nama_kategori
        if ($nama_kategori === '') {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Nama kategori tidak boleh kosong';
            return json_encode($resp);
        }

        // Query update aman
        $stmt = $this->prepare("UPDATE kategori SET nama_kategori=? WHERE id=?");
        if (!$stmt) {
            $resp['status'] = 'failed';
            $resp['msg'] = $this->error;
            return json_encode($resp);
        }

        $stmt->bind_param("si", $nama_kategori, $id);

        if ($stmt->execute()) {
            $resp['status'] = 'success';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = $stmt->error;
        }
        $stmt->close();

        return json_encode($resp);
    }

    /* =========================
       HAPUS kategori
    ========================== */
    function delete_kategori()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $stmt = $this->prepare("DELETE FROM kategori WHERE id=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $resp['status'] = 'success';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       GET SINGLE kategori
    ========================== */
    function get_single_kategori()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = false;
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $stmt = $this->prepare("SELECT * FROM kategori WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $qry = $stmt->get_result();

        if ($qry->num_rows > 0) {
            $resp['status'] = true;
            $resp['data'] = $qry->fetch_assoc();
        } else {
            $resp['status'] = false;
            $resp['msg'] = 'Data tidak ditemukan';
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       PELANGGAN
    ========================== */
    private function generatePelangganCode()
    {
        $qry = $this->query("SELECT COALESCE(MAX(CAST(SUBSTRING(kode, 3) AS UNSIGNED)), 0) AS last_number
                            FROM pelanggan
                            WHERE UPPER(COALESCE(type_customer, '')) = 'PELANGGAN'
                              AND kode REGEXP '^SL[0-9]+$'");
        $last_number = (int) $qry->fetch_assoc()['last_number'];
        $next_number = $last_number + 1;

        // Minimal 4 digit, tetapi tetap aman jika jumlah pelanggan melewati 9.999.
        return 'SL' . str_pad((string) $next_number, 4, '0', STR_PAD_LEFT);
    }

    function get_data_pelanggan()
    {
        $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
        $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
        $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
        $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
        $order  = isset($_POST['order'][0]) ? $_POST['order'][0] : ['column' => 0, 'dir' => 'asc'];

        $columns_arr = array("id", "kode", "nama", "alamat", "kota", "telpon", "status");
        $order_column = isset($columns_arr[$order['column']]) ? $columns_arr[$order['column']] : "id";
        $order_dir = strtolower($order['dir']) === 'desc' ? "DESC" : "ASC";
        $pelanggan_where = "UPPER(COALESCE(type_customer, '')) = 'PELANGGAN' AND (kode IS NULL OR kode NOT LIKE 'MB%')";

        // Hitung total data PELANGGAN
        $stmt = $this->prepare("SELECT COUNT(*) as cnt FROM pelanggan WHERE {$pelanggan_where}");

        $stmt->execute();
        $totalCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Base query untuk PELANGGAN
        $sql = "SELECT * FROM pelanggan WHERE {$pelanggan_where}";
        $sql_count = "SELECT COUNT(*) as cnt FROM pelanggan WHERE {$pelanggan_where}";

        $params = [];
        $types = "";
        $params_count = [];
        $types_count = "";

        // Filter pencarian
        if (!empty($search)) {
            $sql .= " AND (nama LIKE ? OR kode LIKE ? OR alamat LIKE ? OR kota LIKE ? OR telpon LIKE ?)";
            $sql_count .= " AND (nama LIKE ? OR kode LIKE ? OR alamat LIKE ? OR kota LIKE ? OR telpon LIKE ?)";
            $like = "%{$search}%";
            $params = array_fill(0, 5, $like);
            $params_count = array_fill(0, 5, $like);
            $types = str_repeat("s", 5);
            $types_count = str_repeat("s", 5);
        }

        // Tambahkan ORDER BY dan LIMIT
        $sql .= " ORDER BY $order_column $order_dir LIMIT ? OFFSET ?";
        $params[] = $length;
        $params[] = $start;
        $types .= "ii";

        // Eksekusi query
        // $stmt = $this->prepare($sql);
        $stmt = $this->prepare($sql);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // Hitung data setelah filter
        $stmt2 = $this->prepare($sql_count);
        if (!empty($types_count)) {
            $stmt2->bind_param($types_count, ...$params_count);
        }
        $stmt2->execute();
        $recordsFilterCount = $stmt2->get_result()->fetch_assoc()['cnt'];
        $stmt2->close();

        // Susun hasil
        $data = [];
        $i = 1 + $start;
        while ($row = $result->fetch_assoc()) {
            $row['no'] = $i++;
            $data[] = $row;
        }
        $stmt->close();

        return json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $recordsFilterCount,
            'data' => $data
        ]);
    }

    /* =========================
       SIMPAN PELANGGAN
    ========================== */
    function save_pelanggan()
    {
        $resp = [];
        $nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
        $kota = isset($_POST['kota']) ? trim($_POST['kota']) : '';
        $telpon = isset($_POST['telpon']) ? trim($_POST['telpon']) : '';
        $status = isset($_POST['status']) ? 1 : 0;

        // Validasi input wajib
        if (empty($nama)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Nama wajib diisi';
            return json_encode($resp);
        }

        // Penguncian mencegah dua request bersamaan memperoleh nomor SL yang sama.
        $lock = $this->query("SELECT GET_LOCK('sarinah_pelanggan_sl_code', 10) AS acquired");
        $lock_acquired = (int) $lock->fetch_assoc()['acquired'] === 1;
        if (!$lock_acquired) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Kode pelanggan sedang diproses. Silakan coba simpan kembali.';
            return json_encode($resp);
        }

        try {
            $kode = $this->generatePelangganCode();

            $stmt = $this->prepare("INSERT INTO pelanggan
                (kode, nama, alamat, kota, telpon, status, type_customer, date_created)
                VALUES (?, ?, ?, ?, ?, ?, 'PELANGGAN', NOW())");
            $stmt->bind_param("sssssi", $kode, $nama, $alamat, $kota, $telpon, $status);

            if ($stmt->execute()) {
                $resp['status'] = 'success';
                $resp['msg'] = 'Data pelanggan berhasil disimpan dengan kode ' . $kode;
                $resp['kode'] = $kode;
            } else {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Gagal menyimpan data: ' . $stmt->error;
            }
            $stmt->close();
        } finally {
            $this->query("SELECT RELEASE_LOCK('sarinah_pelanggan_sl_code')");
        }

        return json_encode($resp);
    }

    /* =========================
       UPDATE PELANGGAN
    ========================== */
    function update_pelanggan()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
        $kota = isset($_POST['kota']) ? trim($_POST['kota']) : '';
        $telpon = isset($_POST['telpon']) ? trim($_POST['telpon']) : '';
        $status = isset($_POST['status']) ? 1 : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        // Validasi input wajib - HAPUS VALIDASI KODE
        if (empty($nama)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Nama wajib diisi';
            return json_encode($resp);
        }

        // HAPUS CEK DUPLIKASI KODE karena kode tidak bisa diubah

        // Update data - HAPUS KOLOM KODE dari UPDATE
        $stmt = $this->prepare("UPDATE pelanggan SET 
            nama=?, alamat=?, kota=?, telpon=?, status=?, date_updated=NOW() 
            WHERE id=? AND UPPER(COALESCE(type_customer, '')) = 'PELANGGAN' AND (kode IS NULL OR kode NOT LIKE 'MB%')");

        $stmt->bind_param("ssssii", $nama, $alamat, $kota, $telpon, $status, $id);

        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['msg'] = 'Data pelanggan berhasil diperbarui';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal memperbarui data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       HAPUS PELANGGAN
    ========================== */
    function delete_pelanggan()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $stmt = $this->prepare("DELETE FROM pelanggan WHERE id=? AND UPPER(COALESCE(type_customer, '')) = 'PELANGGAN' AND (kode IS NULL OR kode NOT LIKE 'MB%')");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['msg'] = 'Data pelanggan berhasil dihapus';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menghapus data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       GET SINGLE PELANGGAN
    ========================== */
    function get_single_pelanggan()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = false;
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $stmt = $this->prepare("SELECT * FROM pelanggan WHERE id=? AND UPPER(COALESCE(type_customer, '')) = 'PELANGGAN' AND (kode IS NULL OR kode NOT LIKE 'MB%')");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $qry = $stmt->get_result();

        if ($qry->num_rows > 0) {
            $resp['status'] = true;
            $resp['data'] = $qry->fetch_assoc();
        } else {
            $resp['status'] = false;
            $resp['msg'] = 'Data tidak ditemukan';
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       MEMBER
    ========================== */
    function get_data_member()
    {
        $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
        $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
        $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
        $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
        $order  = isset($_POST['order'][0]) ? $_POST['order'][0] : ['column' => 0, 'dir' => 'asc'];

        $columns_arr = array("p.id", "p.kode", "p.nama", "p.alamat", "p.kota", "p.telpon", "p.status");
        $order_column = isset($columns_arr[$order['column']]) ? $columns_arr[$order['column']] : "p.id";
        $order_dir = strtolower($order['dir']) === 'desc' ? "DESC" : "ASC";
        $member_where = "(UPPER(COALESCE(p.type_customer, '')) = 'MEMBER'
            OR (
                p.kode LIKE 'MB%'
                AND NOT EXISTS (
                    SELECT 1
                    FROM pelanggan pm
                    WHERE pm.kode = p.kode
                      AND pm.id <> p.id
                      AND UPPER(COALESCE(pm.type_customer, '')) = 'MEMBER'
                )
            ))";

        // Hitung total data MEMBER
        $stmt = $this->prepare("SELECT COUNT(*) as cnt FROM pelanggan p WHERE {$member_where}");
        $stmt->execute();
        $totalCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Base query untuk MEMBER
        $sql = "SELECT p.* FROM pelanggan p WHERE {$member_where}";
        $sql_count = "SELECT COUNT(*) as cnt FROM pelanggan p WHERE {$member_where}";

        $params = [];
        $types = "";
        $params_count = [];
        $types_count = "";

        // Filter pencarian
        if (!empty($search)) {
            $sql .= " AND (p.nama LIKE ? OR p.kode LIKE ? OR p.alamat LIKE ? OR p.kota LIKE ? OR p.telpon LIKE ?)";
            $sql_count .= " AND (p.nama LIKE ? OR p.kode LIKE ? OR p.alamat LIKE ? OR p.kota LIKE ? OR p.telpon LIKE ?)";
            $like = "%{$search}%";
            $params = array_fill(0, 5, $like);
            $params_count = array_fill(0, 5, $like);
            $types = str_repeat("s", 5);
            $types_count = str_repeat("s", 5);
        }

        // Tambahkan ORDER BY dan LIMIT
        $sql .= " ORDER BY $order_column $order_dir LIMIT ? OFFSET ?";
        $params[] = $length;
        $params[] = $start;
        $types .= "ii";

        // Eksekusi query
        $stmt = $this->prepare($sql);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // Hitung data setelah filter
        $stmt2 = $this->prepare($sql_count);
        if (!empty($types_count)) {
            $stmt2->bind_param($types_count, ...$params_count);
        }
        $stmt2->execute();
        $recordsFilterCount = $stmt2->get_result()->fetch_assoc()['cnt'];
        $stmt2->close();

        // Susun hasil
        $data = [];
        $i = 1 + $start;
        while ($row = $result->fetch_assoc()) {
            $row['no'] = $i++;
            $data[] = $row;
        }
        $stmt->close();

        return json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $recordsFilterCount,
            'data' => $data
        ]);
    }

    /* =========================
       SIMPAN MEMBER
    ========================== */
    function save_member()
    {
        $nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
        $kota = isset($_POST['kota']) ? trim($_POST['kota']) : '';
        $telpon = isset($_POST['telpon']) ? trim($_POST['telpon']) : '';
        $status = isset($_POST['status']) ? 1 : 0;

        // Validasi input wajib
        if (empty($nama)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Nama wajib diisi';
            return json_encode($resp);
        }

        // Generate kode otomatis: MB + nomor urut
        $qry = $this->query("SELECT MAX(kode) as last_kode FROM pelanggan WHERE kode LIKE 'MB%'");
        $last_kode = $qry && $qry->num_rows > 0 ? $qry->fetch_assoc()['last_kode'] : null;

        if ($last_kode) {
            // Ambil angka dari kode terakhir dan tambah 1
            $last_number = (int) substr($last_kode, 2);
            $next_number = $last_number + 1;
        } else {
            // Jika belum ada data, mulai dari 1
            $next_number = 1;
        }

        // Format kode: MB + 4 digit angka (contoh: MB0001, MB0002, dst)
        $kode = "MB" . str_pad($next_number, 4, "0", STR_PAD_LEFT);

        // Insert data
        $stmt = $this->prepare("INSERT INTO pelanggan 
            (kode, nama, alamat, kota, telpon, status, type_customer, date_created) 
            VALUES (?, ?, ?, ?, ?, ?, 'MEMBER', NOW())");

        $stmt->bind_param("sssssi", $kode, $nama, $alamat, $kota, $telpon, $status);

        $resp = [];
        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['msg'] = 'Data member berhasil disimpan';
            $resp['kode'] = $kode; // Kirim kode yang di-generate ke frontend
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menyimpan data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       UPDATE MEMBER
    ========================== */
    function update_member()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
        $kota = isset($_POST['kota']) ? trim($_POST['kota']) : '';
        $telpon = isset($_POST['telpon']) ? trim($_POST['telpon']) : '';
        $status = isset($_POST['status']) ? 1 : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        // Validasi input wajib - HAPUS VALIDASI KODE
        if (empty($nama)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Nama wajib diisi';
            return json_encode($resp);
        }

        // HAPUS CEK DUPLIKASI KODE karena kode tidak bisa diubah

        // Update data - HAPUS KOLOM KODE dari UPDATE
        $stmt = $this->prepare("UPDATE pelanggan SET 
            nama=?, alamat=?, kota=?, telpon=?, status=?, type_customer='MEMBER', date_updated=NOW() 
            WHERE id=? AND (UPPER(COALESCE(type_customer, '')) = 'MEMBER' OR kode LIKE 'MB%')");

        $stmt->bind_param("ssssii", $nama, $alamat, $kota, $telpon, $status, $id);

        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['msg'] = 'Data member berhasil diperbarui';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal memperbarui data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       HAPUS MEMBER
    ========================== */
    function delete_member()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $stmt = $this->prepare("DELETE FROM pelanggan WHERE id=? AND (UPPER(COALESCE(type_customer, '')) = 'MEMBER' OR kode LIKE 'MB%')");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['msg'] = 'Data member berhasil dihapus';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menghapus data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       GET SINGLE MEMBER
    ========================== */
    function get_single_member()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = false;
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $member_where = "(UPPER(COALESCE(p.type_customer, '')) = 'MEMBER'
            OR (
                p.kode LIKE 'MB%'
                AND NOT EXISTS (
                    SELECT 1
                    FROM pelanggan pm
                    WHERE pm.kode = p.kode
                      AND pm.id <> p.id
                      AND UPPER(COALESCE(pm.type_customer, '')) = 'MEMBER'
                )
            ))";
        $stmt = $this->prepare("SELECT p.* FROM pelanggan p WHERE p.id=? AND {$member_where}");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $qry = $stmt->get_result();

        if ($qry->num_rows > 0) {
            $resp['status'] = true;
            $resp['data'] = $qry->fetch_assoc();
        } else {
            $resp['status'] = false;
            $resp['msg'] = 'Data tidak ditemukan';
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       SUPPLIER
    ========================== */
    function get_data_supplier()
    {
        $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
        $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
        $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
        $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
        $order  = isset($_POST['order'][0]) ? $_POST['order'][0] : ['column' => 0, 'dir' => 'asc'];

        $columns_arr = array("id", "kode", "nama", "cperson", "alamat", "kota", "telpon", "status");
        $order_column = isset($columns_arr[$order['column']]) ? $columns_arr[$order['column']] : "id";
        $order_dir = strtolower($order['dir']) === 'desc' ? "DESC" : "ASC";

        // Hitung total data
        $stmt = $this->prepare("SELECT COUNT(*) as cnt FROM supplier");
        $stmt->execute();
        $totalCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Base query
        $sql = "SELECT * FROM supplier WHERE 1=1";
        $sql_count = "SELECT COUNT(*) as cnt FROM supplier WHERE 1=1";

        $params = [];
        $types = "";
        $params_count = [];
        $types_count = "";

        // Filter pencarian
        if (!empty($search)) {
            $sql .= " AND (nama LIKE ? OR kode LIKE ? OR alamat LIKE ? OR kota LIKE ? OR telpon LIKE ? OR cperson LIKE ?)";
            $sql_count .= " AND (nama LIKE ? OR kode LIKE ? OR alamat LIKE ? OR kota LIKE ? OR telpon LIKE ? OR cperson LIKE ?)";
            $like = "%{$search}%";
            $params = array_fill(0, 6, $like);
            $params_count = array_fill(0, 6, $like);
            $types = str_repeat("s", 6);
            $types_count = str_repeat("s", 6);
        }

        // Tambahkan ORDER BY dan LIMIT
        $sql .= " ORDER BY $order_column $order_dir LIMIT ? OFFSET ?";
        $params[] = $length;
        $params[] = $start;
        $types .= "ii";

        // Eksekusi query
        $stmt = $this->prepare($sql);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // Hitung data setelah filter
        $stmt2 = $this->prepare($sql_count);
        if (!empty($types_count)) {
            $stmt2->bind_param($types_count, ...$params_count);
        }
        $stmt2->execute();
        $recordsFilterCount = $stmt2->get_result()->fetch_assoc()['cnt'];
        $stmt2->close();

        // Susun hasil
        $data = [];
        $i = 1 + $start;
        while ($row = $result->fetch_assoc()) {
            $row['no'] = $i++;
            $data[] = $row;
        }
        $stmt->close();

        return json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $recordsFilterCount,
            'data' => $data
        ]);
    }

    /* =========================
       SIMPAN SUPPLIER
    ========================== */
    function save_supplier()
    {
        $nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
        $kota = isset($_POST['kota']) ? trim($_POST['kota']) : '';
        $telpon = isset($_POST['telpon']) ? trim($_POST['telpon']) : '';
        $cperson = isset($_POST['cperson']) ? trim($_POST['cperson']) : '';
        $status = isset($_POST['status']) ? 1 : 0;

        // Validasi input wajib
        if (empty($nama)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Nama supplier wajib diisi';
            return json_encode($resp);
        }

        // Generate kode otomatis: SP + nomor urut
        $qry = $this->query("SELECT MAX(kode) as last_kode FROM supplier WHERE kode LIKE 'SP%'");
        $last_kode = $qry && $qry->num_rows > 0 ? $qry->fetch_assoc()['last_kode'] : null;

        if ($last_kode) {
            // Ambil angka dari kode terakhir dan tambah 1
            $last_number = (int) substr($last_kode, 2);
            $next_number = $last_number + 1;
        } else {
            // Jika belum ada data, mulai dari 1
            $next_number = 1;
        }

        // Format kode: SP + 4 digit angka (contoh: SP0001, SP0002, dst)
        $kode = "SP" . str_pad($next_number, 4, "0", STR_PAD_LEFT);

        // Insert data
        $stmt = $this->prepare("INSERT INTO supplier 
            (kode, nama, alamat, kota, telpon, cperson, status, date_created) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");

        $stmt->bind_param("ssssssi", $kode, $nama, $alamat, $kota, $telpon, $cperson, $status);

        $resp = [];
        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['msg'] = 'Data supplier berhasil disimpan';
            $resp['kode'] = $kode;
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menyimpan data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       UPDATE SUPPLIER
    ========================== */
    function update_supplier()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nama = isset($_POST['nama']) ? trim($_POST['nama']) : '';
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
        $kota = isset($_POST['kota']) ? trim($_POST['kota']) : '';
        $telpon = isset($_POST['telpon']) ? trim($_POST['telpon']) : '';
        $cperson = isset($_POST['cperson']) ? trim($_POST['cperson']) : '';
        $status = isset($_POST['status']) ? 1 : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        // Validasi input wajib
        if (empty($nama)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Nama supplier wajib diisi';
            return json_encode($resp);
        }

        // Update data
        $stmt = $this->prepare("UPDATE supplier SET 
            nama=?, alamat=?, kota=?, telpon=?, cperson=?, status=?, date_updated=NOW() 
            WHERE id=?");

        $stmt->bind_param("sssssii", $nama, $alamat, $kota, $telpon, $cperson, $status, $id);

        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['msg'] = 'Data supplier berhasil diperbarui';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal memperbarui data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       HAPUS SUPPLIER
    ========================== */
    function delete_supplier()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $stmt = $this->prepare("DELETE FROM supplier WHERE id=?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['msg'] = 'Data supplier berhasil dihapus';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menghapus data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       GET SINGLE SUPPLIER
    ========================== */
    function get_single_supplier()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = false;
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $stmt = $this->prepare("SELECT * FROM supplier WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $qry = $stmt->get_result();

        if ($qry->num_rows > 0) {
            $resp['status'] = true;
            $resp['data'] = $qry->fetch_assoc();
        } else {
            $resp['status'] = false;
            $resp['msg'] = 'Data tidak ditemukan';
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       SALESMAN
    ========================== */
    function get_data_salesman()
    {
        $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
        $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
        $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
        $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
        $order  = isset($_POST['order'][0]) ? $_POST['order'][0] : ['column' => 0, 'dir' => 'asc'];

        $columns_arr = array("id", "firstname", "nohp1", "sales_profit_percent", "alamat", "status");
        $order_column = isset($columns_arr[$order['column']]) ? $columns_arr[$order['column']] : "id";
        $order_dir = strtolower($order['dir']) === 'desc' ? "DESC" : "ASC";

        // Hitung total data salesman (level_id = 3)
        $stmt = $this->prepare("SELECT COUNT(*) as cnt FROM users WHERE level_id = 3");
        $stmt->execute();
        $totalCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Base query untuk salesman (level_id = 3)
        $sql = "SELECT id, firstname, username, alamat, nohp1, kota, status, date_added, avatar,
                       COALESCE(sales_profit_percent, 100) AS sales_profit_percent
                FROM users WHERE level_id = 3";
        $sql_count = "SELECT COUNT(*) as cnt FROM users WHERE level_id = 3";

        $params = [];
        $types = "";
        $params_count = [];
        $types_count = "";

        // Filter pencarian
        if (!empty($search)) {
            $sql .= " AND (firstname LIKE ? OR username LIKE ? OR nohp1 LIKE ? OR kota LIKE ?)";
            $sql_count .= " AND (firstname LIKE ? OR username LIKE ? OR nohp1 LIKE ? OR kota LIKE ?)";
            $like = "%{$search}%";
            $params = array_fill(0, 4, $like);
            $params_count = array_fill(0, 4, $like);
            $types = str_repeat("s", 4);
            $types_count = str_repeat("s", 4);
        }

        // Tambahkan ORDER BY dan LIMIT
        $sql .= " ORDER BY $order_column $order_dir LIMIT ? OFFSET ?";
        $params[] = $length;
        $params[] = $start;
        $types .= "ii";

        // Eksekusi query
        $stmt = $this->prepare($sql);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // Hitung data setelah filter
        $stmt2 = $this->prepare($sql_count);
        if (!empty($types_count)) {
            $stmt2->bind_param($types_count, ...$params_count);
        }
        $stmt2->execute();
        $recordsFilterCount = $stmt2->get_result()->fetch_assoc()['cnt'];
        $stmt2->close();

        // Susun hasil
        $data = [];
        $i = 1 + $start;
        while ($row = $result->fetch_assoc()) {
            $row['no'] = $i++;
            $data[] = $row;
        }
        $stmt->close();

        return json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $recordsFilterCount,
            'data' => $data
        ]);
    }

    /* =========================
       SIMPAN SALESMAN
    ========================== */
    function save_salesman()
    {
        $upload_dir = '../uploads/';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
        $nohp1 = isset($_POST['nohp1']) ? trim($_POST['nohp1']) : '';
        $kota = isset($_POST['kota']) ? trim($_POST['kota']) : '';
        $status = isset($_POST['status']) ? 1 : 0;
        $default_sales_profit_percent = $this->settings->info('sales_profit_percent');
        if (!is_numeric($default_sales_profit_percent)) {
            $default_sales_profit_percent = 100;
        }
        $sales_profit_percent_raw = isset($_POST['sales_profit_percent'])
            ? str_replace(',', '.', trim((string)$_POST['sales_profit_percent']))
            : (string)max(0, min(100, (float)$default_sales_profit_percent));
        $sales_profit_percent = is_numeric($sales_profit_percent_raw)
            ? (float)$sales_profit_percent_raw
            : NAN;
        $avatar_path = '';

        // Validasi input wajib
        if (empty($username) || empty($firstname) || empty($password)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username, Nama Sales, dan Password wajib diisi';
            return json_encode($resp);
        }

        if (strlen($username) > 20) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username maksimal 20 karakter';
            return json_encode($resp);
        }

        if (!is_finite($sales_profit_percent) || $sales_profit_percent < 0 || $sales_profit_percent > 100) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Persentase profit sales harus berada di antara 0% sampai 100%';
            return json_encode($resp);
        }

        // Cek duplikasi username
        $check_stmt = $this->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username sudah digunakan';
            $check_stmt->close();
            return json_encode($resp);
        }
        $check_stmt->close();

        // Handle upload avatar
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_name = $_FILES['avatar']['name'];
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $file_size = $_FILES['avatar']['size'];
            $file_error = $_FILES['avatar']['error'];

            try {
                $validated_avatar = validate_uploaded_image(
                    $_FILES['avatar'],
                    ['image/jpeg', 'image/png'],
                    2 * 1024 * 1024,
                    5000,
                    5000,
                    12000000
                );
            } catch (RuntimeException $e) {
                $resp['status'] = 'failed';
                $resp['msg'] = $e->getMessage();
                return json_encode($resp);
            }

            // Validasi file
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_extension, $allowed_extensions)) {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.';
                return json_encode($resp);
            }

            $mime = mime_content_type($file_tmp);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true) || getimagesize($file_tmp) === false) {
                $resp['status'] = 'failed';
                $resp['msg'] = 'File gambar tidak valid.';
                return json_encode($resp);
            }

            if ($file_size > 2097152) { // 2MB
                $resp['status'] = 'failed';
                $resp['msg'] = 'Ukuran file terlalu besar. Maksimal 2MB.';
                return json_encode($resp);
            }

            // Generate unique filename
            $new_filename = secure_upload_filename('avatar', $validated_avatar['extension']);
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $destination)) {
                $avatar_path = $new_filename;
            } else {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Gagal mengupload foto profil.';
                return json_encode($resp);
            }
        }

        // Hash password
        $password_bcrypt = password_hash($password, PASSWORD_DEFAULT);

        // Insert data
        if (!empty($avatar_path)) {
            $stmt = $this->prepare("INSERT INTO users 
                (username, password_bcrypt, firstname, alamat, nohp1, kota, avatar, status, sales_profit_percent, level_id, date_added) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 3, NOW())");

            $stmt->bind_param(
                "sssssssid",
                $username,
                $password_bcrypt,
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $avatar_path,
                $status,
                $sales_profit_percent
            );
        } else {
            $stmt = $this->prepare("INSERT INTO users 
                (username, password_bcrypt, firstname, alamat, nohp1, kota, status, sales_profit_percent, level_id, date_added) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 3, NOW())");

            $stmt->bind_param(
                "ssssssid",
                $username,
                $password_bcrypt,
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $status,
                $sales_profit_percent
            );
        }

        $resp = [];
        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['msg'] = 'Data salesman berhasil disimpan';
        } else {
            // Hapus file yang sudah diupload jika insert gagal
            if (!empty($avatar_path)) {
                unlink($upload_dir . $avatar_path);
            }
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menyimpan data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       UPDATE SALESMAN - FIXED
    ========================== */
    function update_salesman()
    {
        $upload_dir = '../uploads/';
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
        $nohp1 = isset($_POST['nohp1']) ? trim($_POST['nohp1']) : '';
        $kota = isset($_POST['kota']) ? trim($_POST['kota']) : '';
        $status = isset($_POST['status']) ? 1 : 0;
        $sales_profit_percent_raw = isset($_POST['sales_profit_percent'])
            ? str_replace(',', '.', trim((string)$_POST['sales_profit_percent']))
            : '100';
        $sales_profit_percent = is_numeric($sales_profit_percent_raw)
            ? (float)$sales_profit_percent_raw
            : NAN;
        $current_avatar = isset($_POST['current_avatar']) ? trim($_POST['current_avatar']) : '';
        $avatar_path = $current_avatar;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        // Validasi input wajib
        if (empty($username) || empty($firstname)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username dan Nama Sales wajib diisi';
            return json_encode($resp);
        }

        if (strlen($username) > 20) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username maksimal 20 karakter';
            return json_encode($resp);
        }

        if (!is_finite($sales_profit_percent) || $sales_profit_percent < 0 || $sales_profit_percent > 100) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Persentase profit sales harus berada di antara 0% sampai 100%';
            return json_encode($resp);
        }

        // Cek duplikasi username
        $check_stmt = $this->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username sudah digunakan';
            $check_stmt->close();
            return json_encode($resp);
        }
        $check_stmt->close();

        // Handle upload avatar baru
        $new_avatar_uploaded = false;
        $current_user_id = 0;
        if (isset($GLOBALS['login_id'])) {
            $current_user_id = $GLOBALS['login_id'];
        }
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_name = $_FILES['avatar']['name'];
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $file_size = $_FILES['avatar']['size'];
            $file_error = $_FILES['avatar']['error'];

            try {
                $validated_avatar = validate_uploaded_image(
                    $_FILES['avatar'],
                    ['image/jpeg', 'image/png'],
                    2 * 1024 * 1024,
                    5000,
                    5000,
                    12000000
                );
            } catch (RuntimeException $e) {
                $resp['status'] = 'failed';
                $resp['msg'] = $e->getMessage();
                return json_encode($resp);
            }

            // Validasi file
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_extension, $allowed_extensions)) {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.';
                return json_encode($resp);
            }

            $mime = mime_content_type($file_tmp);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true) || getimagesize($file_tmp) === false) {
                $resp['status'] = 'failed';
                $resp['msg'] = 'File gambar tidak valid.';
                return json_encode($resp);
            }

            if ($file_size > 2097152) { // 2MB
                $resp['status'] = 'failed';
                $resp['msg'] = 'Ukuran file terlalu besar. Maksimal 2MB.';
                return json_encode($resp);
            }

            // Generate unique filename
            $new_filename = secure_upload_filename('avatar', $validated_avatar['extension']);
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $destination)) {
                // Hapus avatar lama jika ada
                if (!empty($current_avatar) && $current_avatar != 'default_avatar.png') {
                    $old_file = $upload_dir . basename(str_replace('\\', '/', $current_avatar));
                    if (file_exists($old_file) && is_file($old_file)) {
                        unlink($old_file);
                    }
                }
                $avatar_path = $new_filename;
                $new_avatar_uploaded = true;
            } else {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Gagal mengupload foto profil.';
                return json_encode($resp);
            }
        }

        // Update data (dengan atau tanpa password)
        if (!empty($password)) {
            $password_bcrypt = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->prepare("UPDATE users SET 
                username=?, password_bcrypt=?, firstname=?, alamat=?, nohp1=?, kota=?, avatar=?, sales_profit_percent=?, status=?, date_updated=NOW() 
                WHERE id=? AND level_id=3");

            $stmt->bind_param(
                "sssssssdii",
                $username,
                $password_bcrypt,
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $avatar_path,
                $sales_profit_percent,
                $status,
                $id
            );
        } else {
            $stmt = $this->prepare("UPDATE users SET 
                username=?, firstname=?, alamat=?, nohp1=?, kota=?, avatar=?, sales_profit_percent=?, status=?, date_updated=NOW() 
                WHERE id=? AND level_id=3");

            $stmt->bind_param(
                "ssssssdii",
                $username,
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $avatar_path,
                $sales_profit_percent,
                $status,
                $id
            );
        }

        if ($stmt->execute()) {
            // Cek jika $_settings tersedia (opsional - tidak wajib)
            if (isset($GLOBALS['_settings']) && is_object($GLOBALS['_settings'])) {
                $current_user_id = $GLOBALS['_settings']->userdata('id') ?? 0;

                // Jika user yang diupdate adalah user yang sedang login, update session
                if ($id == $current_user_id) {
                    $GLOBALS['_settings']->set_userdata('avatar', $avatar_path);
                    $GLOBALS['_settings']->set_userdata('firstname', $firstname);
                    $GLOBALS['_settings']->set_userdata('username', $username);
                }
            }

            $resp['status'] = 'success';
            $resp['msg'] = 'Data salesman berhasil diperbarui';

            // Tambahkan data untuk frontend update real-time
            if ($new_avatar_uploaded) {
                $resp['avatar_updated'] = true;
                $resp['new_avatar'] = $avatar_path;
                $resp['avatar_url'] = base_url . 'uploads/' . $avatar_path;
                $resp['user_updated'] = $id;
                $resp['is_current_user'] = ($id == $current_user_id);
            }
        } else {
            // Hapus file yang baru diupload jika update gagal
            if ($new_avatar_uploaded && !empty($avatar_path) && $avatar_path !== $current_avatar) {
                $new_file = $upload_dir . $avatar_path;
                if (file_exists($new_file) && is_file($new_file)) {
                    unlink($new_file);
                }
            }
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal memperbarui data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       HAPUS SALESMAN
    ========================== */
    function delete_salesman()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        // Ambil data avatar sebelum menghapus
        $avatar_file = null;
        $stmt_select = $this->prepare("SELECT avatar FROM users WHERE id=? AND level_id=3");
        $stmt_select->bind_param("i", $id);
        $stmt_select->execute();
        $stmt_select->bind_result($avatar_file);
        $stmt_select->fetch();
        $stmt_select->close();

        // Hapus data dari database
        $stmt = $this->prepare("DELETE FROM users WHERE id=? AND level_id=3");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Hapus file avatar jika ada dan bukan file default
            if ($avatar_file && !empty($avatar_file) && $avatar_file != 'default_avatar.png') {
                $upload_dir = '../uploads/';
                $file_path = $upload_dir . basename(str_replace('\\', '/', $avatar_file));

                if (file_exists($file_path) && is_file($file_path)) {
                    unlink($file_path); // Tidak perlu cek hasilnya, karena data utama sudah terhapus
                }
            }

            $resp['status'] = 'success';
            $resp['msg'] = 'Data salesman berhasil dihapus';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menghapus data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       GET SINGLE SALESMAN
    ========================== */
    function get_single_salesman()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = false;
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $stmt = $this->prepare("SELECT id, username, firstname, alamat, nohp1, kota, status, avatar,
                                      COALESCE(sales_profit_percent, 100) AS sales_profit_percent
                               FROM users WHERE id=? AND level_id=3");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $qry = $stmt->get_result();

        if ($qry->num_rows > 0) {
            $resp['status'] = true;
            $resp['data'] = $qry->fetch_assoc();
        } else {
            $resp['status'] = false;
            $resp['msg'] = 'Data tidak ditemukan';
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       KARYAWAN - ADMIN
    ========================== */
    function get_data_admin()
    {
        $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
        $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
        $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
        $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
        $order  = isset($_POST['order'][0]) ? $_POST['order'][0] : ['column' => 0, 'dir' => 'asc'];

        $columns_arr = array("id", "firstname", "nohp1", "alamat", "status");
        $order_column = isset($columns_arr[$order['column']]) ? $columns_arr[$order['column']] : "id";
        $order_dir = strtolower($order['dir']) === 'desc' ? "DESC" : "ASC";

        // Hitung total data admin (LEVEL_ID = 1)
        $stmt = $this->prepare("SELECT COUNT(*) as cnt FROM users WHERE level_id = 1");
        $stmt->execute();
        $totalCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Base query untuk admin (LEVEL_ID = 1)
        $sql = "SELECT id, firstname, username, alamat, nohp1, kota, status, date_added,avatar FROM users WHERE level_id = 1";
        $sql_count = "SELECT COUNT(*) as cnt FROM users WHERE level_id = 1";

        $params = [];
        $types = "";
        $params_count = [];
        $types_count = "";

        // Filter pencarian
        if (!empty($search)) {
            $sql .= " AND (firstname LIKE ? OR username LIKE ? OR nohp1 LIKE ? OR kota LIKE ?)";
            $sql_count .= " AND (firstname LIKE ? OR username LIKE ? OR nohp1 LIKE ? OR kota LIKE ?)";
            $like = "%{$search}%";
            $params = array_fill(0, 4, $like);
            $params_count = array_fill(0, 4, $like);
            $types = str_repeat("s", 4);
            $types_count = str_repeat("s", 4);
        }

        // Tambahkan ORDER BY dan LIMIT
        $sql .= " ORDER BY $order_column $order_dir LIMIT ? OFFSET ?";
        $params[] = $length;
        $params[] = $start;
        $types .= "ii";

        // Eksekusi query
        $stmt = $this->prepare($sql);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // Hitung data setelah filter
        $stmt2 = $this->prepare($sql_count);
        if (!empty($types_count)) {
            $stmt2->bind_param($types_count, ...$params_count);
        }
        $stmt2->execute();
        $recordsFilterCount = $stmt2->get_result()->fetch_assoc()['cnt'];
        $stmt2->close();

        // Susun hasil
        $data = [];
        $i = 1 + $start;
        while ($row = $result->fetch_assoc()) {
            $row['no'] = $i++;
            $data[] = $row;
        }
        $stmt->close();

        return json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $recordsFilterCount,
            'data' => $data
        ]);
    }

    /* =========================
       SIMPAN ADMIN
    ========================== */
    function save_admin()
    {
        $upload_dir = '../uploads/';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
        $nohp1 = isset($_POST['nohp1']) ? trim($_POST['nohp1']) : '';
        $kota = isset($_POST['kota']) ? trim($_POST['kota']) : '';
        $status = isset($_POST['status']) ? 1 : 0;
        $avatar_path = '';

        // Validasi input wajib
        if (empty($username) || empty($firstname) || empty($password)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username, Nama Sales, dan Password wajib diisi';
            return json_encode($resp);
        }

        // Cek duplikasi username
        $check_stmt = $this->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username sudah digunakan';
            $check_stmt->close();
            return json_encode($resp);
        }
        $check_stmt->close();

        // Handle upload avatar
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_name = $_FILES['avatar']['name'];
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $file_size = $_FILES['avatar']['size'];
            $file_error = $_FILES['avatar']['error'];

            try {
                $validated_avatar = validate_uploaded_image(
                    $_FILES['avatar'],
                    ['image/jpeg', 'image/png'],
                    2 * 1024 * 1024,
                    5000,
                    5000,
                    12000000
                );
            } catch (RuntimeException $e) {
                $resp['status'] = 'failed';
                $resp['msg'] = $e->getMessage();
                return json_encode($resp);
            }

            // Validasi file
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_extension, $allowed_extensions)) {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.';
                return json_encode($resp);
            }

            $mime = mime_content_type($file_tmp);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true) || getimagesize($file_tmp) === false) {
                $resp['status'] = 'failed';
                $resp['msg'] = 'File gambar tidak valid.';
                return json_encode($resp);
            }

            if ($file_size > 2097152) { // 2MB
                $resp['status'] = 'failed';
                $resp['msg'] = 'Ukuran file terlalu besar. Maksimal 2MB.';
                return json_encode($resp);
            }

            // Generate unique filename
            $new_filename = secure_upload_filename('avatar', $validated_avatar['extension']);
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $destination)) {
                $avatar_path = $new_filename;
            } else {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Gagal mengupload foto profil.';
                return json_encode($resp);
            }
        }

        // Hash password
        $password_bcrypt = password_hash($password, PASSWORD_DEFAULT);

        // Insert data
        if (!empty($avatar_path)) {
            $stmt = $this->prepare("INSERT INTO users 
                (username, password_bcrypt, firstname, alamat, nohp1, kota, avatar, status, level_id, date_added) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");

            $stmt->bind_param(
                "sssssssi",
                $username,
                $password_bcrypt,
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $avatar_path,
                $status
            );
        } else {
            $stmt = $this->prepare("INSERT INTO users 
                (username, password_bcrypt, firstname, alamat, nohp1, kota, status, level_id, date_added) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())");

            $stmt->bind_param(
                "ssssssi",
                $username,
                $password_bcrypt,
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $status
            );
        }

        $resp = [];
        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['msg'] = 'Data admin berhasil disimpan';
        } else {
            // Hapus file yang sudah diupload jika insert gagal
            if (!empty($avatar_path)) {
                unlink($upload_dir . $avatar_path);
            }
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menyimpan data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       UPDATE ADMIN - FIXED
    ========================== */
    function update_admin()
    {
        $upload_dir = '../uploads/';
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
        $nohp1 = isset($_POST['nohp1']) ? trim($_POST['nohp1']) : '';
        $kota = isset($_POST['kota']) ? trim($_POST['kota']) : '';
        $status = isset($_POST['status']) ? 1 : 0;
        $current_avatar = isset($_POST['current_avatar']) ? trim($_POST['current_avatar']) : '';
        $avatar_path = $current_avatar;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        // Validasi input wajib
        if (empty($username) || empty($firstname)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username dan Nama Sales wajib diisi';
            return json_encode($resp);
        }

        // Cek duplikasi username
        $check_stmt = $this->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username sudah digunakan';
            $check_stmt->close();
            return json_encode($resp);
        }
        $check_stmt->close();

        // Handle upload avatar baru
        $new_avatar_uploaded = false;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_name = $_FILES['avatar']['name'];
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $file_size = $_FILES['avatar']['size'];
            $file_error = $_FILES['avatar']['error'];

            try {
                $validated_avatar = validate_uploaded_image(
                    $_FILES['avatar'],
                    ['image/jpeg', 'image/png'],
                    2 * 1024 * 1024,
                    5000,
                    5000,
                    12000000
                );
            } catch (RuntimeException $e) {
                $resp['status'] = 'failed';
                $resp['msg'] = $e->getMessage();
                return json_encode($resp);
            }

            // Validasi file
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_extension, $allowed_extensions)) {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.';
                return json_encode($resp);
            }

            $mime = mime_content_type($file_tmp);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true) || getimagesize($file_tmp) === false) {
                $resp['status'] = 'failed';
                $resp['msg'] = 'File gambar tidak valid.';
                return json_encode($resp);
            }

            if ($file_size > 2097152) { // 2MB
                $resp['status'] = 'failed';
                $resp['msg'] = 'Ukuran file terlalu besar. Maksimal 2MB.';
                return json_encode($resp);
            }

            // Generate unique filename
            $new_filename = secure_upload_filename('avatar', $validated_avatar['extension']);
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $destination)) {
                // Hapus avatar lama jika ada
                if (!empty($current_avatar) && $current_avatar != 'default_avatar.png') {
                    $old_file = $upload_dir . basename(str_replace('\\', '/', $current_avatar));
                    if (file_exists($old_file) && is_file($old_file)) {
                        unlink($old_file);
                    }
                }
                $avatar_path = $new_filename;
                $new_avatar_uploaded = true;
            } else {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Gagal mengupload foto profil.';
                return json_encode($resp);
            }
        }

        // Update data (dengan atau tanpa password)
        if (!empty($password)) {
            $password_bcrypt = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->prepare("UPDATE users SET 
                username=?, password_bcrypt=?, firstname=?, alamat=?, nohp1=?, kota=?, avatar=?, status=?, date_updated=NOW() 
                WHERE id=? AND level_id=1");

            $stmt->bind_param(
                "sssssssii",
                $username,
                $password_bcrypt,
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $avatar_path,
                $status,
                $id
            );
        } else {
            $stmt = $this->prepare("UPDATE users SET 
                username=?, firstname=?, alamat=?, nohp1=?, kota=?, avatar=?, status=?, date_updated=NOW() 
                WHERE id=? AND level_id=1");

            $stmt->bind_param(
                "ssssssii",
                $username,
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $avatar_path,
                $status,
                $id
            );
        }

        if ($stmt->execute()) {
            // Cek jika $_settings tersedia (opsional - tidak wajib)
            $current_user_id = 0;
            if (isset($GLOBALS['_settings']) && is_object($GLOBALS['_settings'])) {
                $current_user_id = $GLOBALS['_settings']->userdata('id') ?? 0;

                // Jika user yang diupdate adalah user yang sedang login, update session
                if ($id == $current_user_id) {
                    $GLOBALS['_settings']->set_userdata('avatar', $avatar_path);
                    $GLOBALS['_settings']->set_userdata('firstname', $firstname);
                    $GLOBALS['_settings']->set_userdata('username', $username);
                }
            }

            $resp['status'] = 'success';
            $resp['msg'] = 'Data admin berhasil diperbarui';

            // Tambahkan data untuk frontend update real-time
            if ($new_avatar_uploaded) {
                $resp['avatar_updated'] = true;
                $resp['new_avatar'] = $avatar_path;
                $resp['avatar_url'] = base_url . 'uploads/' . $avatar_path;
                $resp['user_updated'] = $id;
                $resp['is_current_user'] = ($id == $current_user_id);
            }
        } else {
            // Hapus file yang baru diupload jika update gagal
            if ($new_avatar_uploaded && !empty($avatar_path) && $avatar_path !== $current_avatar) {
                $new_file = $upload_dir . $avatar_path;
                if (file_exists($new_file) && is_file($new_file)) {
                    unlink($new_file);
                }
            }
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal memperbarui data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       HAPUS ADMIN
    ========================== */
    function delete_admin()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        // Ambil data avatar sebelum menghapus
        $avatar_file = null;
        $stmt_select = $this->prepare("SELECT avatar FROM users WHERE id=? AND level_id=1");
        $stmt_select->bind_param("i", $id);
        $stmt_select->execute();
        $stmt_select->bind_result($avatar_file);
        $stmt_select->fetch();
        $stmt_select->close();

        // Hapus data dari database
        $stmt = $this->prepare("DELETE FROM users WHERE id=? AND level_id=1");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Hapus file avatar jika ada dan bukan file default
            if ($avatar_file && !empty($avatar_file) && $avatar_file != 'default_avatar.png') {
                $upload_dir = '../uploads/';
                $file_path = $upload_dir . basename(str_replace('\\', '/', $avatar_file));

                if (file_exists($file_path) && is_file($file_path)) {
                    unlink($file_path); // Tidak perlu cek hasilnya, karena data utama sudah terhapus
                }
            }

            $resp['status'] = 'success';
            $resp['msg'] = 'Data admin berhasil dihapus';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menghapus data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       GET SINGLE ADMIN
    ========================== */
    function get_single_admin()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = false;
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $stmt = $this->prepare("SELECT id, username, firstname, alamat, nohp1, kota, status,avatar FROM users WHERE id=? AND level_id=1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $qry = $stmt->get_result();

        if ($qry->num_rows > 0) {
            $resp['status'] = true;
            $resp['data'] = $qry->fetch_assoc();
        } else {
            $resp['status'] = false;
            $resp['msg'] = 'Data tidak ditemukan';
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       KARYAWAN - KASIR
    ========================== */
    function get_data_kasir()
    {
        $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
        $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
        $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
        $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
        $order  = isset($_POST['order'][0]) ? $_POST['order'][0] : ['column' => 0, 'dir' => 'asc'];

        $columns_arr = array("id", "firstname", "nohp1", "alamat", "status");
        $order_column = isset($columns_arr[$order['column']]) ? $columns_arr[$order['column']] : "id";
        $order_dir = strtolower($order['dir']) === 'desc' ? "DESC" : "ASC";

        // Hitung total data kasir (LEVEL_ID = 2)
        $stmt = $this->prepare("SELECT COUNT(*) as cnt FROM users WHERE level_id = 2");
        $stmt->execute();
        $totalCount = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Base query untuk kasir (LEVEL_ID = 2)
        $sql = "SELECT id, firstname, username, alamat, nohp1, kota, status, date_added,avatar FROM users WHERE level_id = 2";
        $sql_count = "SELECT COUNT(*) as cnt FROM users WHERE level_id = 2";

        $params = [];
        $types = "";
        $params_count = [];
        $types_count = "";

        // Filter pencarian
        if (!empty($search)) {
            $sql .= " AND (firstname LIKE ? OR username LIKE ? OR nohp1 LIKE ? OR kota LIKE ?)";
            $sql_count .= " AND (firstname LIKE ? OR username LIKE ? OR nohp1 LIKE ? OR kota LIKE ?)";
            $like = "%{$search}%";
            $params = array_fill(0, 4, $like);
            $params_count = array_fill(0, 4, $like);
            $types = str_repeat("s", 4);
            $types_count = str_repeat("s", 4);
        }

        // Tambahkan ORDER BY dan LIMIT
        $sql .= " ORDER BY $order_column $order_dir LIMIT ? OFFSET ?";
        $params[] = $length;
        $params[] = $start;
        $types .= "ii";

        // Eksekusi query
        $stmt = $this->prepare($sql);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        // Hitung data setelah filter
        $stmt2 = $this->prepare($sql_count);
        if (!empty($types_count)) {
            $stmt2->bind_param($types_count, ...$params_count);
        }
        $stmt2->execute();
        $recordsFilterCount = $stmt2->get_result()->fetch_assoc()['cnt'];
        $stmt2->close();

        // Susun hasil
        $data = [];
        $i = 1 + $start;
        while ($row = $result->fetch_assoc()) {
            $row['no'] = $i++;
            $data[] = $row;
        }
        $stmt->close();

        return json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalCount,
            'recordsFiltered' => $recordsFilterCount,
            'data' => $data
        ]);
    }

    /* =========================
       SIMPAN KASIR
    ========================== */
    function save_kasir()
    {
        $upload_dir = '../uploads/';
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
        $nohp1 = isset($_POST['nohp1']) ? trim($_POST['nohp1']) : '';
        $kota = isset($_POST['kota']) ? trim($_POST['kota']) : '';
        $status = isset($_POST['status']) ? 1 : 0;
        $avatar_path = '';

        // Validasi input wajib
        if (empty($username) || empty($firstname) || empty($password)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username, Nama Sales, dan Password wajib diisi';
            return json_encode($resp);
        }

        // Cek duplikasi username
        $check_stmt = $this->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username sudah digunakan';
            $check_stmt->close();
            return json_encode($resp);
        }
        $check_stmt->close();

        // Handle upload avatar
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_name = $_FILES['avatar']['name'];
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $file_size = $_FILES['avatar']['size'];
            $file_error = $_FILES['avatar']['error'];

            try {
                $validated_avatar = validate_uploaded_image(
                    $_FILES['avatar'],
                    ['image/jpeg', 'image/png'],
                    2 * 1024 * 1024,
                    5000,
                    5000,
                    12000000
                );
            } catch (RuntimeException $e) {
                $resp['status'] = 'failed';
                $resp['msg'] = $e->getMessage();
                return json_encode($resp);
            }

            // Validasi file
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_extension, $allowed_extensions)) {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.';
                return json_encode($resp);
            }

            $mime = mime_content_type($file_tmp);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true) || getimagesize($file_tmp) === false) {
                $resp['status'] = 'failed';
                $resp['msg'] = 'File gambar tidak valid.';
                return json_encode($resp);
            }

            if ($file_size > 2097152) { // 2MB
                $resp['status'] = 'failed';
                $resp['msg'] = 'Ukuran file terlalu besar. Maksimal 2MB.';
                return json_encode($resp);
            }

            // Generate unique filename
            $new_filename = secure_upload_filename('avatar', $validated_avatar['extension']);
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $destination)) {
                $avatar_path = $new_filename;
            } else {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Gagal mengupload foto profil.';
                return json_encode($resp);
            }
        }

        // Hash password
        $password_bcrypt = password_hash($password, PASSWORD_DEFAULT);

        // Insert data
        if (!empty($avatar_path)) {
            $stmt = $this->prepare("INSERT INTO users 
                (username, password_bcrypt, firstname, alamat, nohp1, kota, avatar, status, level_id, date_added) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 2, NOW())");

            $stmt->bind_param(
                "sssssssi",
                $username,
                $password_bcrypt,
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $avatar_path,
                $status
            );
        } else {
            $stmt = $this->prepare("INSERT INTO users 
                (username, password_bcrypt, firstname, alamat, nohp1, kota, status, level_id, date_added) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 2, NOW())");

            $stmt->bind_param(
                "ssssssi",
                $username,
                $password_bcrypt,
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $status
            );
        }

        $resp = [];
        if ($stmt->execute()) {
            $resp['status'] = 'success';
            $resp['msg'] = 'Data kasir berhasil disimpan';
        } else {
            // Hapus file yang sudah diupload jika insert gagal
            if (!empty($avatar_path)) {
                unlink($upload_dir . $avatar_path);
            }
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menyimpan data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       UPDATE KASIR - FIXED
    ========================== */
    function update_kasir()
    {
        $upload_dir = '../uploads/';
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $firstname = isset($_POST['firstname']) ? trim($_POST['firstname']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';
        $nohp1 = isset($_POST['nohp1']) ? trim($_POST['nohp1']) : '';
        $kota = isset($_POST['kota']) ? trim($_POST['kota']) : '';
        $status = isset($_POST['status']) ? 1 : 0;
        $current_avatar = isset($_POST['current_avatar']) ? trim($_POST['current_avatar']) : '';
        $avatar_path = $current_avatar;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        // Validasi input wajib
        if (empty($username) || empty($firstname)) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username dan Nama Sales wajib diisi';
            return json_encode($resp);
        }

        // Cek duplikasi username
        $check_stmt = $this->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check_stmt->bind_param("si", $username, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Username sudah digunakan';
            $check_stmt->close();
            return json_encode($resp);
        }
        $check_stmt->close();

        // Handle upload avatar baru
        $new_avatar_uploaded = false;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_name = $_FILES['avatar']['name'];
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $file_size = $_FILES['avatar']['size'];
            $file_error = $_FILES['avatar']['error'];

            try {
                $validated_avatar = validate_uploaded_image(
                    $_FILES['avatar'],
                    ['image/jpeg', 'image/png'],
                    2 * 1024 * 1024,
                    5000,
                    5000,
                    12000000
                );
            } catch (RuntimeException $e) {
                $resp['status'] = 'failed';
                $resp['msg'] = $e->getMessage();
                return json_encode($resp);
            }

            // Validasi file
            $allowed_extensions = ['jpg', 'jpeg', 'png'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_extension, $allowed_extensions)) {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.';
                return json_encode($resp);
            }

            $mime = mime_content_type($file_tmp);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true) || getimagesize($file_tmp) === false) {
                $resp['status'] = 'failed';
                $resp['msg'] = 'File gambar tidak valid.';
                return json_encode($resp);
            }

            if ($file_size > 2097152) { // 2MB
                $resp['status'] = 'failed';
                $resp['msg'] = 'Ukuran file terlalu besar. Maksimal 2MB.';
                return json_encode($resp);
            }

            // Generate unique filename
            $new_filename = secure_upload_filename('avatar', $validated_avatar['extension']);
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($file_tmp, $destination)) {
                // Hapus avatar lama jika ada
                if (!empty($current_avatar) && $current_avatar != 'default_avatar.png') {
                    $old_file = $upload_dir . basename(str_replace('\\', '/', $current_avatar));
                    if (file_exists($old_file) && is_file($old_file)) {
                        unlink($old_file);
                    }
                }
                $avatar_path = $new_filename;
                $new_avatar_uploaded = true;
            } else {
                $resp['status'] = 'failed';
                $resp['msg'] = 'Gagal mengupload foto profil.';
                return json_encode($resp);
            }
        }

        // Update data (dengan atau tanpa password)
        if (!empty($password)) {
            $password_bcrypt = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->prepare("UPDATE users SET 
                username=?, password_bcrypt=?, firstname=?, alamat=?, nohp1=?, kota=?, avatar=?, status=?, date_updated=NOW() 
                WHERE id=? AND level_id=2");

            $stmt->bind_param(
                "sssssssii",
                $username,
                $password_bcrypt,
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $avatar_path,
                $status,
                $id
            );
        } else {
            $stmt = $this->prepare("UPDATE users SET 
                username=?, firstname=?, alamat=?, nohp1=?, kota=?, avatar=?, status=?, date_updated=NOW() 
                WHERE id=? AND level_id=2");

            $stmt->bind_param(
                "ssssssii",
                $username,
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $avatar_path,
                $status,
                $id
            );
        }

        if ($stmt->execute()) {
            // Cek jika $_settings tersedia (opsional - tidak wajib)
            $current_user_id = 0;
            if (isset($GLOBALS['_settings']) && is_object($GLOBALS['_settings'])) {
                $current_user_id = $GLOBALS['_settings']->userdata('id') ?? 0;

                // Jika user yang diupdate adalah user yang sedang login, update session
                if ($id == $current_user_id) {
                    $GLOBALS['_settings']->set_userdata('avatar', $avatar_path);
                    $GLOBALS['_settings']->set_userdata('firstname', $firstname);
                    $GLOBALS['_settings']->set_userdata('username', $username);
                }
            }

            $resp['status'] = 'success';
            $resp['msg'] = 'Data kasir berhasil diperbarui';

            // Tambahkan data untuk frontend update real-time
            if ($new_avatar_uploaded) {
                $resp['avatar_updated'] = true;
                $resp['new_avatar'] = $avatar_path;
                $resp['avatar_url'] = base_url . 'uploads/' . $avatar_path;
                $resp['user_updated'] = $id;
                $resp['is_current_user'] = ($id == $current_user_id);
            }
        } else {
            // Hapus file yang baru diupload jika update gagal
            if ($new_avatar_uploaded && !empty($avatar_path) && $avatar_path !== $current_avatar) {
                $new_file = $upload_dir . $avatar_path;
                if (file_exists($new_file) && is_file($new_file)) {
                    unlink($new_file);
                }
            }
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal memperbarui data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       HAPUS KASIR
    ========================== */
    function delete_kasir()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        // Ambil data avatar sebelum menghapus
        $avatar_file = null;
        $stmt_select = $this->prepare("SELECT avatar FROM users WHERE id=? AND level_id=2");
        $stmt_select->bind_param("i", $id);
        $stmt_select->execute();
        $stmt_select->bind_result($avatar_file);
        $stmt_select->fetch();
        $stmt_select->close();

        // Hapus data dari database
        $stmt = $this->prepare("DELETE FROM users WHERE id=? AND level_id=2");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            // Hapus file avatar jika ada dan bukan file default
            if ($avatar_file && !empty($avatar_file) && $avatar_file != 'default_avatar.png') {
                $upload_dir = '../uploads/';
                $file_path = $upload_dir . basename(str_replace('\\', '/', $avatar_file));

                if (file_exists($file_path) && is_file($file_path)) {
                    unlink($file_path); // Tidak perlu cek hasilnya, karena data utama sudah terhapus
                }
            }

            $resp['status'] = 'success';
            $resp['msg'] = 'Data kasir berhasil dihapus';
        } else {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Gagal menghapus data: ' . $stmt->error;
        }
        $stmt->close();
        return json_encode($resp);
    }

    /* =========================
       GET SINGLE KASIR
    ========================== */
    function get_single_kasir()
    {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        $resp = [];
        if ($id <= 0) {
            $resp['status'] = false;
            $resp['msg'] = 'ID tidak valid';
            return json_encode($resp);
        }

        $stmt = $this->prepare("SELECT id, username, firstname, alamat, nohp1, kota, status,avatar FROM users WHERE id=? AND level_id=2");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $qry = $stmt->get_result();

        if ($qry->num_rows > 0) {
            $resp['status'] = true;
            $resp['data'] = $qry->fetch_assoc();
        } else {
            $resp['status'] = false;
            $resp['msg'] = 'Data tidak ditemukan';
        }
        $stmt->close();
        return json_encode($resp);
    }

    // ========== USER MANAGEMENT FUNCTIONS ==========

    public function get_users_datatable()
    {
        $draw = (int)($_POST['draw'] ?? 1);
        $start = (int)($_POST['start'] ?? 0);
        $length = (int)($_POST['length'] ?? 10);
        $search = $_POST['search']['value'] ?? '';

        $search_sql = "";
        if (!empty($search)) {
            $search_sql = " AND (username LIKE ? OR firstname LIKE ? OR nohp1 LIKE ?)";
        }

        // Count total
        $count_sql = "SELECT COUNT(*) as cnt FROM users WHERE 1=1";
        $stmt = $this->prepare($count_sql);
        $stmt->execute();
        $totalRecords = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Count filtered
        $count_sql = "SELECT COUNT(*) as cnt FROM users WHERE 1=1 {$search_sql}";
        $stmt = $this->prepare($count_sql);

        if (!empty($search)) {
            $like = "%{$search}%";
            $stmt->bind_param("sss", $like, $like, $like);
        }
        $stmt->execute();
        $recordsFiltered = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Get data
        $sql = "SELECT id, username, firstname, nohp1, kota, status, level_id, date_added 
                FROM users 
                WHERE 1=1 {$search_sql}
                ORDER BY date_added DESC 
                LIMIT ?, ?";

        $stmt = $this->prepare($sql);
        if (!empty($search)) {
            $like = "%{$search}%";
            $stmt->bind_param("sssii", $like, $like, $like, $start, $length);
        } else {
            $stmt->bind_param("ii", $start, $length);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        $no = $start + 1;
        $level_map = [1 => 'Admin', 2 => 'Kasir', 3 => 'Salesman', 4 => 'Supervisor'];

        while ($row = $result->fetch_assoc()) {
            $status_badge = $row['status'] == 1
                ? '<span class="badge badge-light-success">Aktif</span>'
                : '<span class="badge badge-light-danger">Non-Aktif</span>';

            $actions = '<a href="javascript:void(0)" onclick="edit_user(' . $row['id'] . ')" class="btn btn-icon btn-active-light-primary btn-sm me-1"><i class="bi bi-pencil fs-3"></i></a>
                        <a href="javascript:void(0)" onclick="change_password(' . $row['id'] . ', \'' . addslashes($row['username']) . '\')" class="btn btn-icon btn-active-light-warning btn-sm me-1"><i class="bi bi-key fs-3"></i></a>
                        <a href="javascript:void(0)" onclick="delete_user(' . $row['id'] . ', \'' . addslashes($row['username']) . '\')" class="btn btn-icon btn-active-light-danger btn-sm"><i class="bi bi-trash fs-3"></i></a>';

            $data[] = [
                'no' => $no++,
                'username' => htmlspecialchars($row['username']),
                'firstname' => htmlspecialchars($row['firstname']),
                'nohp1' => htmlspecialchars($row['nohp1'] ?? ''),
                'level_name' => $level_map[$row['level_id']] ?? 'Unknown',
                'status_badge' => $status_badge,
                'actions' => $actions
            ];
        }
        $stmt->close();

        return json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data
        ]);
    }

    public function get_users_by_level()
    {
        $draw = (int)($_POST['draw'] ?? 1);
        $start = (int)($_POST['start'] ?? 0);
        $length = (int)($_POST['length'] ?? 10);
        $search = $_POST['search']['value'] ?? '';
        $level_id = (int)($_POST['level_id'] ?? 1);

        $search_sql = "";
        if (!empty($search)) {
            $search_sql = " AND (username LIKE ? OR firstname LIKE ? OR nohp1 LIKE ?)";
        }

        // Count total by level
        $count_sql = "SELECT COUNT(*) as cnt FROM users WHERE level_id = ?";
        $stmt = $this->prepare($count_sql);
        $stmt->bind_param("i", $level_id);
        $stmt->execute();
        $totalRecords = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Count filtered
        $count_sql = "SELECT COUNT(*) as cnt FROM users WHERE level_id = ? {$search_sql}";
        $stmt = $this->prepare($count_sql);

        if (!empty($search)) {
            $like = "%{$search}%";
            $stmt->bind_param("isss", $level_id, $like, $like, $like);
        } else {
            $stmt->bind_param("i", $level_id);
        }
        $stmt->execute();
        $recordsFiltered = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        // Get data
        $sql = "SELECT id, username, firstname, nohp1, kota, status, level_id, date_added 
                FROM users 
                WHERE level_id = ? {$search_sql}
                ORDER BY date_added DESC 
                LIMIT ?, ?";

        $stmt = $this->prepare($sql);
        if (!empty($search)) {
            $like = "%{$search}%";
            $stmt->bind_param("isssii", $level_id, $like, $like, $like, $start, $length);
        } else {
            $stmt->bind_param("iii", $level_id, $start, $length);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $data = [];
        $no = $start + 1;

        while ($row = $result->fetch_assoc()) {
            $status_badge = $row['status'] == 1
                ? '<span class="badge badge-light-success">Aktif</span>'
                : '<span class="badge badge-light-danger">Non-Aktif</span>';

            $actions = '<a href="javascript:void(0)" onclick="edit_user(' . $row['id'] . ')" class="btn btn-icon btn-active-light-primary btn-sm me-1"><i class="bi bi-pencil fs-3"></i></a>
                        <a href="javascript:void(0)" onclick="change_password(' . $row['id'] . ', \'' . addslashes($row['username']) . '\')" class="btn btn-icon btn-active-light-warning btn-sm me-1"><i class="bi bi-key fs-3"></i></a>
                        <a href="javascript:void(0)" onclick="delete_user(' . $row['id'] . ', \'' . addslashes($row['username']) . '\')" class="btn btn-icon btn-active-light-danger btn-sm"><i class="bi bi-trash fs-3"></i></a>';

            $data[] = [
                'no' => $no++,
                'username' => htmlspecialchars($row['username']),
                'firstname' => htmlspecialchars($row['firstname']),
                'nohp1' => htmlspecialchars($row['nohp1'] ?? ''),
                'status_badge' => $status_badge,
                'actions' => $actions
            ];
        }
        $stmt->close();

        return json_encode([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data
        ]);
    }

    public function save_user()
    {
        try {
            $resp = ['status' => 'failed', 'msg' => ''];

            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $cpassword = trim($_POST['cpassword'] ?? '');
            $firstname = trim($_POST['firstname'] ?? '');
            $nohp1 = trim($_POST['nohp1'] ?? '');
            $kota = trim($_POST['kota'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $level_id = (int)($_POST['level_id'] ?? 3);
            $status = (int)($_POST['status'] ?? 1);

            if (empty($username) || empty($password) || empty($firstname)) {
                $resp['msg'] = 'Username, password, dan nama harus diisi';
                return json_encode($resp);
            }

            if (strlen($password) < 6) {
                $resp['msg'] = 'Password minimal 6 karakter';
                return json_encode($resp);
            }

            if ($password !== $cpassword) {
                $resp['msg'] = 'Konfirmasi password tidak cocok';
                return json_encode($resp);
            }

            // Check if username exists
            $stmt = $this->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $resp['msg'] = 'Username sudah digunakan';
                $stmt->close();
                return json_encode($resp);
            }
            $stmt->close();

            $password_bcrypt = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $this->prepare("INSERT INTO users 
                (username, password_bcrypt, firstname, alamat, nohp1, kota, level_id, status, date_added) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");

            $stmt->bind_param(
                "ssssssii",
                $username,
                $password_bcrypt,
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $level_id,
                $status
            );

            if ($stmt->execute()) {
                $resp['status'] = 'success';
                $resp['msg'] = 'User berhasil ditambahkan';
            } else {
                $resp['msg'] = 'Gagal menambah user: ' . $stmt->error;
            }
            $stmt->close();

            return json_encode($resp);
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
    }

    public function get_user()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);

            $stmt = $this->prepare("SELECT id, username, firstname, alamat, nohp1, kota, level_id, status FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                return json_encode($result->fetch_assoc());
            } else {
                return json_encode(['error' => 'User tidak ditemukan']);
            }
        } catch (Exception $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    public function update_user()
    {
        try {
            $resp = ['status' => 'failed', 'msg' => ''];

            $id = (int)($_POST['id'] ?? 0);
            $firstname = trim($_POST['firstname'] ?? '');
            $nohp1 = trim($_POST['nohp1'] ?? '');
            $kota = trim($_POST['kota'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $level_id = (int)($_POST['level_id'] ?? 3);
            $status = (int)($_POST['status'] ?? 1);

            if (empty($firstname)) {
                $resp['msg'] = 'Nama harus diisi';
                return json_encode($resp);
            }

            $stmt = $this->prepare("UPDATE users SET 
                firstname = ?, alamat = ?, nohp1 = ?, kota = ?, level_id = ?, status = ?, date_updated = NOW() 
                WHERE id = ?");

            $stmt->bind_param(
                "ssssiii",
                $firstname,
                $alamat,
                $nohp1,
                $kota,
                $level_id,
                $status,
                $id
            );

            if ($stmt->execute()) {
                $resp['status'] = 'success';
                $resp['msg'] = 'User berhasil diperbarui';
            } else {
                $resp['msg'] = 'Gagal memperbarui user: ' . $stmt->error;
            }
            $stmt->close();

            return json_encode($resp);
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
    }

    public function delete_user()
    {
        try {
            $resp = ['status' => 'failed', 'msg' => ''];

            $id = (int)($_POST['id'] ?? 0);

            if ($id == $_SESSION['login_id']) {
                $resp['msg'] = 'Tidak dapat menghapus user yang sedang login';
                return json_encode($resp);
            }

            $stmt = $this->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute()) {
                $resp['status'] = 'success';
                $resp['msg'] = 'User berhasil dihapus';
            } else {
                $resp['msg'] = 'Gagal menghapus user: ' . $stmt->error;
            }
            $stmt->close();

            return json_encode($resp);
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
    }

    public function change_password()
    {
        try {
            $resp = ['status' => 'failed', 'msg' => ''];

            $id = (int)($_POST['id'] ?? 0);
            $password = trim($_POST['password'] ?? '');
            $cpassword = trim($_POST['cpassword'] ?? '');

            if (empty($password)) {
                $resp['msg'] = 'Password harus diisi';
                return json_encode($resp);
            }

            if (strlen($password) < 6) {
                $resp['msg'] = 'Password minimal 6 karakter';
                return json_encode($resp);
            }

            if ($password !== $cpassword) {
                $resp['msg'] = 'Konfirmasi password tidak cocok';
                return json_encode($resp);
            }

            $password_bcrypt = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $this->prepare("UPDATE users SET password_bcrypt = ?, date_updated = NOW() WHERE id = ?");
            $stmt->bind_param("si", $password_bcrypt, $id);

            if ($stmt->execute()) {
                $resp['status'] = 'success';
                $resp['msg'] = 'Password berhasil diubah';
            } else {
                $resp['msg'] = 'Gagal mengubah password: ' . $stmt->error;
            }
            $stmt->close();

            return json_encode($resp);
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
    }

    /* =========================
       ACTIVITY LOG FUNCTIONS
    ========================== */

    private function activityActionCategory($aksi)
    {
        $aksi = strtoupper(trim((string)$aksi));

        if ($aksi === 'LOGIN' || $aksi === 'LOGOUT' || $aksi === 'INSERT' || $aksi === 'UPDATE' || $aksi === 'DELETE') {
            return $aksi;
        }

        if (str_starts_with($aksi, 'OTORISASI QTY MINUS')) {
            return 'MINUS_AUTH';
        }

        if ($aksi === '') {
            return 'OTHER';
        }

        if (str_starts_with($aksi, 'TAMBAH') || str_starts_with($aksi, 'BUAT') || str_contains($aksi, ' BARU') || str_contains($aksi, 'TRANSAKSI KASIR')) {
            return 'INSERT';
        }

        if (str_starts_with($aksi, 'UPDATE') || str_starts_with($aksi, 'EDIT') || str_starts_with($aksi, 'UBAH')) {
            return 'UPDATE';
        }

        if (str_starts_with($aksi, 'DELETE') || str_starts_with($aksi, 'HAPUS') || str_starts_with($aksi, 'VOID') || str_starts_with($aksi, 'BATAL')) {
            return 'DELETE';
        }

        return 'OTHER';
    }

    private function activityActionLabel($aksi)
    {
        $aksi = trim((string)$aksi);
        $category = $this->activityActionCategory($aksi);

        if ($aksi !== '' && !in_array(strtoupper($aksi), ['INSERT', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'OTHER'], true)) {
            return $aksi;
        }

        return [
            'INSERT' => 'Tambah Data',
            'UPDATE' => 'Edit Data',
            'DELETE' => 'Hapus Data',
            'LOGIN' => 'Login',
            'LOGOUT' => 'Logout',
            'MINUS_AUTH' => 'Otorisasi Qty Minus',
            'OTHER' => 'Aktivitas'
        ][$category] ?? 'Aktivitas';
    }

    private function activityActionColor($aksi)
    {
        return [
            'INSERT' => 'success',
            'UPDATE' => 'warning',
            'DELETE' => 'danger',
            'LOGIN' => 'primary',
            'LOGOUT' => 'dark',
            'MINUS_AUTH' => 'danger',
            'OTHER' => 'secondary'
        ][$this->activityActionCategory($aksi)] ?? 'secondary';
    }

    private function activityActionCategorySql()
    {
        return "CASE
                    WHEN UPPER(TRIM(l.aksi)) = 'LOGIN' THEN 'LOGIN'
                    WHEN UPPER(TRIM(l.aksi)) = 'LOGOUT' THEN 'LOGOUT'
                    WHEN UPPER(TRIM(l.aksi)) LIKE 'OTORISASI QTY MINUS%' THEN 'MINUS_AUTH'
                    WHEN UPPER(TRIM(l.aksi)) = 'INSERT'
                         OR UPPER(l.aksi) LIKE 'TAMBAH%'
                         OR UPPER(l.aksi) LIKE 'BUAT%'
                         OR UPPER(l.aksi) LIKE '% BARU%'
                         OR UPPER(l.aksi) LIKE '%TRANSAKSI KASIR%' THEN 'INSERT'
                    WHEN UPPER(TRIM(l.aksi)) = 'UPDATE'
                         OR UPPER(l.aksi) LIKE 'UPDATE%'
                         OR UPPER(l.aksi) LIKE 'EDIT%'
                         OR UPPER(l.aksi) LIKE 'UBAH%' THEN 'UPDATE'
                    WHEN UPPER(TRIM(l.aksi)) = 'DELETE'
                         OR UPPER(l.aksi) LIKE 'DELETE%'
                         OR UPPER(l.aksi) LIKE 'HAPUS%'
                         OR UPPER(l.aksi) LIKE 'VOID%'
                         OR UPPER(l.aksi) LIKE 'BATAL%' THEN 'DELETE'
                    ELSE 'OTHER'
                END";
    }

    public function get_activity_logs()
    {
        try {
            $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
            $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
            $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;
            $search = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';
            $date_from = isset($_POST['date_from']) ? $_POST['date_from'] : '';
            $date_to = isset($_POST['date_to']) ? $_POST['date_to'] : '';
            $aksi = strtoupper(trim((string)($_POST['aksi'] ?? '')));
            $categorySql = $this->activityActionCategorySql();

            $where = ["1=1"];
            $params = [];
            $types = "";

            if (!empty($date_from)) {
                $date_from = date('Y-m-d', strtotime($date_from));
                $where[] = "DATE(l.created_at) >= ?";
                $params[] = $date_from;
                $types .= "s";
            }

            if (!empty($date_to)) {
                $date_to = date('Y-m-d', strtotime($date_to));
                $where[] = "DATE(l.created_at) <= ?";
                $params[] = $date_to;
                $types .= "s";
            }

            if (!empty($aksi)) {
                $where[] = "({$categorySql}) = ?";
                $params[] = $aksi;
                $types .= "s";
            }

            if (!empty($search)) {
                $searchTerm = '%' . $search . '%';
                $where[] = "(u.firstname LIKE ? OR u.username LIKE ? OR ul.level_nama LIKE ? OR l.aksi LIKE ? OR l.tabel LIKE ? OR l.keterangan LIKE ? OR l.ip_address LIKE ?)";
                for ($i = 0; $i < 7; $i++) {
                    $params[] = $searchTerm;
                    $types .= "s";
                }
            }

            $whereSql = implode(" AND ", $where);

            $total_result = $this->query("SELECT COUNT(*) as cnt FROM log_aktivitas");
            $total_records = (int)($total_result->fetch_assoc()['cnt'] ?? 0);

            $countSql = "SELECT COUNT(*) as cnt
                         FROM log_aktivitas l
                         LEFT JOIN users u ON l.user_id = u.id
                         LEFT JOIN user_level ul ON u.level_id = ul.level_id
                         WHERE {$whereSql}";
            $countStmt = $this->prepare($countSql);
            if ($types !== "") {
                $countStmt->bind_param($types, ...$params);
            }
            $countStmt->execute();
            $total_filtered = (int)($countStmt->get_result()->fetch_assoc()['cnt'] ?? 0);
            $countStmt->close();

            $query = "SELECT l.*,
                             COALESCE(u.firstname, CONCAT('User #', l.user_id)) as firstname,
                             COALESCE(u.username, '') as username,
                             COALESCE(ul.level_nama, '') as level_nama,
                             {$categorySql} as aksi_category
                      FROM log_aktivitas l
                      LEFT JOIN users u ON l.user_id = u.id
                      LEFT JOIN user_level ul ON u.level_id = ul.level_id
                      WHERE {$whereSql}
                      ORDER BY l.created_at DESC
                      LIMIT ?, ?";

            $dataParams = $params;
            $dataTypes = $types . "ii";
            $dataParams[] = $start;
            $dataParams[] = $length;

            $stmt = $this->prepare($query);
            $stmt->bind_param($dataTypes, ...$dataParams);
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            $i = 1 + $start;
            while ($row = $result->fetch_assoc()) {
                $row['no'] = $i++;
                $row['created_at'] = $row['created_at'] ? date('d/m/Y H:i:s', strtotime($row['created_at'])) : '-';
                $row['aksi_label'] = $this->activityActionLabel($row['aksi']);
                $row['aksi_color'] = $this->activityActionColor($row['aksi']);
                $row['aksi_badge'] = '<span class="badge bg-' . $row['aksi_color'] . '">' . htmlspecialchars($row['aksi_label'], ENT_QUOTES, 'UTF-8') . '</span>';
                $row['firstname'] = trim($row['firstname'] . (!empty($row['level_nama']) ? ' (' . $row['level_nama'] . ')' : ''));
                $row['tabel'] = $row['tabel'] ?: '-';
                $row['record_id'] = $row['record_id'] ?: '-';
                $row['keterangan'] = $row['keterangan'] ?: '-';
                $row['ip_address'] = $row['ip_address'] ?: '-';
                $data[] = $row;
            }
            $stmt->close();

            return json_encode([
                'draw' => $draw,
                'recordsTotal' => $total_records,
                'recordsFiltered' => $total_filtered,
                'data' => $data
            ]);
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
    }

    /* =========================
       REWARD SYSTEM - POINT & PROFILE MATCHING
    ========================== */

    /**
     * Get total poin pelanggan
     */
    function get_customer_points()
    {
        $pelanggan_id = isset($_POST['pelanggan_id']) ? (int)$_POST['pelanggan_id'] : 0;

        if ($pelanggan_id <= 0) {
            return json_encode(['status' => 'failed', 'message' => 'Pelanggan ID tidak valid']);
        }

        require_once __DIR__ . '/RewardProfileMatching.php';
        $reward = new RewardProfileMatching();
        $points = $reward->getCustomerPoints($pelanggan_id);

        return json_encode([
            'status' => 'success',
            'pelanggan_id' => $pelanggan_id,
            'total_points' => $points,
            'message' => 'Berhasil mengambil data poin'
        ]);
    }

    /**
     * Get reward score pelanggan
     */
    function get_customer_reward_score()
    {
        $pelanggan_id = isset($_POST['pelanggan_id']) ? (int)$_POST['pelanggan_id'] : 0;
        $month = isset($_POST['month']) ? (int)$_POST['month'] : date('m');
        $year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');

        if ($pelanggan_id <= 0) {
            return json_encode(['status' => 'failed', 'message' => 'Pelanggan ID tidak valid']);
        }

        require_once __DIR__ . '/RewardProfileMatching.php';
        $reward = new RewardProfileMatching();
        $score = $reward->getCustomerScore($pelanggan_id, $month, $year);
        $points = $reward->getCustomerPoints($pelanggan_id);

        if (!$score) {
            return json_encode(['status' => 'no_data', 'message' => 'Belum ada scoring untuk periode ini']);
        }

        return json_encode([
            'status' => 'success',
            'pelanggan_id' => $pelanggan_id,
            'score' => $score,
            'total_points' => $points
        ]);
    }

    /**
     * Hitung reward scores untuk semua pelanggan
     */
    function calculate_reward_scores()
    {
        $user_type = $this->settings->set_userdata('user_type') ?? 99;
        if ($user_type > 2) {
            return json_encode(['status' => 'failed', 'message' => 'Anda tidak memiliki akses']);
        }

        $month = isset($_POST['month']) ? (int)$_POST['month'] : date('m');
        $year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');

        require_once __DIR__ . '/RewardProfileMatching.php';
        $reward = new RewardProfileMatching();
        $result = $reward->calculateAllCustomerScores($month, $year);

        return json_encode($result);
    }

    /**
     * Get list pelanggan yang ELIGIBLE untuk reward
     */
    function get_eligible_customers()
    {
        $month = isset($_POST['month']) ? (int)$_POST['month'] : date('m');
        $year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;

        require_once __DIR__ . '/RewardProfileMatching.php';
        $reward = new RewardProfileMatching();
        $customers = $reward->getEligibleCustomers($month, $year, $limit);

        return json_encode([
            'status' => 'success',
            'month' => $month,
            'year' => $year,
            'total_eligible' => count($customers),
            'customers' => $customers
        ]);
    }

    /**
     * Get ranking pelanggan berdasarkan reward score
     */
    function get_customer_ranking()
    {
        $month = isset($_POST['month']) ? (int)$_POST['month'] : date('m');
        $year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y');
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;

        require_once __DIR__ . '/RewardProfileMatching.php';
        $reward = new RewardProfileMatching();
        $ranking = $reward->getCustomerRanking($month, $year, $limit);

        return json_encode([
            'status' => 'success',
            'month' => $month,
            'year' => $year,
            'total_ranking' => count($ranking),
            'ranking' => $ranking
        ]);
    }

    /**
     * Reset poin pelanggan (berikan reward)
     */
    function reset_customer_points()
    {
        $user_type = $this->settings->set_userdata('user_type') ?? 99;
        if ($user_type > 2) {
            return json_encode(['status' => 'failed', 'message' => 'Anda tidak memiliki akses']);
        }

        $pelanggan_id = isset($_POST['pelanggan_id']) ? (int)$_POST['pelanggan_id'] : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'Diberikan reward oleh pemilik';
        $admin_id = $this->settings->set_userdata('id') ?? 0;

        if ($pelanggan_id <= 0) {
            return json_encode(['status' => 'failed', 'message' => 'Pelanggan ID tidak valid']);
        }

        require_once __DIR__ . '/RewardProfileMatching.php';
        $reward = new RewardProfileMatching();
        $score = $reward->getCustomerScore($pelanggan_id);
        if (!$score || $score['eligibility_status'] !== 'ELIGIBLE') {
            return json_encode(['status' => 'failed', 'message' => 'Pelanggan tidak memenuhi syarat eligibility']);
        }

        $result = $reward->resetCustomerPoints($pelanggan_id, $admin_id, $reason);
        return json_encode($result);
    }

    /**
     * Reset semua customer points sekaligus (admin only)
     */
    function reset_all_customer_points()
    {
        $user_type = $this->settings->set_userdata('user_type') ?? 99;
        if ($user_type > 2) {
            return json_encode(['status' => 'failed', 'message' => 'Anda tidak memiliki akses']);
        }

        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'Diberikan reward oleh pemilik';
        $admin_id = $this->settings->set_userdata('id') ?? 0;

        require_once __DIR__ . '/RewardProfileMatching.php';
        $reward = new RewardProfileMatching();

        // Get semua pelanggan yang ELIGIBLE
        $eligible_customers = $reward->getEligibleCustomers(date('m'), date('Y'), 1000);

        if (empty($eligible_customers)) {
            return json_encode(['status' => 'success', 'message' => 'Tidak ada pelanggan yang eligible untuk direset', 'reset_count' => 0]);
        }

        $reset_count = 0;
        $failed_count = 0;

        foreach ($eligible_customers as $customer) {
            $result = $reward->resetCustomerPoints($customer['pelanggan_id'], $admin_id, $reason);
            if (isset($result['status']) && $result['status'] === 'success') {
                $reset_count++;
            } else {
                $failed_count++;
            }
        }

        return json_encode([
            'status' => 'success',
            'message' => "Reset berhasil untuk $reset_count pelanggan",
            'reset_count' => $reset_count,
            'failed_count' => $failed_count
        ]);
    }

    /**
     * Earn points dari transaksi
     */
    function earn_points_from_transaction()
    {
        $pelanggan_id = isset($_POST['pelanggan_id']) ? (int)$_POST['pelanggan_id'] : 0;
        $sale_id = isset($_POST['sale_id']) ? (int)$_POST['sale_id'] : 0;
        $transaction_amount = isset($_POST['transaction_amount']) ? (float)$_POST['transaction_amount'] : 0;

        if ($pelanggan_id <= 0 || $sale_id <= 0 || $transaction_amount <= 0) {
            return json_encode(['status' => 'failed', 'message' => 'Parameter tidak valid']);
        }

        require_once __DIR__ . '/RewardProfileMatching.php';
        $reward = new RewardProfileMatching();
        $result = $reward->earnPointsFromTransaction($pelanggan_id, $sale_id, $transaction_amount);

        return json_encode($result);
    }

    public function get_log_detail()
    {
        try {
            $id = (int)($_POST['id'] ?? 0);

            $stmt = $this->prepare("SELECT l.*, u.firstname, u.username,
                                           COALESCE(ul.level_nama, '') AS level_nama
                                    FROM log_aktivitas l
                                   LEFT JOIN users u ON l.user_id = u.id
                                   LEFT JOIN user_level ul ON u.level_id = ul.level_id
                                   WHERE l.id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if ($row) {
                $row['created_at'] = date('d/m/Y H:i:s', strtotime($row['created_at']));
                $row['aksi_label'] = $this->activityActionLabel($row['aksi']);
                $row['aksi_color'] = $this->activityActionColor($row['aksi']);
                $row['firstname'] = $row['firstname'] ?: 'User #' . (int)$row['user_id'];
                if (!empty($row['username'])) {
                    $row['firstname'] .= ' [' . $row['username'] . ']';
                }
                if (!empty($row['level_nama'])) {
                    $row['firstname'] .= ' (' . $row['level_nama'] . ')';
                }

                return json_encode($row);
            }

            return json_encode(['status' => 'failed', 'msg' => 'Log tidak ditemukan']);
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
    }

    public function clear_all_logs()
    {
        try {
            $resp = ['status' => 'failed', 'msg' => ''];

            if (current_user_level() !== 1) {
                return json_encode(['status' => 'failed', 'msg' => 'Akses ditolak. Hanya admin yang dapat menghapus log.']);
            }

            $stmt = $this->prepare("DELETE FROM log_aktivitas");

            if ($stmt->execute()) {
                $resp['status'] = 'success';
                $resp['msg'] = 'Semua log aktivitas berhasil dihapus';
            } else {
                $resp['msg'] = 'Gagal menghapus log: ' . $stmt->error;
            }
            $stmt->close();

            return json_encode($resp);
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
    }

    private function normalizedSalePriceExpression($detailAlias = 'sd', $productAlias = 'p', $detailTotalAlias = 'dt', $saleAlias = 's')
    {
        return "(CASE
                    WHEN {$saleAlias}.sale_total > 0
                         AND COALESCE({$detailTotalAlias}.detail_total, 0) >= ({$saleAlias}.sale_total * 50)
                        THEN {$detailAlias}.sale_harga / 100
                    WHEN COALESCE({$productAlias}.harga_kasir, 0) > 0
                         AND {$detailAlias}.sale_harga >= ({$productAlias}.harga_kasir * 50)
                        THEN {$detailAlias}.sale_harga / 100
                    ELSE {$detailAlias}.sale_harga
                END)";
    }

    /* =========================
       DASHBOARD FUNCTIONS
    ========================== */

    public function get_dashboard_stats()
    {
        try {
            $today = date('Y-m-d');
            $month_start = date('Y-m-01');
            $month_end = date('Y-m-d');

            // Today's Sales - FIXED: use correct status 'ACTIVE'
            $stmt = $this->prepare("
                SELECT COALESCE(SUM(sale_total), 0) as nilai,
                       COUNT(*) as jumlah_transaksi
                FROM sale 
                WHERE DATE(sale_date) = ? AND sale_status = 'ACTIVE'
            ");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $today_sales = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // This Month's Sales - FIXED: use correct status 'ACTIVE'
            $stmt = $this->prepare("
                SELECT COALESCE(SUM(sale_total), 0) as nilai,
                       COUNT(*) as jumlah_transaksi
                FROM sale 
                WHERE DATE(sale_date) >= ? AND DATE(sale_date) <= ? AND sale_status = 'ACTIVE'
            ");
            $stmt->bind_param("ss", $month_start, $month_end);
            $stmt->execute();
            $month_sales = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Total Products - Fixed Query
            $stmt = $this->prepare("SELECT COUNT(*) as total FROM produk");
            $stmt->execute();
            $total_products = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            // Low Stock Products - Fixed Query
            $stmt = $this->prepare("SELECT COUNT(*) as total FROM produk WHERE stok < 10");
            $stmt->execute();
            $low_stock = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            // Total Customers - Fixed Query (ALL customers)
            $stmt = $this->prepare("SELECT COUNT(*) as total FROM pelanggan");
            $stmt->execute();
            $total_customers = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            // Total member dengan saldo poin aktif pada ledger kanonis.
            $stmt = $this->prepare("
                SELECT COUNT(*) AS total
                FROM (
                    SELECT pelanggan_id
                    FROM customer_points
                    WHERE expired_at IS NULL OR expired_at > NOW() OR points < 0
                    GROUP BY pelanggan_id
                    HAVING SUM(points) > 0
                ) balances
            ");
            $stmt->execute();
            $total_members = $stmt->get_result()->fetch_assoc()['total'];
            $stmt->close();

            $salePriceExpr = $this->normalizedSalePriceExpression('sd', 'p', 'dt', 's');

            // Best Selling Products This Month - Fixed: correct column names and status
            $stmt = $this->prepare("
                SELECT p.id, p.nama_produk, SUM(sd.sale_qty) as total_qty, SUM({$salePriceExpr} * sd.sale_qty) as total_harga
                FROM sale_det sd
                JOIN sale s ON sd.sale_id = s.saleid
                JOIN produk p ON sd.produk_id = p.id
                LEFT JOIN (
                    SELECT sale_id, SUM(sale_qty * sale_harga) as detail_total
                    FROM sale_det
                    GROUP BY sale_id
                ) dt ON dt.sale_id = s.saleid
                WHERE DATE(s.sale_date) >= ? AND DATE(s.sale_date) <= ? AND s.sale_status = 'ACTIVE'
                GROUP BY sd.produk_id, p.id, p.nama_produk
                ORDER BY total_qty DESC
                LIMIT 5
            ");
            $stmt->bind_param("ss", $month_start, $month_end);
            $stmt->execute();
            $result = $stmt->get_result();
            $best_sellers = [];
            while ($row = $result->fetch_assoc()) {
                $best_sellers[] = $row;
            }
            $stmt->close();

            // Daily Sales Data (Last 7 Days) - FIXED: use correct status 'ACTIVE'
            $daily_sales = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $stmt = $this->prepare("
                    SELECT COALESCE(SUM(sale_total), 0) as nilai
                    FROM sale 
                    WHERE DATE(sale_date) = ? AND sale_status = 'ACTIVE'
                ");
                $stmt->bind_param("s", $date);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $daily_sales[] = [
                    'date' => date('d/m', strtotime($date)),
                    'nilai' => (float)$result['nilai']
                ];
                $stmt->close();
            }

            // Ringkasan saldo poin dari customer_points untuk seluruh kanal.
            $customer_points = [];
            $stmt = $this->prepare("
                SELECT 
                    p.id,
                    p.kode,
                    p.nama,
                    GREATEST(SUM(cp.points), 0) as total_points
                FROM customer_points cp
                JOIN pelanggan p ON cp.pelanggan_id = p.id
                WHERE cp.expired_at IS NULL OR cp.expired_at > NOW() OR cp.points < 0
                GROUP BY cp.pelanggan_id, p.id, p.kode, p.nama
                HAVING total_points > 0
                ORDER BY total_points DESC
                LIMIT 10
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $customer_points[] = $row;
            }
            $stmt->close();

            return json_encode([
                'status' => 'success',
                'today_sales' => $today_sales,
                'month_sales' => $month_sales,
                'total_products' => $total_products,
                'low_stock' => $low_stock,
                'total_customers' => $total_customers,
                'total_members' => $total_members,
                'best_sellers' => $best_sellers,
                'daily_sales' => $daily_sales,
                'customer_points' => $customer_points
            ]);
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
    }

    public function get_dashboard_charts()
    {
        try {
            $month_start = date('Y-m-01');
            $month_end = date('Y-m-d');

            // Monthly Sales Chart Data - FIXED: use correct tables and status
            $daily_chart_data = [];
            for ($day = 1; $day <= 31; $day++) {
                $date = date('Y-m') . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                if ($date > date('Y-m-d')) break;

                $stmt = $this->prepare("
                    SELECT COALESCE(SUM(sale_total), 0) as nilai
                    FROM sale 
                    WHERE DATE(sale_date) = ? AND sale_status = 'ACTIVE'
                ");
                $stmt->bind_param("s", $date);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $daily_chart_data[] = (int)$result['nilai'];
                $stmt->close();
            }

            $salePriceExpr = $this->normalizedSalePriceExpression('sd', 'p', 'dt', 's');

            // Sales by Category - FIXED: use correct tables, column names and status
            $stmt = $this->prepare("
                SELECT k.nama_kategori, SUM({$salePriceExpr} * sd.sale_qty) as total_harga
                FROM sale_det sd
                JOIN sale s ON sd.sale_id = s.saleid
                JOIN produk p ON sd.produk_id = p.id
                JOIN kategori k ON p.id_kategori = k.id
                LEFT JOIN (
                    SELECT sale_id, SUM(sale_qty * sale_harga) as detail_total
                    FROM sale_det
                    GROUP BY sale_id
                ) dt ON dt.sale_id = s.saleid
                WHERE DATE(s.sale_date) >= ? AND DATE(s.sale_date) <= ? AND s.sale_status = 'ACTIVE'
                GROUP BY k.id, k.nama_kategori
                ORDER BY total_harga DESC
            ");
            $stmt->bind_param("ss", $month_start, $month_end);
            $stmt->execute();
            $result = $stmt->get_result();
            $category_chart = [];
            while ($row = $result->fetch_assoc()) {
                $category_chart[] = [
                    'label' => $row['nama_kategori'],
                    'nilai' => (float)$row['total_harga']
                ];
            }
            $stmt->close();

            return json_encode([
                'status' => 'success',
                'daily_chart' => $daily_chart_data,
                'category_chart' => $category_chart
            ]);
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Get point settings data untuk ranking detail
     */
    public function get_point_settings_data()
    {
        try {
            $stmt = $this->prepare("SELECT * FROM point_settings ORDER BY setting_key ASC");
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[$row['setting_key']] = $row['setting_value'];
            }
            $stmt->close();

            return json_encode([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Get detail scoring pelanggan dengan info belanja dari transaksi
     */
    public function get_customer_detail_scoring()
    {
        try {
            $pelanggan_id = isset($_POST['pelanggan_id']) ? (int)$_POST['pelanggan_id'] : 0;
            $month = isset($_POST['month']) ? (int)$_POST['month'] : date('m');
            $year = isset($_POST['year']) ? (int)$_POST['year'] : date('Y'); // FIXED: Gunakan tahun dari POST atau tahun sekarang
            $startDate = "{$year}-01-01";
            $endDate = "{$year}-12-31";

            // Get point settings - fallback ke default jika table tidak ada
            $min_belanja = 100000;
            $point_per_belanja = 10000;

            $result = $this->query("SELECT setting_value FROM point_settings WHERE setting_key = 'min_belanja_point' LIMIT 1");
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $min_belanja = (int)$row['setting_value'];
            }

            $result = $this->query("SELECT setting_value FROM point_settings WHERE setting_key = 'point_per_belanja' LIMIT 1");
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $point_per_belanja = (int)$row['setting_value'];
            }

            // Get customer transaction data
            // First, check if customer_reward_scoring has reference data (untuk synthetic/reference data)
            $sql_scoring_check = "SELECT transaction_count, total_transaction_amount FROM customer_reward_scoring WHERE pelanggan_id = {$pelanggan_id} LIMIT 1";
            $result_scoring_check = $this->query($sql_scoring_check);

            $jumlah_transaksi = 0;
            $total_belanja = 0;

            if ($result_scoring_check && $result_scoring_check->num_rows > 0) {
                // Use data from customer_reward_scoring (synthetic atau reference data)
                $scoring_data = $result_scoring_check->fetch_assoc();
                $jumlah_transaksi = (int)$scoring_data['transaction_count'];
                $total_belanja = (float)$scoring_data['total_transaction_amount'];
            } else {
                // Query dari tabel sale (real transaction data)
                $sql_transaction = "
                    SELECT 
                        COUNT(*) as jumlah_transaksi,
                        COALESCE(SUM(sale_total), 0) as total_belanja
                    FROM sale
                    WHERE cust_id = {$pelanggan_id}
                    AND sale_date >= '{$startDate}'
                    AND sale_date <= '{$endDate}'
                    AND sale_status NOT IN ('DELETED', 'VOID')
                ";

                $result_transaction = $this->query($sql_transaction);
                if (!$result_transaction) {
                    return json_encode(['status' => 'failed', 'message' => 'Query failed: unable to fetch transaction data']);
                }

                $transaction_data = $result_transaction->fetch_assoc();
                $jumlah_transaksi = isset($transaction_data['jumlah_transaksi']) ? (int)$transaction_data['jumlah_transaksi'] : 0;
                $total_belanja = isset($transaction_data['total_belanja']) ? (float)$transaction_data['total_belanja'] : 0;
            }

            // Get total poin dari customer_points (ALL, tidak filtered by month/year karena ini cumulative)
            $total_poin = 0;

            $result_poin = $this->query("SELECT COALESCE(SUM(CASE WHEN point_type IN ('EARN', 'ADJUST') THEN points ELSE -points END), 0) as total_poin FROM customer_points WHERE pelanggan_id = {$pelanggan_id}");
            if ($result_poin && $result_poin->num_rows > 0) {
                $row = $result_poin->fetch_assoc();
                $total_poin = (int)$row['total_poin'];
            }

            // Calculate scoring data - using GAP Analysis (1-5 scale)
            // Constants untuk GAP Analysis
            $IDEAL_NOMINAL_AMOUNT = 10000000;      // Rp 10 juta (ideal nominal)
            $IDEAL_FREQUENCY = 10;                  // 10 transaksi (ideal frequency)
            $CORE_FACTOR_WEIGHT = 0.60;            // Bobot Score Nominal
            $SECONDARY_FACTOR_WEIGHT = 0.40;       // Bobot Score Frekuensi
            $MINIMUM_ELIGIBILITY_SCORE = 3.5;      // Threshold minimum (skala 1-5)

            // ===== GAP ANALYSIS METHOD =====
            // Calculate GAP for Nominal (Belanja)
            $nominalGap = ($IDEAL_NOMINAL_AMOUNT > 0)
                ? abs($total_belanja - $IDEAL_NOMINAL_AMOUNT) / $IDEAL_NOMINAL_AMOUNT
                : 0;
            // Convert GAP to score (1-5 scale): 5 - (GAP × 5), min 1
            $nominalScore = max(1, min(5, 5 - ($nominalGap * 5)));

            // Calculate GAP for Frequency
            $frequencyGap = ($IDEAL_FREQUENCY > 0)
                ? abs($jumlah_transaksi - $IDEAL_FREQUENCY) / $IDEAL_FREQUENCY
                : 0;
            // Convert GAP to score (1-5 scale): 5 - (GAP × 5), min 1
            $frequencyScore = max(1, min(5, 5 - ($frequencyGap * 5)));

            // Calculate final score (1-5 scale)
            $finalScore = ($nominalScore * $CORE_FACTOR_WEIGHT) + ($frequencyScore * $SECONDARY_FACTOR_WEIGHT);
            $finalScore = round($finalScore, 2);

            // Determine eligibility (threshold: 3.5 for 1-5 scale)
            $eligibilityStatus = $finalScore >= $MINIMUM_ELIGIBILITY_SCORE ? 'ELIGIBLE' : 'NOT_ELIGIBLE';

            // Response
            return json_encode([
                'status' => 'success',
                'min_belanja_point' => $min_belanja,
                'point_per_belanja' => $point_per_belanja,
                'total_belanja' => (float)$total_belanja,
                'jumlah_transaksi' => (int)$jumlah_transaksi,
                'total_poin' => (int)$total_poin,
                'nominal_score' => round($nominalScore, 2),
                'frequency_score' => round($frequencyScore, 2),
                'final_score' => round($finalScore, 2),
                'eligibility_status' => $eligibilityStatus,
                'period_year' => $year,
                'period_month' => $month
            ]);
        } catch (Exception $e) {
            return json_encode([
                'status' => 'failed',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sinkronkan kembali customer_points dari tabel sale
     * Gunakan untuk fix masalah frekuensi dan total belanja = 0
     */
    public function sync_customer_points_from_sales()
    {
        try {
            $sync_all = isset($_POST['sync_all']) && $_POST['sync_all'] == 1;

            if ($sync_all) {
                // Sinkronkan SEMUA customer
                return $this->performFullSync();
            } else {
                // Sinkronkan customer yang punya poin tapi tidak ada transaksi
                return $this->performPartialSync();
            }
        } catch (Exception $e) {
            return json_encode(['status' => 'failed', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Sinkronisasi PARTIAL: Fix customer yang punya poin tapi tidak ada transaksi di sale
     */
    private function performPartialSync()
    {
        // Step 1: Ambil customer yang memiliki poin tapi tidak ada transaksi di sale
        $sql_orphaned = "
            SELECT DISTINCT cp.pelanggan_id
            FROM customer_points cp
            WHERE NOT EXISTS (
                SELECT 1 FROM sale s 
                WHERE s.cust_id = cp.pelanggan_id 
                AND s.sale_status NOT IN ('DELETED', 'VOID', 'CANCELED')
            )
        ";

        $result_orphaned = $this->query($sql_orphaned);
        if (!$result_orphaned) {
            return json_encode(['status' => 'failed', 'message' => 'Query orphaned customers failed']);
        }

        $orphaned_customers = [];
        while ($row = $result_orphaned->fetch_assoc()) {
            $orphaned_customers[] = $row['pelanggan_id'];
        }

        if (empty($orphaned_customers)) {
            return json_encode([
                'status' => 'success',
                'message' => 'Tidak ada customer yang perlu disinkronkan',
                'sync_type' => 'partial',
                'affected_count' => 0
            ]);
        }

        // Step 2: Untuk setiap orphaned customer, cari transaksi di tabel sale dan rekrebuild poinnya
        $rebuild_count = 0;
        $issue_count = 0;

        foreach ($orphaned_customers as $cust_id) {
            // Cek apakah ada transaksi di sale untuk customer ini
            $sql_check_sales = "
                SELECT COUNT(*) as cnt FROM sale 
                WHERE cust_id = {$cust_id} 
                AND sale_status NOT IN ('DELETED', 'VOID', 'CANCELED')
            ";
            $result_check = $this->query($sql_check_sales);
            $row_check = $result_check->fetch_assoc();

            if ($row_check['cnt'] > 0) {
                // Ada transaksi! Hapus poin lama dan rebuild dari transaksi
                $this->query("DELETE FROM customer_points WHERE pelanggan_id = {$cust_id}");

                // Get semua transaksi dan rebuild poin
                $sql_sales = "
                    SELECT saleid, sale_total, sale_date 
                    FROM sale 
                    WHERE cust_id = {$cust_id}
                    AND sale_status NOT IN ('DELETED', 'VOID', 'CANCELED')
                    ORDER BY sale_date ASC
                ";
                $result_sales = $this->query($sql_sales);

                while ($sale = $result_sales->fetch_assoc()) {
                    $sale_id = (int)$sale['saleid'];
                    $sale_total = (float)$sale['sale_total'];
                    $sale_date = $sale['sale_date'];

                    if ($sale_total >= 100000) {
                        $points = (int)($sale_total / 10000);
                        $expired_at = date('Y-m-d H:i:s', strtotime($sale_date . ' +12 months'));

                        $this->query("
                            INSERT INTO customer_points 
                            (pelanggan_id, sale_id, point_type, points, transaction_amount, description, expired_at, created_at)
                            VALUES ({$cust_id}, {$sale_id}, 'EARN', {$points}, {$sale_total}, 'Sinkronisasi dari transaksi penjualan #{$sale_id}', '{$expired_at}', NOW())
                        ");
                        $rebuild_count++;
                    }
                }
            } else {
                // Tidak ada transaksi! Kemungkinan transaksi sudah dihapus
                // Opsi: Hapus poin yang orphaned atau biarkan saja
                $issue_count++;
            }
        }

        // Recalculate scores untuk customer yang sudah disinkronkan
        require_once __DIR__ . '/RewardProfileMatching.php';
        $reward = new RewardProfileMatching();
        $reward->calculateAllCustomerScores(date('m'), date('Y'));

        return json_encode([
            'status' => 'success',
            'message' => "Sinkronisasi berhasil! {$rebuild_count} poin telah diperbaharui, {$issue_count} customer tanpa transaksi",
            'sync_type' => 'partial',
            'affected_count' => count($orphaned_customers),
            'rebuilt_count' => $rebuild_count,
            'orphaned_count' => $issue_count
        ]);
    }

    /**
     * Sinkronisasi FULL: Rebuild semua customer_points dari tabel sale
     */
    private function performFullSync()
    {
        // WARNING: Ini akan menghapus semua poin dan rebuild dari awal!
        // Step 1: Backup data sebelum delete
        $backup_sql = "CREATE TABLE IF NOT EXISTS customer_points_backup AS SELECT * FROM customer_points WHERE 1=0";
        $this->query("DROP TABLE IF EXISTS customer_points_backup");
        $this->query("CREATE TABLE customer_points_backup LIKE customer_points");
        $this->query("INSERT INTO customer_points_backup SELECT * FROM customer_points");

        // Step 2: Hapus semua poin
        $this->query("DELETE FROM customer_points");

        // Step 3: Rebuild dari tabel sale
        $sql_all_sales = "
            SELECT saleid, cust_id, sale_total, sale_date
            FROM sale
            WHERE sale_status NOT IN ('DELETED', 'VOID', 'CANCELED')
            ORDER BY sale_date ASC
        ";

        $result_all_sales = $this->query($sql_all_sales);
        $total_rebuilt = 0;
        $total_sales = 0;

        while ($sale = $result_all_sales->fetch_assoc()) {
            $sale_id = (int)$sale['saleid'];
            $cust_id = (int)$sale['cust_id'];
            $sale_total = (float)$sale['sale_total'];
            $sale_date = $sale['sale_date'];
            $total_sales++;

            if ($sale_total >= 100000) {
                $points = (int)($sale_total / 10000);
                $expired_at = date('Y-m-d H:i:s', strtotime($sale_date . ' +12 months'));

                $insert_result = $this->query("
                    INSERT INTO customer_points 
                    (pelanggan_id, sale_id, point_type, points, transaction_amount, description, expired_at, created_at)
                    VALUES ({$cust_id}, {$sale_id}, 'EARN', {$points}, {$sale_total}, 'Sinkronisasi penuh dari transaksi #{$sale_id}', '{$expired_at}', NOW())
                ");

                if ($insert_result) {
                    $total_rebuilt++;
                }
            }
        }

        // Step 4: Recalculate scores
        require_once __DIR__ . '/RewardProfileMatching.php';
        $reward = new RewardProfileMatching();
        $reward->calculateAllCustomerScores(date('m'), date('Y'));

        return json_encode([
            'status' => 'success',
            'message' => "Sinkronisasi penuh berhasil! {$total_rebuilt} poin telah direkonstruksi dari {$total_sales} transaksi",
            'sync_type' => 'full',
            'total_sales_processed' => $total_sales,
            'total_points_rebuilt' => $total_rebuilt,
            'backup_table' => 'customer_points_backup'
        ]);
    }

    /**
     * Get customer detail dengan matching score terbaru
     * Digunakan untuk menampilkan modal detail pelanggan
     */
    public function get_customer_detail_matching()
    {
        try {
            $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
            $periode = isset($_POST['periode']) ? (int)$_POST['periode'] : date('Y');

            if ($customer_id <= 0) {
                return json_encode(['status' => 'failed', 'message' => 'Customer ID tidak valid']);
            }

            // Get customer info
            $sql_customer = "SELECT id, kode, nama, alamat, kota, telpon FROM pelanggan WHERE id = {$customer_id}";
            $result_customer = $this->query($sql_customer);

            if (!$result_customer || $result_customer->num_rows === 0) {
                return json_encode(['status' => 'failed', 'message' => 'Pelanggan tidak ditemukan']);
            }

            $customer = $result_customer->fetch_assoc();

            // Date range
            $startDate = "{$periode}-01-01";
            $endDate = "{$periode}-12-31";

            // Get transaction data
            $sql_transaction = "
                SELECT 
                    COUNT(*) as jumlah_transaksi,
                    COALESCE(SUM(sale_total), 0) as total_belanja
                FROM sale
                WHERE cust_id = {$customer_id}
                AND sale_date >= '{$startDate}'
                AND sale_date <= '{$endDate}'
                AND sale_status NOT IN ('DELETED', 'VOID', 'CANCELED')
            ";

            $result_transaction = $this->query($sql_transaction);
            $transaction_data = $result_transaction->fetch_assoc();

            $jumlah_transaksi = (int)($transaction_data['jumlah_transaksi'] ?? 0);
            $total_belanja = (float)($transaction_data['total_belanja'] ?? 0);

            // Get total points (terbaru)
            $sql_poin = "
                SELECT COALESCE(SUM(CASE WHEN point_type IN ('EARN', 'ADJUST') THEN points ELSE -points END), 0) as total_poin 
                FROM customer_points 
                WHERE pelanggan_id = {$customer_id}
            ";

            $result_poin = $this->query($sql_poin);
            $poin_data = $result_poin->fetch_assoc();
            $total_poin = max(0, (int)($poin_data['total_poin'] ?? 0));

            // Reward settings
            $min_purchase = 50000;
            $point_per_rp = 10000;
            $min_score = 65;

            // Calculate scores
            $MAX_NOMINAL = 5000000;
            $MAX_FREQUENCY = 20;
            $CORE_WEIGHT = 0.60;
            $SECONDARY_WEIGHT = 0.40;

            $nominal_score = $total_belanja > 0 ? ($total_belanja / $MAX_NOMINAL) * 100 : 0;
            $nominal_score = min($nominal_score, 100);

            $frequency_score = $jumlah_transaksi > 0 ? ($jumlah_transaksi / $MAX_FREQUENCY) * 100 : 0;
            $frequency_score = min($frequency_score, 100);

            $final_score = ($nominal_score * $CORE_WEIGHT) + ($frequency_score * $SECONDARY_WEIGHT);
            $final_score = round($final_score, 2);

            return json_encode([
                'status' => 'success',
                'data' => [
                    'customer' => $customer,
                    'total_belanja' => $total_belanja,
                    'transaction_count' => $jumlah_transaksi,
                    'total_points' => $total_poin,
                    'matching_score' => [
                        'nominal_score' => round($nominal_score, 2),
                        'frequency_score' => round($frequency_score, 2),
                        'final_score' => $final_score
                    ],
                    'reward_settings' => [
                        'min_purchase' => $min_purchase,
                        'point_per_rp' => $point_per_rp,
                        'min_score' => $min_score
                    ],
                    'period' => $periode
                ]
            ]);
        } catch (Exception $e) {
            return json_encode([
                'status' => 'failed',
                'message' => $e->getMessage()
            ]);
        }
    }
}

if (is_direct_script(__FILE__)) {
require_login(true);
require_csrf_for_unsafe_request(true);

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$Master = new Master();
$action = request_action('none');
$sysset = new SystemSettings();

// Endpoint Master berisi administrasi data dan user. Samakan otorisasinya
// dengan routing halaman: Admin dan Supervisor mendapat akses penuh, sedangkan
// role operasional hanya boleh membaca statistik dashboard mereka sendiri.
$dashboardActions = ['get_dashboard_stats', 'get_dashboard_charts'];
$allowedMasterRoles = in_array($action, $dashboardActions, true) ? [1, 2, 3, 4] : [1, 4];
require_admin_auth($allowedMasterRoles, true);

// Semua action yang mengubah state wajib POST. Ini mencegah perubahan data
// melalui link/GET sekalipun ada action baru yang lupa memeriksa method.
if (preg_match('/^(?:save|update|delete|clear|reset|calculate|earn|sync|change)_/', $action)) {
    require_http_method(['POST'], true);
}

switch ($action) {
    case 'get_data_barang':
        echo $Master->get_data_barang();
        break;
    case 'save_barang':
        echo $Master->save_barang();
        break;
    case 'update_barang':
        echo $Master->update_barang();
        break;
    case 'update_barang_image':
        echo $Master->update_barang_image();
        break;
    case 'delete_barang':
        echo $Master->delete_barang();
        break;
    case 'get_single_barang':
        echo $Master->get_single_barang();
        break;

    case 'get_data_satuan':
        echo $Master->get_data_satuan();
        break;
    case 'save_satuan':
        echo $Master->save_satuan();
        break;
    case 'update_satuan':
        echo $Master->update_satuan();
        break;
    case 'delete_satuan':
        echo $Master->delete_satuan();
        break;
    case 'get_single_satuan':
        echo $Master->get_single_satuan();
        break;

    case 'get_data_kategori':
        echo $Master->get_data_kategori();
        break;
    case 'save_kategori':
        echo $Master->save_kategori();
        break;
    case 'update_kategori':
        echo $Master->update_kategori();
        break;
    case 'delete_kategori':
        echo $Master->delete_kategori();
        break;
    case 'get_single_kategori':
        echo $Master->get_single_kategori();
        break;

    // GET DROPDOWN
    case 'get_kategori':
        echo $Master->get_kategori();
        break;
    case 'get_satuan':
        echo $Master->get_satuan();
        break;
    case 'get_supplier':
        echo $Master->get_supplier();
        break;
    // GET DROPDOWN

    case 'get_data_pelanggan':
        echo $Master->get_data_pelanggan();
        break;
    case 'save_pelanggan':
        echo $Master->save_pelanggan();
        break;
    case 'update_pelanggan':
        echo $Master->update_pelanggan();
        break;
    case 'delete_pelanggan':
        echo $Master->delete_pelanggan();
        break;
    case 'get_single_pelanggan':
        echo $Master->get_single_pelanggan();
        break;

    case 'get_data_member':
        echo $Master->get_data_member();
        break;
    case 'save_member':
        echo $Master->save_member();
        break;
    case 'update_member':
        echo $Master->update_member();
        break;
    case 'delete_member':
        echo $Master->delete_member();
        break;
    case 'get_single_member':
        echo $Master->get_single_member();
        break;

    case 'get_data_supplier':
        echo $Master->get_data_supplier();
        break;
    case 'save_supplier':
        echo $Master->save_supplier();
        break;
    case 'update_supplier':
        echo $Master->update_supplier();
        break;
    case 'delete_supplier':
        echo $Master->delete_supplier();
        break;
    case 'get_single_supplier':
        echo $Master->get_single_supplier();
        break;

    case 'get_data_salesman':
        echo $Master->get_data_salesman();
        break;
    case 'save_salesman':
        echo $Master->save_salesman();
        break;
    case 'update_salesman':
        echo $Master->update_salesman();
        break;
    case 'delete_salesman':
        echo $Master->delete_salesman();
        break;
    case 'get_single_salesman':
        echo $Master->get_single_salesman();
        break;

    case 'get_data_admin':
        echo $Master->get_data_admin();
        break;
    case 'save_admin':
        echo $Master->save_admin();
        break;
    case 'update_admin':
        echo $Master->update_admin();
        break;
    case 'delete_admin':
        echo $Master->delete_admin();
        break;
    case 'get_single_admin':
        echo $Master->get_single_admin();
        break;

    case 'get_data_kasir':
        echo $Master->get_data_kasir();
        break;
    case 'save_kasir':
        echo $Master->save_kasir();
        break;
    case 'update_kasir':
        echo $Master->update_kasir();
        break;
    case 'delete_kasir':
        echo $Master->delete_kasir();
        break;
    case 'get_single_kasir':
        echo $Master->get_single_kasir();
        break;

    // User Management Cases
    case 'get_users_datatable':
        echo $Master->get_users_datatable();
        break;
    case 'get_users_by_level':
        echo $Master->get_users_by_level();
        break;
    case 'save_user':
        echo $Master->save_user();
        break;
    case 'get_user':
        echo $Master->get_user();
        break;
    case 'update_user':
        echo $Master->update_user();
        break;
    case 'delete_user':
        echo $Master->delete_user();
        break;
    case 'change_password':
        echo $Master->change_password();
        break;

    // Reward System Cases
    case 'get_customer_points':
        echo $Master->get_customer_points();
        break;
    case 'get_customer_reward_score':
        echo $Master->get_customer_reward_score();
        break;
    case 'calculate_reward_scores':
        echo $Master->calculate_reward_scores();
        break;
    case 'get_eligible_customers':
        echo $Master->get_eligible_customers();
        break;
    case 'get_customer_ranking':
        echo $Master->get_customer_ranking();
        break;
    case 'reset_customer_points':
        echo $Master->reset_customer_points();
        break;
    case 'reset_all_customer_points':
        echo $Master->reset_all_customer_points();
        break;
    case 'earn_points_from_transaction':
        echo $Master->earn_points_from_transaction();
        break;

    // Activity Log Cases
    case 'get_activity_logs':
        echo $Master->get_activity_logs();
        break;
    case 'get_log_detail':
        echo $Master->get_log_detail();
        break;
    case 'clear_all_logs':
        echo $Master->clear_all_logs();
        break;

    // Dashboard Cases
    case 'get_dashboard_stats':
        echo $Master->get_dashboard_stats();
        break;
    case 'get_dashboard_charts':
        echo $Master->get_dashboard_charts();
        break;

    // Ranking Point Detail Cases
    case 'get_point_settings_data':
        echo $Master->get_point_settings_data();
        break;
    case 'get_customer_detail_scoring':
        echo $Master->get_customer_detail_scoring();
        break;
    case 'get_customer_detail_matching':
        echo $Master->get_customer_detail_matching();
        break;

    case 'sync_customer_points_from_sales':
        echo $Master->sync_customer_points_from_sales();
        break;

    default:
        echo json_encode(['status' => 'failed', 'msg' => 'No action specified']);
        break;
}
}
