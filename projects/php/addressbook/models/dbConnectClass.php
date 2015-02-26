<?php

class DBConnect {

    private static $instance;
    private static $pdoInstance;
    private $database;
    private $production; // environment
    private $testing; // mode (truly run a query or not)
    private $queries;
    private $result;
    private $queryRaw;
    private $query;

    private function __construct($hostname = 'localhost', $database = '', $username = 'root', $password = '', $testing = true, $production = false) {
        if (($hostname === 'localhost' || empty($hostname)) && empty($database) && ($username === 'root' || empty($username)) && empty($password)) {
            include_once($_SERVER['DOCUMENT_ROOT'] . '/projects/php/addressbook/resources/dbInfo.php');
        }
        $this->database = $database;
        $this->testing = $testing;
        $this->production = $production;
        $this->queries = 0;
        if (!is_array($this->pdoInstance)) {
            $this->pdoInstance = array();
        }
        try {
            if (!$this->pdoInstance[$database]) {
                $this->pdoInstance[$database] = new PDO("mysql:host={$hostname};dbname={$database}", $username, $password);
            }
            if ($testing && !$production) {
                $this->consoleOut("Connected to database ({$database})");
            }
            //TODO: ADD LOG
        } catch (PDOException $e) {
            if ($testing && !$production) {
                $this->consoleOut($e->getMessage());
            }
            //TODO: ADD LOG
        }
    }

    private function __destruct() {
        
    }

    public static function instantiateDB($hostname = 'localhost', $database = '', $username = 'root', $password = '', $testing = true, $production = false) {
        if (!is_array(self::$instance)) {
            self::$instance = array();
        }
        if (self::$instance[$database] == null) {
            self::$instance[$database] = new self($hostname, $database, $username, $password, $testing, $production);
        }
        return self::$instance[$database];
    }

    private function __clone() {
        
    }

    public function exec($queryRaw = '', $type = 'insert') {
        $query = empty($queryRaw) ? $this->query : $this->queryValidation($queryRaw, $type);
        if (empty($query)) {
            return;
        }
        try {
            if ($this->testing && !$this->production) {
                $this->consoleOut($query);
            }
            //TODO: ADD LOG
            if (!$this->testing && $this->pdoInstance[$this->database]) {
                $count = $this->pdoInstance[$this->database]->exec($query);
            }
            //TODO: Create psuedo insert and record for testing mode
            $this->queries += $count;
            return $count;
        } catch (PDOException $e) {
            if ($this->testing && !$this->production) {
                $this->consoleOut($e->getMessage());
            }
            //TODO: ADD LOG
            return -1;
        }
    }

    public function insert($queryRaw = '') {
        return $this->exec($queryRaw, 'insert');
    }

    public function update($queryRaw = '') {
        return $this->exec($queryRaw, 'update');
    }

    public function delete($queryRaw = '') {
        return $this->exec($queryRaw, 'delete');
    }

    public function query($queryRaw = '', $type = 'select') {
        $query = empty($queryRaw) ? $this->query : $this->queryValidation($queryRaw, $type);
        if (empty($query)) {
            return;
        }
        try {
            if ($queryRaw === $this->queryRaw && $this->pdoInstance[$this->database]) {
                $this->result = $this->pdoInstance[$this->database]->query($this->query);
            }
            if ($this->testing && !$this->production) {
                $this->consoleOut($this->query);
            }
            return $this->result;
        } catch (PDOException $e) {
            if ($this->testing && !$this->production) {
                $this->consoleOut($e->getMessage());
            }
            //TODO: ADD LOG
            return -1;
        }
    }

    public function select($queryRaw = '') {
        return $this->query($queryRaw, 'select');
    }

    public function select_assoc($queryRaw = '') {
        return $this->pdoInstance[$this->database] ? $this->query($queryRaw, 'select')->fetch($this->pdoInstance->FETCH_ASSOC) : $this->query($queryRaw, 'select');
    }

    public function select_num($queryRaw = '') {
        return $this->pdoInstance[$this->database] ? $this->query($queryRaw, 'select')->fetch($this->pdoInstance->FETCH_NUM) : $this->query($queryRaw, 'select');
    }

    public function select_both($queryRaw = '') {
        return $this->pdoInstance[$this->database] ? $this->query($queryRaw, 'select')->fetch($this->pdoInstance->FETCH_BOTH) : $this->query($queryRaw, 'select');
    }

    public function select_object($queryRaw = '') {
        return $this->pdoInstance[$this->database] ? $this->query($queryRaw, 'select')->fetch($this->pdoInstance->FETCH_OBJECT) : $this->query($queryRaw, 'select');
    }

    public function select_lazy($queryRaw = '') {
        return $this->pdoInstance[$this->database] ? $this->query($queryRaw, 'select')->fetch($this->pdoInstance->FETCH_LAZY) : $this->query($queryRaw, 'select');
    }

