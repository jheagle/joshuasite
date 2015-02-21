<?php

class DBConnect extends PDO {

    private $production; // environment
    private $testing; // mode (truly run a query or not)
    private $queries;
    private $result;
    private $queryRaw;
    private $query;

    public function __construct($hostname = 'localhost', $database = '', $username = 'root', $password = '', $testing = true, $production = false) {
        if (($hostname === 'localhost' || empty($hostname)) && empty($database) && ($username === 'root' || empty($username)) && empty($password)) {
            include_once($_SERVER['DOCUMENT_ROOT'] . '/projects/php/addressbook/resources/dbInfo.php');
        }
        $this->testing = $testing;
        $this->production = $production;
        $this->queries = 0;
        try {
            parent::__construct("mysql:host={$hostname};dbname={$database}", $username, $password);
            if ($this->testing === true && $this->production === false) {
                echo "Connected to database ({$database})";
            }
            //TODO: ADD LOG
        } catch (PDOException $e) {
            if ($this->testing === true && $this->production === false) {
                echo $e->getMessage();
            }
            //TODO: ADD LOG
        }
    }

    public function __destruct() {
        
    }

    public function exec($queryRaw = '', $type) {
        $query = empty($queryRaw) ? $this->query : $this->queryValidation($queryRaw, $type);
        if (empty($query)) {
            return;
        }
        try {
            if ($this->testing === true && $this->production === false) {
                echo $query;
            }
            //TODO: ADD LOG
            if ($this->testing === false) {
                $count = parent::exec($query);
            }
            //TODO: Create psuedo insert and record for testing mode
            $this->queries += $count;
            return $count;
        } catch (PDOException $e) {
            if ($this->testing === true && $this->production === false) {
                echo $e->getMessage();
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

    public function query($queryRaw = '', $type) {
        $query = empty($queryRaw) ? $this->query : $this->queryValidation($queryRaw, $type);
        if (empty($query)) {
            return;
        }
        try {
            if ($queryRaw === $this->queryRaw) {
                $this->result = parent::query($this->query);
            }
            if ($this->testing === true && $this->production === false) {
                echo $this->query;
            }
            return $this->result;
        } catch (PDOException $e) {
            if ($this->testing === true && $this->production === false) {
                echo $e->getMessage();
            }
            //TODO: ADD LOG
            return -1;
        }
    }

    public function select($queryRaw = '') {
        return $this->query($queryRaw, 'select');
    }

    public function select_assoc($queryRaw = '') {
        return $this->query($queryRaw, 'select')->fetch(parent::FETCH_ASSOC);
    }

    public function select_num($queryRaw = '') {
        return $this->query($queryRaw, 'select')->fetch(parent::FETCH_NUM);
    }

    public function select_both($queryRaw = '') {
        return $this->query($queryRaw, 'select')->fetch(parent::FETCH_BOTH);
    }

    public function select_object($queryRaw = '') {
        return $this->query($queryRaw, 'select')->fetch(parent::FETCH_OBJECT);
    }

    public function select_lazy($queryRaw = '') {
        return $this->query($queryRaw, 'select')->fetch(parent::FETCH_LAZY);
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
                $new_output[$key] = stripslashes(htmlentities(str_replace('\r', '', $output), ENT_HTML5, 'UTF-8', false));
            }
            return $new_output;
        }
        return stripslashes(htmlentities(str_replace('\r', '', $output), ENT_HTML5, 'UTF-8', false));
    }

    function camelToUnderscore($input) {
        return ltrim(strtolower(preg_replace('/[A-Z0-9]/', '_$0', $input)), '_');
    }

    function underscoreToCamel($input) {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $input)));
    }

}
