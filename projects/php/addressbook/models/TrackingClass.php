<?php

require_once($_SERVER["DOCUMENT_ROOT"] . '\dbConnectClass.php');

class Tracking {

    private $db;
    private $id;
    private $date_time;
    private $app_user;
    private $action;
    private $acting_class;
    private $class_id;

    public function __construct(&$db) {
        $args = func_get_args();
        $count = count($args);
        $i = -1;
        $this->id = 0;
        $this->date_time = date('m/d/y h:i:s', time());

        foreach (get_object_vars($this) as $prop => $val) {
            if ($prop === 'db' && $args[$i] instanceof PDO) {
                $this->db = $db;
            } elseif (!preg_match('/^(db|id|date_time)/', $prop) && ++$i < $count && isset($args[$i]) && !empty($args[$i])) {
                $this->set($prop, $args[$i]);
            } elseif (!preg_match('/^(db|id|date_time)/', $prop) && preg_match('/^(action|acting_class)/', $prop)) {
                $this->{$prop} = $prop === 'action' ? "Changes occurred." : "tracking";
            } elseif (!preg_match('/^(db|id|date_time)/', $prop)) {
                $this->{$prop} = $prop === 'class_id' ? -1 : "";
            }
        }
    }

    public function __get($property) {
        if (isset($this->{$property}) && $property !== 'db') {
            return $this->db->sanitizeOutput($this->{$property});
        }
    }

    public function set($property, $value) {
        if (property_exists($this, $property) && $property !== 'db') {
            $this->{$property} = gettype($value) === 'object' ? $this->db->sanitizeInput(get_class($value)) : $this->db->sanitizeInput($value);
        }
    }

    public function get_events_count($whereTemp = "") {
        $where = preg_match("/(`([a-zA-Z0-9])\w+`='?([0-9a-zA-Z])\w+'?( AND | OR )?)+/", $whereTemp) ? 'WHERE ' . $whereTemp : "";
        $query = "SELECT COUNT(`id`) AS `total` FROM `tracking` {$where}";
        $row = $this->db->select_assoc($query);
        return $row['total'];
    }

    public function add_event($action, $acting_class, $class_id) {
        $this->set_action(isset($action) ? $action : $this->action);
        $this->set_acting_class(isset($acting_class) ? $acting_class : $this->acting_class);
        $this->set_class_id(isset($class_id) ? $class_id : $this->class_id);
        if (isset($this->app_user) && !empty($this->action) && !empty($this->acting_class) && isset($this->class_id) && $this->class_id > 0) {
            $query = "INSERT INTO `tracking` (`date_time`,`app_user`,`action`,`acting_class`,`class_id`) VALUES (NOW(),'{$this->app_user}','{$this->action}','{$this->acting_class}',{$this->class_id})";
            $this->db->insert($query);
            return $this;
        }
    }

    public function get_all_events($limitTemp = "", $offsetTemp = 0, $whereTemp = "", $orderByTemp = "`date_time`", $directionTemp = "DESC") {
        $vars = $logs = array();

        foreach (get_object_vars($this) as $var => $val) {
            $vars[] = $var;
        }

        $limit = empty($limitTemp) ? '' : 'LIMIT ' . intval($limit);
        $offset = intval($offsetTemp);
        $where = preg_match("/(`([a-z0-9])\w+`='?([a-z0-9])\w+'?,?)+/i", $whereTemp) ? 'WHERE ' . $whereTemp : "";
        $orderBy = in_array($orderByTemp, $vars) ? $orderByTemp : "`date_time`";
        $direction = preg_match('/^(DESC|ASC)/i', $directionTemp) ? $directionTemp : 'DESC';
        $query = "SELECT `date_time`, `action`, `class_id` FROM `tracking` {$where} ORDER BY {$orderBy} {$direction} {$limit} OFFSET {$offset}";

        while ($row = $this->db->select_assoc($query)) {
            $row['action'] = $this->db->sanitizeOutput($row['action']);
            $logs[] = $row;
        }

        return $logs;
    }

    public function get_event() {
        $logs = $have_value = $need_value = array();
        $args = func_get_args();
        $i = -1;

        foreach (get_object_vars($this) as $prop => $val) {
            if (!preg_match('/^(db|id)/', $prop) && ++$i < count($args) && isset($args[$i]) && !empty($args[$i])) {
                $have_value[] = "`{$prop}` LIKE '%{$this->db->sanitizeInput($args[$i])}%'";
            } elseif ($prop !== 'db') {
                $need_value[] = "`{$prop}`";
            }
        }
        if (!empty($have_value)) {
            $have = implode(" AND ", $have_value);
            $need = empty($need_value) ? "" : ", " . implode(", ", $need_value);
            $query = "SELECT {$need} FROM `tracking` WHERE {$have}";
            while ($row = $this->db->select_assoc($query)) {
                $row['action'] = $this->db->sanitizeOutput($row['action']);
                $logs[] = $row;
            }
        }
        return $logs;
    }

}
