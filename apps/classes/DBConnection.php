<?php

/**
 * ==========================================================
 *  FILE: classes/DBConnection.php
 *  DESCRIPTION: Safe, Lazy MySQLi Connection Handler + Error Logger
 *  AUTHOR: GPT-5 🤖
 * ==========================================================
 */

if (!defined('DB_SERVER')) {
    require_once(__DIR__ . '/../initialize.php');
}

class DBConnection
{
    private $host;
    private $username;
    private $password;
    private $database;
    private $port;
    private $charset = "utf8mb4";
    public $conn = null;

    /** Path ke file log **/
    private $logFile;

    public function __construct()
    {
        $this->host = DB_SERVER;
        $this->username = DB_USERNAME;
        $this->password = DB_PASSWORD;
        $this->database = DB_NAME;
        $this->port = DB_PORT;
        $this->logFile = __DIR__ . '/../logs/db_error.log';
    }

    /** =====================================================
     *  Private Lazy Connection (hanya dipanggil sekali)
     * ===================================================== */
    protected function connect()
    {
        if ($this->conn === null) {
            mysqli_report(MYSQLI_REPORT_OFF);
            $attempts = [
                [$this->host, $this->port],
            ];

            if ($this->host === 'localhost') {
                $attempts[] = ['127.0.0.1', $this->port];
            }

            if ((int)$this->port !== 3306) {
                $attempts[] = [$this->host, 3306];
                $attempts[] = ['127.0.0.1', 3306];
            }

            $lastError = null;

            foreach ($attempts as [$host, $port]) {
                $conn = @new mysqli(
                    $host,
                    $this->username,
                    $this->password,
                    $this->database,
                    $port
                );

                if (!$conn->connect_error) {
                    $this->conn = $conn;
                    $this->conn->set_charset($this->charset);
                    return;
                }

                $lastError = $conn->connect_error;
                $this->conn = null;
            }

            $this->logError("Database Connection Failed", $lastError);
            throw new Exception($this->publicError("Database Connection Failed", $lastError));
        }
    }

    /** =====================================================
     *  Logging Helper
     * ===================================================== */
    private function logError($title, $message, $file = null, $line = null)
    {
        try {
            $timestamp = date("Y-m-d H:i:s");
            $fileInfo = ($file && $line) ? " in $file:$line" : "";
            $logMsg = "[$timestamp] [$title] $message$fileInfo" . PHP_EOL;

            $dir = dirname($this->logFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            error_log($logMsg, 3, $this->logFile);
        } catch (Exception $e) {
            // fallback diam-diam
        }
    }

    private function publicError($title, $detail)
    {
        if (defined('APP_ENV') && APP_ENV === 'development') {
            return $title . ": " . $detail;
        }

        return "Database sedang bermasalah. Silakan coba lagi.";
    }

    private function inferTypes(array $params)
    {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        return $types;
    }

    private function bindParams($stmt, array $params)
    {
        if (empty($params)) {
            return;
        }

        $types = $this->inferTypes($params);
        $bind = [$types];
        foreach ($params as $key => &$value) {
            $bind[] = &$value;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    /** =====================================================
     *  Safe Query dengan Lazy Connection
     * ===================================================== */
    public function query($sql)
    {
        $this->connect();
        $result = $this->conn->query($sql);
        if (!$result) {
            $this->logError("Query Error", $this->conn->error, __FILE__, __LINE__);
            throw new Exception($this->publicError("Query Error", $this->conn->error));
            throw new Exception("❌ Query Error: " . $this->conn->error);
        }
        return $result;
    }

    /** =====================================================
     *  Safe Prepared Statement
     * ===================================================== */
    public function prepare($sql)
    {
        $this->connect();
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            error_log("DB FILE PATH: " . __FILE__);

            $this->logError("Statement Prepare Error", $this->conn->error, __FILE__, __LINE__);
            throw new Exception($this->publicError("Statement Prepare Error", $this->conn->error));
            throw new Exception("❌ Statement Prepare Error: " . $this->conn->error);
        }
        return $stmt;
    }

    public function execute($sql, array $params = [])
    {
        if (empty($params)) {
            return $this->query($sql);
        }

        $stmt = $this->prepare($sql);
        $this->bindParams($stmt, $params);

        if (!$stmt->execute()) {
            $this->logError("Statement Execute Error", $stmt->error, __FILE__, __LINE__);
            throw new Exception($this->publicError("Statement Execute Error", $stmt->error));
        }

        return $stmt;
    }

    /** =====================================================
     *  Helper: Fetch All
     * ===================================================== */
    public function fetchAll($sql, array $params = [])
    {
        $res = $this->execute($sql, $params);
        if ($res instanceof mysqli_stmt) {
            $result = $res->get_result();
            $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            $res->close();
            return $rows;
        }
        return $res->fetch_all(MYSQLI_ASSOC);
    }

    /** =====================================================
     *  Helper: Fetch One
     * ===================================================== */
    public function fetchOne($sql, array $params = [])
    {
        $res = $this->execute($sql, $params);
        if ($res instanceof mysqli_stmt) {
            $result = $res->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $res->close();
            return $row;
        }
        return $res->fetch_assoc();
    }

    /** =====================================================
     *  Escape String
     * ===================================================== */
    public function escape($str)
    {
        $this->connect();
        return $this->conn->real_escape_string($str);
    }

    /** =====================================================
     *  Destructor - safely close connection without errors
     * ===================================================== */
    public function __destruct()
    {
        // Don't close connection in destructor - let PHP handle cleanup
        // This prevents "mysqli object is already closed" errors
        // The connection will be cleaned up when script ends
        $this->conn = null;
    }
}
