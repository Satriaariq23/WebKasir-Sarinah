<?php

/**
 * ==========================================================
 *  FILE: classes/Login.php
 *  DESCRIPTION: Secure Login Handler (bcrypt + session)
 *  AUTHOR: GPT-5 🤖
 * ==========================================================
 */

require_once __DIR__ . '/../config.php';

class Login extends DBConnection
{
	private $settings;

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

	/**
	 * 🚫 Halaman default (jika file diakses langsung)
	 */
	public function index()
	{
		echo "<h1>Access Denied</h1> <a href='" . base_url . "'>Go Back.</a>";
	}

	/**
	 * 🧠 Login utama
	 * - Validasi input
	 * - Cek username aktif
	 * - Verifikasi password bcrypt
	 * - Simpan session user
	 * - Catat login attempt
	 */
	public function login()
	{
		$username = trim($_POST['username'] ?? '');
		$password = (string)($_POST['password'] ?? '');
		$resp = ['status' => 'failed', 'msg' => ''];
		$invalidMsg = 'Username atau password salah.';

		// === Input validation ===
		if (empty($username) || empty($password)) {
			$resp['msg'] = 'Username dan password wajib diisi.';
			return json_encode($resp);
			exit;
		}
		if (preg_match('/[\'"<>;\\\\]/', $username)) {
			$resp['msg'] = 'Karakter tidak valid pada username.';
			return json_encode($resp);
			exit;
		}
		if (strlen($username) > 100 || strlen($password) > 4096) {
			$this->slowDownFailedLogin();
			$resp['msg'] = $invalidMsg;
			return json_encode($resp);
		}
		if ($this->isLoginThrottled($username)) {
			$resp['msg'] = 'Terlalu banyak percobaan login. Coba lagi 15 menit lagi.';
			return json_encode($resp);
			exit;
		}

		// === Cek user di DB ===
		$sql = "SELECT u.*, l.level_nama 
				FROM users u 
				LEFT JOIN user_level l ON u.level_id = l.level_id 
				WHERE u.username = ? AND u.status = 1 
				LIMIT 1";

		$stmt = $this->prepare($sql);
		$stmt->bind_param("s", $username);
		$stmt->execute();
		$result = $stmt->get_result();

		if (!$result || $result->num_rows === 0) {
			$this->recordLoginAttempt($username, false);
			$this->slowDownFailedLogin();
			$resp['msg'] = $invalidMsg;
			return json_encode($resp);
			exit;
		}

		$user = $result->fetch_assoc();
		$stmt->close();

		// === Verifikasi password bcrypt ===
		if (!empty($user['password_bcrypt']) && password_verify($password, $user['password_bcrypt'])) {
			if (password_needs_rehash($user['password_bcrypt'], PASSWORD_DEFAULT)) {
				$newHash = password_hash($password, PASSWORD_DEFAULT);
				$userId = (int)$user['id'];
				$rehash = $this->prepare("UPDATE users SET password_bcrypt = ? WHERE id = ?");
				$rehash->bind_param("si", $newHash, $userId);
				$rehash->execute();
				$rehash->close();
			}
			$this->recordLoginAttempt($username, true);
			$this->setSession($user);
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
			$this->logActivity(
				(int)$user['id'],
				'LOGIN',
				'Login berhasil: ' . ($user['firstname'] ?? $username) . ' (' . ($user['level_nama'] ?? 'User') . ')'
			);

			$resp['status'] = 'success';
			$resp['msg'] = 'Login berhasil.';
			$resp['level_id'] = $user['level_id'];
			$resp['level_nama'] = $user['level_nama'];
			return json_encode($resp);
			exit;
		}

		// === Password salah ===
		$this->recordLoginAttempt($username, false);
		$this->slowDownFailedLogin();
		$resp['msg'] = $invalidMsg;
		return json_encode($resp);
		exit;
	}

