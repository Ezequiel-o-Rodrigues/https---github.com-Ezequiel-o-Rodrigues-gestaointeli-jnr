<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host = getenv('PGHOST') !== false ? getenv('PGHOST') : "ep-holy-lab-amw4z2md-pooler.c-5.us-east-1.aws.neon.tech";
        $this->db_name = getenv('PGDATABASE') !== false ? getenv('PGDATABASE') : "neondb";
        $this->username = getenv('PGUSER') !== false ? getenv('PGUSER') : "neondb_owner";
        $this->password = getenv('PGPASSWORD') !== false ? getenv('PGPASSWORD') : "npg_2YFVGWIqu4vD";
    }

    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "pgsql:host=" . $this->host . ";port=5432;dbname=" . $this->db_name . ";sslmode=require";
            $this->conn = new PDO($dsn, $this->username, $this->password);

            // Configurar encoding e timezone da sessão
            $this->conn->exec("SET client_encoding TO 'UTF8'");
            $this->conn->exec("SET timezone TO 'America/Sao_Paulo'");

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            error_log("Conexão com Neon PostgreSQL estabelecida com sucesso");

        } catch(PDOException $exception) {
            error_log("Erro de conexão: " . $exception->getMessage());
            throw $exception;
        }

        return $this->conn;
    }
}

date_default_timezone_set('America/Sao_Paulo');
?>