    private function queryValidation($queryRaw, $type) {
        if ($queryRaw === $this->queryRaw) {
            return $this->query;
        }
        $this->queryRaw = $queryRaw;
        return $this->query = $this->queryRaw; // remove thise once complete function
        if (!preg_match("`?\d*[a-zA-Z][0-9a-zA-Z$_]*`?", $tableName)) {
            return;
        }
        if (!preg_match("(`?\d*[a-zA-Z][0-9,a-z,A-Z$_]*`?,?)+", $columnNames) && !is_array($columnNames)) {
            return;
        }
        if (!preg_match("('?\d*[a-zA-Z][0-9,a-z,A-Z$_]*'?,?)+", $whereClauses) && !is_array($whereClauses)) {
            return;
        }
        if (!preg_match("('?\d*[a-zA-Z][0-9,a-z,A-Z$_]*'?,?)+", $newValues) && !is_array($newValues)) {
            return;
        }
        $table = sanitize_input($tableName, false);
        $columns = sanitize_input($columnNames, false);
        $values = sanitize_input($newValues, false);
        $where = sanitize_input($whereClauses, false);
        if (is_array($columns)) {
            $columns = implode(',', $columns);
        }
        if (is_array($where)) {
            $where = implode(',', $where);
        }
        if (is_array($values)) {
            $values = implode('),(', $values);
        }
        if (!empty($where)) {
            $where = " WHERE {$where}";
        }
        switch ($type) {
            case 'select':
                $query = "SELECT {$columns} FROM {$table}{$where}{$orderBy}{$offset}{$limit}";
                break;
            case 'insert':
                $query = "INSERT INTO {$table}({$columns}) VALUES ({$values}){$where}";
                break;
            case 'update':
                break;
            case 'delete':
                break;
            default:
        }
        return $this->query;
    }

    private function consoleOut($output, $type = 'DB') {
        if (is_array($output) || is_object($output)) {
            echo("<script>console.log('{$type}: " . json_encode($output) . "');</script>");
        } else {
            echo("<script>console.log('{$type}: " . $output . "');</script>");
        }
    }

    private function sanitizeInput($input, $escape = true) {
        if (is_array($input)) {
            $new_input = array();
            foreach ($input as $key => $value) {
                $new_input[$key] = $escape ? addslashes(html_entity_decode(trim($value), ENT_HTML5, 'UTF-8')) : html_entity_decode(trim($value), ENT_HTML5, 'UTF-8');
            }
            return $new_input;
        }
        return $escape ? addslashes(html_entity_decode(trim($input), ENT_HTML5, 'UTF-8')) : html_entity_decode(trim($input), ENT_HTML5, 'UTF-8');
    }

    private function sanitizeOutput($output) {
        if (is_array($output)) {
            $new_output = array();
            foreach ($output as $key => $value) {
                if (is_array($value)) {
                    $new_output[$key] = $this->sanitizeOutput($value);
                } else {
                    $new_output[$key] = stripslashes(htmlentities(str_replace('\r', '', $value), ENT_HTML5, 'UTF-8', false));
                }
            }
            return $new_output;
        }
        return stripslashes(htmlentities(str_replace('\r', '', $output), ENT_HTML5, 'UTF-8', false));
    }

    public function camelToUnderscore($input) {
        return ltrim(strtolower(preg_replace('/[A-Z0-9]/', '_$0', $input)), '_');
    }

    public function underscoreToCamel($input) {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $input)));
    }

}

class UnitTest {

    private static $instance;
    private static $breakpoints;
    private $origFile;
    private $copyFile;
    private $currFunction;
    private $prevData;
    private $curreData;
    private $prevLine;
    private $currLine;
    private $pause;

    private function __construct() {
        
    }

    private function __destruct() {
        self::$instance = null;
    }

    public static function instantiateTest() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __get($property) {
        if (!isset($this->{$property})) {
            return null;
        }
        if (is_array($this->{$property})) {
            $new_output = array();
            foreach ($this->{$property} as $key => $value) {
                if (is_array($value)) {
                    $new_output[$key] = $this->{$property}[$key];
                } else {
                    $new_output[$key] = stripslashes(htmlentities(str_replace('\r', '', $value), ENT_HTML5, 'UTF-8', false));
                }
            }
            return $new_output;
        }
        return stripslashes(htmlentities(str_replace('\r', '', $this->{$property}), ENT_HTML5, 'UTF-8', false));
    }

    public function set($property, $input) {
        if (!property_exists($this, $property)) {
            return null;
        }
        if (is_array($input)) {
            $new_input = array();
            foreach ($input as $key => $value) {
                $new_input[$key] = addslashes(html_entity_decode(trim($value), ENT_HTML5, 'UTF-8'));
            }
            $this->{$property} = $new_input;
        }
        $this->{$property} = addslashes(html_entity_decode(trim($input), ENT_HTML5, 'UTF-8'));
    }

    public function traceProcesses() {

        echo '<br/>CLASS: ' . __CLASS__;
        echo '<br/>DIR: ' . __DIR__;
        echo '<br/>FILE: ' . __FILE__;
        echo '<br/>FUNCTION: ' . __FUNCTION__;
        echo '<br/>LINE: ' . __LINE__;
        echo '<br/>METHOD: ' . __METHOD__;
        echo '<br/>NAMESPACE: ' . __NAMESPACE__;
        echo '<br/>TRAIT: ' . __TRAIT__;
    }

    private function __clone() {
        
    }

}