	/**
	 * 🔐 Simpan session user
	 */
	private function setSession($user)
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_regenerate_id(true);
		}

		foreach ($user as $key => $value) {
			if ($key !== 'password_bcrypt') {
				$this->settings->set_userdata($key, $value);
			}
		}
		$this->settings->set_userdata('login_type', 1);
		$this->settings->set_flashdata('success', 'Login berhasil. Selamat datang kembali!');
	}

	/**
	 * 🚪 Logout user
	 */
	public function logout()
	{
		$userId = (int)($this->settings->userdata('id') ?? $_SESSION['userdata']['id'] ?? 0);
		$name = $this->settings->userdata('firstname') ?? $_SESSION['userdata']['firstname'] ?? 'User';
		$role = $this->settings->userdata('level_nama') ?? $_SESSION['userdata']['level_nama'] ?? 'User';

		if ($userId > 0) {
			$this->logActivity($userId, 'LOGOUT', 'Logout: ' . $name . ' (' . $role . ')');
		}

		destroy_current_session();
		redirect('../../sarinah-apps/');
	}

	private function slowDownFailedLogin()
	{
		// Jitter kecil mengurangi efektivitas brute-force dan timing enumeration.
		usleep(random_int(180000, 360000));
	}

	/**
	 * 🕵️‍♂️ Catat login attempt (keamanan tambahan)
	 * Tabel: login_attempts (username, attempts, last_attempt, created_at)
	 */
	private function recordLoginAttempt($username, $success)
	{
		if (empty($username)) return;

		$stmt = $this->prepare("SELECT * FROM login_attempts WHERE username = ? LIMIT 1");
		$stmt->bind_param("s", $username);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result && $result->num_rows > 0) {
			$row = $result->fetch_assoc();
			if ($success) {
				// Reset attempts after successful login
				$reset = $this->prepare("UPDATE login_attempts SET attempts = 0, last_attempt = NOW() WHERE id = ?");
				$reset->bind_param("i", $row['id']);
				$reset->execute();
				$reset->close();
			} else {
				// Increment attempt count
				$update = $this->prepare("UPDATE login_attempts SET attempts = attempts + 1, last_attempt = NOW() WHERE id = ?");
				$update->bind_param("i", $row['id']);
				$update->execute();
				$update->close();
			}
		} else {
			// Create new record
			$new = $this->prepare("INSERT INTO login_attempts (username, attempts, last_attempt) VALUES (?, ?, NOW())");
			$attempts = $success ? 0 : 1;
			$new->bind_param("si", $username, $attempts);
			$new->execute();
			$new->close();
		}
		$stmt->close();
	}

	private function isLoginThrottled($username)
	{
		if (empty($username)) {
			return false;
		}

		$stmt = $this->prepare("SELECT attempts, last_attempt FROM login_attempts WHERE username = ? LIMIT 1");
		$stmt->bind_param("s", $username);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result ? $result->fetch_assoc() : null;
		$stmt->close();

		if (!$row || (int)$row['attempts'] < 5 || empty($row['last_attempt'])) {
			return false;
		}

		return strtotime($row['last_attempt']) >= strtotime('-15 minutes');
	}

	private function logActivity($userId, $aksi, $keterangan)
	{
		try {
			$userId = (int)$userId;
			if ($userId <= 0) {
				return;
			}

			$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
			$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
			$tabel = 'auth';
			$recordId = $userId;

			$stmt = $this->prepare("INSERT INTO log_aktivitas
				(user_id, aksi, tabel, record_id, keterangan, ip_address, user_agent, created_at)
				VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
			$stmt->bind_param("ississs", $userId, $aksi, $tabel, $recordId, $keterangan, $ipAddress, $userAgent);
			$stmt->execute();
			$stmt->close();
		} catch (Exception $e) {
			error_log("[LOGIN ACTIVITY LOG ERROR] " . $e->getMessage());
		}
	}
}

/**
 * ==========================================================
 *  ACTION ROUTING (based on $_GET['f'])
 * ==========================================================
 */
$action = request_action('none');
$auth = new Login();

switch ($action) {
	case 'login':
		if (!headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}
		require_http_method(['POST'], true);
		require_csrf_for_unsafe_request(true);
		echo $auth->login();
		break;
	case 'logout':
		require_login(false);
		require_http_method(['POST'], false);
		require_csrf_for_unsafe_request(false);
		$auth->logout();
		break;
	default:
		$auth->index();
		break;
}
