<?php

require_once($_SERVER["DOCUMENT_ROOT"] . '\dbConnectClass.php');

class tracking {

    private $db;
    private $id;
    private $date_time;
    private $app_user;
    private $action;
    private $acting_class;
    private $class_id;

    public function __construct($db, $app_user = "", $action = "Changes occurred.", $acting_class = "tracking", $class_id = -1) {
        $this->db = $db;
        $this->id = -1;
        $this->date_time = date('m/d/y h:i:s', time());
        $this->set_app_user($app_user);
        $this->set_action($action);
        $this->set_acting_class($acting_class);
        $this->set_class_id($class_id);
    }

    public function get_date_time() {
        if (isset($this->date_time) && !empty($this->date_time)) {
            return sanitizeOutput($this->date_time);
        }
    }

    public function get_app_user() {
        if (isset($this->app_user) && !empty($this->app_user)) {
            return sanitizeOutput($this->app_user);
        }
    }

    public function set_app_user($app_user) {
        $this->app_user = sanitizeInput($app_user);
    }

    public function get_action() {
        return sanitizeOutput($this->action);
    }

    public function set_action($action) {
        $this->action = sanitizeInput($action);
    }

    public function get_acting_class() {
        return sanitizeOutput($this->acting_class);
    }

    public function set_acting_class($class) {
        if (gettype($class) == "object") {
            $this->acting_class = sanitizeInput(get_class($class));
        } else {
            $this->acting_class = sanitizeInput($class);
        }
    }

    public function get_class_id() {
        if (isset($this->class_id) && $this->class_id >= 0) {
            return sanitizeOutput($this->class_id);
        }
    }

    public function set_class_id($id) {
        $this->class_id = sanitizeInput($id);
    }

    public function get_events_count($where = "") {
        $table = get_class($this);
        $where = preg_match("/(`([a-zA-Z0-9])\w+`='?([0-9a-zA-Z])\w+'?( AND | OR )?)+/", $where) ? 'WHERE ' . $where : "";
        $query = "SELECT COUNT(`id`)
                          AS `total`
                        FROM `{$this->db}`.`{$table}`
                    {$where}";
        $row = $this->db->tri_fetch_assoc($query);
        return $row['total'];
    }

    public function add_event($action, $acting_class, $class_id) {
        $this->set_action(isset($action) ? $action : $this->action);
        $this->set_acting_class(isset($acting_class) ? $acting_class : $this->acting_class);
        $this->set_class_id(isset($class_id) ? $class_id : $this->class_id);
        if (isset($this->app_user) && !empty($this->action) && !empty($this->acting_class) &&
                isset($this->class_id) && $this->class_id > 0) {
            $table = get_class($this);
            $query = "INSERT INTO `{$this->db}`.`{$table}` 
                                      (`date_time`,`app_user`,`action`,`acting_class`,`class_id`) 
                               VALUES (NOW(),'{$this->app_user}','{$this->action}','{$this->acting_class}',{$this->class_id})";
            $this->db->tri($query);
            return $this;
        }
    }

    public function get_all_events($limit = "", $offset = 0, $where = "", $order_by = "`date_time`", $direction = "DESC") {
        $vars = array();
        foreach (get_object_vars($this) as $var => $val) {
            $vars[] = $var;
        }
        $directs = array('DESC', 'ASC');
        $limit = empty($limit) ? '' : 'LIMIT ' . intval($limit);
        $offset = intval($offset);
        $where = preg_match("/(`([a-zA-Z0-9])\w+`='?([0-9a-zA-Z])\w+'?,?)+/", $where) ? 'WHERE ' . $where : "";
        $order_by = in_array($order_by, $vars) ? $order_by : "`date_time`";
        $direction = in_array($direction, $directs) ? $direction : 'DESC';
        $logs = array();
        $table = get_class($this);
        $query = "SELECT `date_time`, `action`, `class_id`
                        FROM `{$this->db}`.`{$table}`
                    {$where}
                    ORDER BY {$order_by} {$direction}
                    {$limit}
                      OFFSET {$offset}";
        while ($row = $this->db->tri_fetch_assoc($query)) {
            $row['action'] = sanitizeOutput($row['action']);
            $logs[] = $row;
        }

        return $logs;
    }

    public function get_event($date_time = null, $app_user = null, $action = null, $acting_class = null, $class_id = null) {
        $this->date_time = $date_time;
        $this->app_user = $app_user;
        $this->action = $action;
        $this->acting_class = $acting_class;
        $this->class_id = $class_id;

        $logs = array();

        $have_value = array();
        $need_value = array();

        if (isset($this->date_time) && !empty($this->date_time)) {
            $have_value[] = "`date_time` LIKE '%{$this->date_time}%'";
        } else {
            $need_value[] = "`date_time`";
        }

        if (isset($this->app_user) && !empty($this->app_user)) {
            $have_value[] = "`app_user` LIKE '%{$this->app_user}%'";
        } else {
            $need_value[] = "`app_user`";
        }

        if (isset($this->action) && !empty($this->action)) {
            $have_value[] = "`action` LIKE '%{$this->action}%'";
        } else {
            $need_value[] = "`action`";
        }

        if (isset($this->acting_class) && !empty($this->acting_class)) {
            $have_value[] = "`acting_class`='{$this->acting_class}'";
        } else {
            $need_value[] = "`acting_class`";
        }

        if (isset($this->class_id) && !empty($this->class_id)) {
            $have_value[] = "`class_id`='{$this->class_id}'";
        } else {
            $need_value[] = "`class_id`";
        }

        if (!empty($have_value)) {
            $table = get_class($this);
            $have = implode(" AND ", $have_value);
            $need = empty($need_value) ? "" : ", " . implode(", ", $need_value);
            $query = "SELECT `id`{$need} 
                            FROM `{$this->db}`.`{$table}` 
                           WHERE {$have}";
            while ($row = $this->db->tri_fetch_assoc($query)) {
                $row['action'] = sanitizeOutput($row['action']);
                $logs[] = $row;
            }

            return $logs;
        }
    }

}

function sanitizeInput($input) {
    return $this->db->escape_string(htmlentities(trim($input), ENT_HTML5, 'UTF-8', false));
}

function sanitizeOutput($output) {
    return stripslashes(html_entity_decode(str_replace('\r', '', $output), ENT_HTML5, 'UTF-8'));
}
