<?php
/**
 * ==========================================================
 *  FILE: classes/SystemSettings.php
 *  DESCRIPTION: Manage system_info table & global session vars
 *  AUTHOR: GPT-5 🤖
 * ==========================================================
 */

if(!class_exists('DBConnection')){
	require_once(__DIR__ . '/../config.php');
	require_once(__DIR__ . '/DBConnection.php');
}

class SystemSettings extends DBConnection {
	public function __construct() {
		parent::__construct();
	}

	/** Check active DB connection **/
	public function check_connection() {
		try {
			$this->connect();
			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	/** Load all system info into session **/
	public function load_system_info() {
		$sql = "SELECT meta_field, meta_value FROM system_info";
		$qry = $this->query($sql);
		while($row = $qry->fetch_assoc()){
			$_SESSION['system_info'][$row['meta_field']] = $row['meta_value'];
		}
	}

	/** Force refresh system info **/
	public function update_system_info() {
		unset($_SESSION['system_info']);
		$this->load_system_info();
		return true;
	}

	/** Update settings info based on POST data **/
	public function update_settings_info() {
		if(empty($_POST)) return json_encode(['status'=>'failed','msg'=>'No data received']);

		if (array_key_exists('sales_profit_percent', $_POST)) {
			$rawPercent = str_replace(',', '.', trim((string)$_POST['sales_profit_percent']));
			if ($rawPercent === '' || !is_numeric($rawPercent)) {
				return json_encode([
					'status' => 'failed',
					'msg' => 'Persentase pembagian profit sales harus berupa angka.'
				]);
			}

			$salesProfitPercent = (float)$rawPercent;
			if ($salesProfitPercent < 0 || $salesProfitPercent > 100) {
				return json_encode([
					'status' => 'failed',
					'msg' => 'Persentase pembagian profit sales harus berada di antara 0% sampai 100%.'
				]);
			}

			$_POST['sales_profit_percent'] = rtrim(rtrim(number_format($salesProfitPercent, 2, '.', ''), '0'), '.');
		}

		if (array_key_exists('ppn_rate', $_POST)) {
			$rawPpnRate = str_replace(',', '.', trim((string)$_POST['ppn_rate']));
			if ($rawPpnRate === '' || !is_numeric($rawPpnRate)) {
				return json_encode([
					'status' => 'failed',
					'msg' => 'Tarif PPN harus berupa angka.'
				]);
			}

			$ppnRate = (float)$rawPpnRate;
			if ($ppnRate < 0 || $ppnRate > 100) {
				return json_encode([
					'status' => 'failed',
					'msg' => 'Tarif PPN harus berada di antara 0% sampai 100%.'
				]);
			}

			$_POST['ppn_rate'] = rtrim(rtrim(number_format($ppnRate, 2, '.', ''), '0'), '.');
		}

		$this->conn->begin_transaction();
		try {
			foreach ($_POST as $key => $value) {
				if(in_array($key, ["content", "voucher", "rp_bulat", "dpoin"])) continue;
				$value = $this->escape($value);

				$stmt = $this->prepare("INSERT INTO system_info (meta_field, meta_value)
										VALUES (?, ?)
										ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
				$stmt->bind_param("ss", $key, $value);
				$stmt->execute();
				$stmt->close();
			}

			// Update profil table
			if(isset($_POST['voucher'], $_POST['rp_bulat'], $_POST['dpoin'])) {
				$stmt = $this->prepare("UPDATE profil SET VOUCHER = ?, RP_BULAT = ?, DPOIN = ?");
				$stmt->bind_param("sss", $_POST['voucher'], $_POST['rp_bulat'], $_POST['dpoin']);
				$stmt->execute();
				$stmt->close();
			}

			// Save editable HTML content if any
			if(isset($_POST['content']) && is_array($_POST['content'])){
				foreach($_POST['content'] as $k => $v){
					$safeKey = str_replace('\\', '/', trim((string)$k));
					if (!preg_match('/^[a-zA-Z0-9_\-\/]+$/', $safeKey) || strpos($safeKey, '..') !== false) {
						throw new Exception("Invalid content path");
					}

					$target = realpath(base_app) . DIRECTORY_SEPARATOR . $safeKey . '.html';
					$base = realpath(base_app);
					if ($base === false || strpos(realpath(dirname($target)) ?: dirname($target), $base) !== 0) {
						throw new Exception("Invalid content target");
					}

					file_put_contents($target, $v);
				}
			}

			// Upload images (logo & cover)
			$this->handleImageUpload('img', 'logo', 400, 130);
			$this->handleImageUpload('cover', 'cover', 1280, 720);

			$this->conn->commit();

			$this->update_system_info();
			$this->set_flashdata('success','System Info Successfully Updated.');
			return json_encode(['status'=>'success','msg'=>'System Info Successfully Updated.']);
		} catch (Exception $e) {
			$this->conn->rollback();
			error_log($e->getMessage());
			return json_encode(['status'=>'failed','msg'=>'Error: '.$e->getMessage()]);
		}
	}

	/** Handle image upload securely **/
	private function handleImageUpload($inputName, $metaField, $new_width, $new_height) {
		if(!isset($_FILES[$inputName]) || $_FILES[$inputName]['tmp_name'] == '') return;

		$validated = validate_uploaded_image(
			$_FILES[$inputName],
			['image/png', 'image/jpeg'],
			3 * 1024 * 1024,
			8000,
			8000,
			12000000
		);
		$upload = $_FILES[$inputName]['tmp_name'];
		$type = $validated['mime'];
		if (!function_exists('imagecreatetruecolor') || !function_exists('imagecreatefromstring')) {
			throw new RuntimeException('Ekstensi GD PHP wajib aktif untuk memproses logo dan cover dengan aman.');
		}

		$fname = 'uploads/' . secure_upload_filename($metaField, 'png');
		$dir_path = base_app.$fname;

		$width = $validated['width'];
		$height = $validated['height'];
		$t_image = imagecreatetruecolor($new_width, $new_height);
		imagealphablending($t_image, false);
		imagesavealpha($t_image, true);

		$sourceBytes = file_get_contents($upload);
		$gdImg = $sourceBytes !== false ? @imagecreatefromstring($sourceBytes) : false;
		unset($sourceBytes);
		if ($gdImg === false) {
			imagedestroy($t_image);
			throw new RuntimeException("Gambar {$metaField} gagal didekode dengan aman.");
		}
		imagecopyresampled($t_image, $gdImg, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

		imagepng($t_image, $dir_path);
		imagedestroy($gdImg);
		imagedestroy($t_image);

		// Update DB and remove old file if exists
		if(isset($_SESSION['system_info'][$metaField])) {
			$oldFile = str_replace('\\', '/', $_SESSION['system_info'][$metaField]);
			if (strpos($oldFile, '..') === false && is_file(base_app.$oldFile)) {
				unlink(base_app.$oldFile);
			}
		}
		$stmt = $this->prepare("INSERT INTO system_info (meta_field, meta_value)
								VALUES (?, ?) 
								ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)");
		$stmt->bind_param("ss", $metaField, $fname);
		$stmt->execute();
		$stmt->close();
	}

	/** Session helpers **/
	public function set_userdata($field='',$value=''){
		if($field !== '' && $value !== '') $_SESSION['userdata'][$field]= $value;
	}
	public function userdata($field=''){
		return $_SESSION['userdata'][$field] ?? null;
	}
	public function set_flashdata($flash='',$value=''){
		if($flash !== '' && $value !== '') $_SESSION['flashdata'][$flash]= $value;
		return true;
	}
	public function chk_flashdata($flash=''){
		return isset($_SESSION['flashdata'][$flash]);
	}
	public function flashdata($flash=''){
		if(isset($_SESSION['flashdata'][$flash])){
			$tmp = $_SESSION['flashdata'][$flash];
			unset($_SESSION['flashdata'][$flash]);
			return $tmp;
		}
		return false;
	}
	public function sess_des(){
		if(isset($_SESSION['userdata'])) unset($_SESSION['userdata']);
		return true;
	}

	/** Info getter/setter **/
	public function info($field=''){
		return $_SESSION['system_info'][$field] ?? false;
	}
	public function set_info($field='',$value=''){
		if($field !== '' && $value !== '') $_SESSION['system_info'][$field] = $value;
	}
}

// ==========================================================
// AUTO INITIALIZE SETTINGS
// ==========================================================
$_settings = new SystemSettings();
$_settings->load_system_info();

if (is_direct_script(__FILE__)) {
	require_admin_auth(1, true);
	require_csrf_for_unsafe_request(true);

	$action = request_action('none');
	switch ($action) {
		case 'update_settings':
			echo $_settings->update_settings_info();
			break;
		default:
			break;
	}
}
?>
