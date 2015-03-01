<?php

/*
  require_once($_SERVER['DOCUMENT_ROOT'] . '/projects/php/addressbook/models/dbConnectClass.php');
  $db = DBConnect::instantiateDB('localhost','','root','', false);
  $test = UnitTest::instantiateTest($db, __FILE__);
  var_dump($test);
 */
require_once('dbConnectClass.php');
require_once('TrackingClass.php');

$db = DBConnect::instantiateDB('', '', '', '', false);
$tracking = new Tracking($db, isset($_SESSION['ab_user']) ? $_SESSION['ab_user'] : "");

class Contact {

    private $db;
    private $id;
    private $first_name;
    private $middle_name;
    private $last_name;
    private $address;
    private $phone_number;
    private $email;
    private $notes;

    public function __construct(&$db) {
        $args = func_get_args();
        $count = count($args);
        $i = 0;
        $this->id = -1;

        foreach (get_object_vars($this) as $prop => $val) {
            if ($prop === 'db' && $args[$i] instanceof DBConnect) {
                $this->db = $db;
            } elseif (!preg_match('/^(db|id)/', $prop) && $i < $count && isset($args[$i]) && !empty($args[$i])) {
                $this->set($prop, $args[$i]);
            } elseif (!preg_match('/^(db|id)/', $prop)) {
                $this->{$prop} = preg_match('/^(address|phone_number)/', $prop) ? array() : "";
            }
            ++$i;
        }
    }

    public function __get($property) {
        if (preg_match('/^(address|phone_number)/', $property)) {
            return $this->get_contact_info($property);
        } elseif (isset($this->{$property}) && $property !== 'db') {
            return $this->db->sanitizeOutput($this->{$property});
        }
    }

    public function set($property, $value) {
        if (is_array($value) && preg_match('/^(address|phone_number)/', $property)) {
            $this->db->consoleOut("Setting {$property} to " . json_encode($value), 'PHP');
            foreach ($value as $val) {
                if (is_array($val)) {
                    $this->set($property, $val);
                }
                $this->add_contact_info($val);
            }
        } elseif (preg_match('/^(address|phone_number)/', $property)) {
            $this->db->consoleOut("Setting {$property} to {$value}", 'PHP');
            $this->add_contact_info($value);
        } elseif (property_exists($this, $property) && $property !== 'db') {
            $this->db->consoleOut("Setting {$property} to {$value}", 'PHP');
            $this->{$property} = $this->db->sanitizeInput($value);
        }
    }

    public function get_contact_info($type, $contact_id = -1) {
        if ($contact_id < 1) {
            $contact_id = $this->id;
        }
        $info = $type === 'address' ? new ContactAddress($this->db, -1, $contact_id) : new ContactPhoneNumber($this->db, -1, $contact_id);
        $this->set($type, $info->get_all_{$type}());
        return $this->{$type};
    }

    public function add_contact_info($contact_info) {
        if (get_class($contact_info) == "ContactAddress") {
            $this->address[] = $contact_info;
        } elseif (get_class($contact_info) == "ContactPhoneNumber") {
            $phone_type = $contact_info->phone_type;
            if (empty($this->phone_number)) {
                $this->phone_number = array();
            }
            if (!in_array($phone_type, $this->phone_number)) {
                $this->phone_number[$phone_type] = array();
            }
            $this->phone_number[$phone_type][] = $contact_info;
        }
    }

    private function create_contact_info($type) {
        if (empty($this->id) || $this->id < 1 || !preg_match('/^(address|phone_number)/', $type)) {
            return null;
        }
        foreach ($this->{$type} as $key => &$val) {
            if (!is_array($val)) {
                $val->set('contact_id', $this->id);
                $val->create_contact_{$type}();
                $this->db->consoleOut("Created an Address", 'PHP');
                continue;
            }
            foreach ($key as &$v) {
                $v->set('contact_id', $this->id);
                $v->set('phone_type', $key);
                $v->create_contact_{$type}();
                $this->db->consoleOut("Created a Phone Number", 'PHP');
            }
        }
        return $this->{$type};
    }

    private function update_contact_info($type, $contact_infoIn = null, $idArrayIn = null, $strIndex = null) {
        if (empty($this->id) || $this->id < 1 || !preg_match('/^(address|phone_number)/', $type)) {
            return null;
        }

        $contact_info_ids = array();

        $idArray = is_array($idArrayIn) ? $idArrayIn : $this->{$type};
        $cnt = count($idArray);

        $temp_info = $type === 'address' ? new ContactAddress(-1, $this->id) : new ContactPhoneNumber(-1, $this->id);
        $newContact = empty($strIndex) ? $temp_info->get_all_contact_{$type}() : $temp_info->get_all_contact_{$type}()[$strIndex];
        $origContact = empty($strIndex) ? $contact_infoIn : $contact_infoIn[$strIndex];
        $contact_infoArray = is_array($contact_infoIn) ? $origContact : $newContact;

        $array = merge_array($idArray, $contact_infoArray);

        foreach ($array as $i => $contact_info) {
            if (preg_match('/^(home|work|cell)/', $i) || is_array($contact_info)) {
                $this->update_contact_info($type, $contact_infoArray, $idArray, $i);
            } elseif ($cnt > 0) {
                $contact_info_ids[] = !is_array($contact_info) && $contact_info->id > 0 ? $contact_info->id : $contact_info;
            } elseif ($cnt < 1) {
                $contact_info->{!in_array($contact_info->id, $contact_info_ids) || !isset($contact_info_ids) || empty($contact_info_ids) ? "delete_contact_{$type}" : "update_contact_{$type}"}();
            } else {
                $contact_info->create_contact_{$type}();
            }
            --$cnt;
        }

        return $this->{$type};
    }

    public function delete_contact_info($contact_info) {
        if (!preg_match('/^(ContactAddress|ContactPhoneNumber)/', get_class($contact_info)) || (!in_array($contact_info, $this->address) && !in_array($contact_info->phone_type, $this->phone_number))) {
            return null;
        }
        $type = get_class($contact_info) == "ContactAddress" ? 'address' : 'phone_number';
        $thisProp = $type === 'address' ? $this->{$type} : $this->{$type}[$contact_info->phone_type];
        foreach ($this->{$thisProp} as $i => $info) {
            if ($contact_info == $info) {
                $contact_info->delete_contact_{$type}();
                unset($this->{$thisProp}[$i]);
                return $info;
            }
        }
    }

    public function create_contact() {
        $columns = $values = array();

        foreach (get_object_vars($this) as $prop => $val) {
            if ($prop !== 'id' && $prop !== 'db' && (!isset($val) || empty($val) || $val < 0) && preg_match('/^(first_name|address|phone_number)/', $prop)) {
                return null;
            } elseif (!preg_match('/^(db|id|address|phone_number)/', $prop)) {
                $columns[] = "`{$prop}`";
                $values[] = "'{$val}'";
            }
        }

        $table = $this->db->camelToUnderscore(get_class($this));
        $cols = implode(',', $columns);
        $vals = implode(',', $values);
        $this->db->insert("INSERT INTO `{$table}` ({$cols}) VALUES ({$vals})");
        $this->set('id', end($this->search_contact_ids(null, true)));
        $GLOBALS['tracking']->add_event("Created {$this->first_name} {$this->middle_name} {$this->last_name}", $this, $this->id);
        $this->create_contact_info('address');
        $this->create_contact_info('phone_number');
        return $this;
    }

    public function get_as_json($array = null) {
        $ob_vars = is_array($array) ? $array : get_object_vars($this);
        foreach ($ob_vars as $prop => &$val) {
            if (is_array($val)) {
                $val = $this->get_as_json($val);
            } elseif (is_object($val) && method_exists($val, 'get_as_json')) {
                $val = $val->get_as_json();
            } elseif ($prop != 'db') {
                $val = $this->{$val};
            }
        }
        return json_encode($ob_vars);
    }

    public function get_all_contacts($summary = true) {
        $table = $this->db->camelToUnderscore(get_class($this));

        $contact_list = $this->retrieve_contacts_by_ids($this->db->select_assoc("SELECT `id` FROM `{$table}`"), $summary);

        if (is_array($contact_list)) {
            usort($contact_list, array($this, "compare_contacts"));
        }

        return $contact_list;
    }

    public function get_contact() {
        $contact = isset($this->id) && $this->id > 0 ? $this->retrieve_contact_by_id($this->id, false) : end($this->search_contact());

        foreach (get_object_vars($contact) as $prop => $val) {
            if ($prop !== 'db') {
                $this->set($prop, $val);
            }
        }

        return $this;
    }

    public function search_contact($name = "") {
        $contact_list = $this->retrieve_contacts_by_ids($this->search_contact_ids($name));
        if (is_array($contact_list)) {
            usort($contact_list, array($this, "compare_contacts"));
        }
        return $contact_list;
    }

    private function search_contact_ids($nameIn = "", $only_contact = false) {
        $name = $this->db->sanitizeInput($nameIn);
        $contact_ids = $have_value = array();

        foreach (get_object_vars($this) as $prop => $val) {
            if (!empty($name) && $prop === 'first_name') {
                $have_value[] = "MATCH(`first_name`,`middle_name`,`last_name`) AGAINST('{$name}')";
            } elseif ((!preg_match('/^(db|id|first_name|middle_name|last_name|address|phone_number)/', $prop) || (empty($name) && preg_match('/^(first_name|middle_name|last_name)/', $prop))) && isset($val) && !empty($val)) {
                $have_value[] = "`{$prop}` LIKE '%{$val}%'";
            }
        }

        if (!empty($have_value)) {
            $table = $this->db->camelToUnderscore(get_class($this));
            $have = implode(" AND ", $have_value);
            echo $query = "SELECT `id` FROM `{$table}` WHERE {$have}";

            while ($row = $this->db->select_assoc($query)) {
                $contact_ids[] = $row['id'];
            }
        }

        return $only_contact ? $contact_ids : $this->search_contact_id_by_contact_info('phone_number', $this->search_contact_id_by_contact_info('address', $contact_ids));
    }

    private function search_contact_id_by_contact_info($type, $contact_ids = array(), $arrayIn = null) {
        $array = is_array($arrayIn) ? $arrayIn : $this->{$type};
        if (isset($array) && !empty($array)) {
            $ids = array();
            foreach ($array as $contact_info) {
                if (isset($contact_info) && !empty($contact_info)) {
                    $ids = is_array($contact_info) ? $this->search_contact_id_by_contact_info($type, $contact_ids, $contact_info) : $contact_info->get_all_contact_{$type}(true);
                }
            }
        }
        return empty($contact_ids) ? $ids : array_intersect($contact_ids, $ids);
    }

    private function retrieve_contacts_by_ids($contact_ids = array(), $summary = true) {
        if (empty($contact_ids)) {
            return null;
        }
        $contacts = array();

        foreach ($contact_ids as $contact_id) {
            $contacts[] = $this->retrieve_contact_by_id($contact_id, $summary);
        }
        return $contacts;
    }

    private function retrieve_contact_by_id($contact_idIn, $summary = true) {
        $table = $this->db->camelToUnderscore(get_class($this));
        $id = isset($idIn) ? $idIn : $this->id;
        $columns = $values = array();

        foreach (get_object_vars($this) as $prop => $val) {
            if (!preg_match('/^(db|address|phone_number|email|notes)/', $prop) || (!$summary && preg_match('/^(email|notes)/', $prop))) {
                $columns[] = "`{$prop}`";
            }
        }
        $cols = implode(', ', $columns);
        $row = $this->db->select_assoc("SELECT {$cols} FROM `{$table}` WHERE `id`={$id}");

        if (!$summary) {
            $row['address'] = new ContactAddress(-1, $contact_id);
            $row['phone_number'] = new ContactPhoneNumber(-1, $contact_id);
        }

        foreach (get_object_vars($this) as $prop => $val) {
            $values[] = isset($row[$prop]) ? $row[$prop] : $val;
        }

        $reflect = new ReflectionClass($this);
        return $reflect->newInstanceArgs($values);
    }

    public function update_contact() {
        if (!isset($this->id) || $this->id < 1 || empty($this->first_name) || empty($this->last_name) || empty($this->address) || empty($this->phone_number['work'])) {
            return null;
        }
        $table = $this->db->camelToUnderscore(get_class($this));
        $set_to = $columns = $changed_col = array();

        foreach (get_object_vars($this) as $prop => $val) {
            if (!preg_match('/^db|address|phone_number/', $prop)) {
                $columns[] = "`{$prop}`";
            }
        }
        $cols = implode(',', $columns);
        $orig_cols = $this->db->select_assoc("SELECT {$cols} FROM `{$table}` WHERE `id` = {$this->id}");

        foreach (get_object_vars($this) as $prop => $val) {
            if ($val != $orig_cols[$prop] && !preg_match('/^db|address|phone_number/', $prop)) {
                $changed_col[$prop] = $val;
                $GLOBALS['tracking']->add_event("Modified {$this->first_name} {$this->middle_name} {$this->last_name} {$prop} from {$orig_cols[$prop]} to {$val}", $this, $this->id);
            }
        }

        foreach ($changed_col as $prop => $val) {
            if ((!isset($val) || empty($val) || $val < 1) && preg_match('/^(id|first_name|last_name)/', $prop)) {
                return null;
            }
            $set_to[] = "`{$prop}` = '{$val}'";
        }

        $set = implode(',', $set_to);
        $this->db->update("UPDATE `{$table}` SET {$set} WHERE `id`={$this->id}");
        $this->update_contact_info('address');
        $this->update_contact_info('phone_number');

        return $this;
    }

    public function delete_contact() {
        if (!isset($this->id) || $this->id < 1) {
            return null;
        }

        foreach ($this->address as &$address) {
            $address->delete_contact_address();
        }

        foreach ($this->phone_number as $phone_type => &$phone_number) {
            foreach ($phone_number as &$number) {
                $number->delete_contact_phone_number();
            }
        }

        $table = $this->db->camelToUnderscore(get_class($this));
        $this->db->delete("DELETE FROM `{$table}` WHERE `id`={$this->id}");
        $GLOBALS['tracking']->add_event("Deleted {$this->first_name} {$this->middle_name} {$this->last_name}", $this, $this->id);
        return $this;
    }

    private function compare_contacts($a, $b) {
        for ($i = 0; $i < 4; ++$i) {
            switch ($i) {
                case 0:
                    $result = strcasecmp($a->last_name, $b->last_name);
                    break;
                case 1:
                    $result = strcasecmp($a->first_name, $b->first_name);
                    break;
                case 2:
                    $result = strcasecmp($a->middle_name, $b->middle_name);
                    break;
                default:
                    $result = $a->id < $b->id ? -1 : 1;
            }
            if ($result !== 0) {
                return $result;
            }
        }
        return $result;
    }

}

class ContactAddress {

    private $db;
    private $id;
    private $contact_id;
    private $street;
    private $city;
    private $province;
    private $country;
    private $postal_code;

    public function __construct(&$db) {
        $args = func_get_args();
        $count = count($args);
        $i = 0;
        $this->id = -1;
        $this->contact_id = -1;

        foreach (get_object_vars($this) as $prop => $val) {
            if ($prop === 'db' && $args[$i] instanceof DBConnect) {
                $this->db = $db;
            } elseif (!preg_match('/^(db|id|contact_id)/', $prop) && $i < $count && isset($args[$i]) && !empty($args[$i])) {
                $this->set($prop, $args[$i]);
            } elseif (!preg_match('/^(db|id|contact_id)/', $prop)) {
                $this->{$prop} = "";
            }
            ++$i;
        }
    }

    public function __get($property) {
        if (isset($this->{$property}) && $property !== 'db') {
            return $this->db->sanitizeOutput($this->{$property});
        }
    }

    public function set($property, $value) {
        if (property_exists($this, $property) && $property !== 'db') {
            $this->db->consoleOut("Setting {$property} to {$value}", 'PHP');
            $this->{$property} = $this->db->sanitizeInput($value);
        }
    }

    public function create_contact_address() {
        if (!isset($this->contact_id) || $this->contact_id < 1 || empty($this->street) || empty($this->city) || empty($this->province) || empty($this->country) || empty($this->postal_code)) {
            return null;
        }
        $columns = $values = array();
        foreach (get_object_vars($this) as $prop => $val) {
            if (!preg_match('/^db|id/', $prop)) {
                $columns[] = "`{$prop}`";
                $values[] = "'{$val}'";
            }
        }
        $cols = implode(', ', $columns);
        $vals = implode(', ', $values);
        $table = $this->db->camelToUnderscore(get_class($this));
        $this->db->insert("INSERT INTO `{$table}` ({$cols}) VALUES ({$vals})");
        $GLOBALS['tracking']->add_event("Created {$this->street}, {$this->city}, {$this->province}, {$this->country}, {$this->postal_code}", $this, $this->contact_id);
        return $this;
    }

    public function get_as_json($array = null) {
        $ob_vars = is_array($array) ? $array : get_object_vars($this);
        foreach ($ob_vars as $prop => &$val) {
            if (is_array($val)) {
                $val = $this->get_as_json($val);
            } elseif (is_object($val) && method_exists($val, 'get_as_json')) {
                $val = $val->get_as_json();
            } elseif ($prop != 'db') {
                $val = $this->{$val};
            }
        }
        return json_encode($ob_vars);
    }

    public function get_all_contact_address($contact_ids_only = false) {
        $addresses = isset($this->id) && $this->id > 0 ? $this->retrieve_address_by_id() : $this->search_contact_address();

        if (!$contact_ids_only) {
            return $addresses;
        }

        $contact_ids = array();
        foreach ($addresses as $address) {
            if ($address->contact_id > 0) {
                $contact_ids[] = $address->contact_id;
            }
        }

        return $contact_ids;
    }

    public function get_contact_address() {
        $address = isset($this->id) && $this->id > 0 ? $this->retrieve_address_by_id() : $this->search_contact_address();

        if ($address->id < 1) {
            return null;
        }

        foreach (get_object_vars($address) as $prop => $val) {
            if ($prop !== 'db') {
                $this->set($prop, $val);
            }
        }

        return $this;
    }

    private function search_contact_ids($nameIn = "", $only_contact = false) {
        $name = $this->db->sanitizeInput($nameIn);
        $contact_ids = $have_value = array();

        foreach (get_object_vars($this) as $prop => $val) {
            if (!empty($name) && $prop === 'first_name') {
                $have_value[] = "MATCH(`first_name`,`middle_name`,`last_name`) AGAINST('{$name}')";
            } elseif ((!preg_match('/^(db|id|first_name|middle_name|last_name)/', $prop) || (empty($name) && preg_match('/^(first_name|middle_name|last_name)/', $prop))) && isset($val) && !empty($val)) {
                $have_value[] = "`{$prop}` LIKE '%{$val}%'";
            }
        }

        if (!empty($have_value)) {
            $table = $this->db->camelToUnderscore(get_class($this));
            $have = implode(" AND ", $have_value);
            $query = "SELECT `id` FROM `{$table}` WHERE {$have}";

            while ($row = $this->db->select_assoc($query)) {
                $contact_ids[] = $row['id'];
            }
        }

        return $only_contact ? $contact_ids : $this->search_contact_id_by_contact_info('phone_number', $this->search_contact_id_by_contact_info('address', $contact_ids));
    }

    private function search_contact_address() {
        $addresses = $need_value = $have_value = array();

        foreach (get_object_vars($this) as $prop => $val) {
            if (!preg_match('/^(db|id)/', $prop) && isset($val) && !empty($val)) {
                $have_value[] = "`{$prop}` LIKE '%{$val}%'";
            } elseif ($prop !== 'db') {
                $need_value[] = "`{$prop}`";
            }
        }

        if (empty($have_value)) {
            return null;
        }
        $table = $this->db->camelToUnderscore(get_class($this));
        $have = implode(" AND ", $have_value);
        $needs = empty($need_value) ? "" : ", " . implode(", ", $have_value);

        while ($row = $this->db->select_assoc("SELECT `id`{$needs} FROM `{$table}` WHERE {$have}")) {
            $values = array();
            foreach (get_object_vars($this) as $prop => $val) {
                $values[] = isset($row[$prop]) ? $row[$prop] : $val;
            }
            $reflect = new ReflectionClass($this);
            $addresses[] = $reflect->newInstanceArgs($values);
        }
        return $addresses;
    }

    private function retrieve_address_by_id($idIn = null) {
        $table = $this->db->camelToUnderscore(get_class($this));
        $id = isset($idIn) ? $idIn : $this->id;
        $columns = $values = array();

        foreach (get_object_vars($this) as $prop => $val) {
            if (!preg_match('/^(db|id)/', $prop)) {
                $columns[] = "`{$prop}`";
            }
        }
        $cols = implode(', ', $columns);
        $row = $this->db->select_assoc("SELECT {$cols} FROM `{$table}` WHERE `id`={$id}");

        foreach (get_object_vars($this) as $prop => $val) {
            $values[] = isset($row[$prop]) ? $row[$prop] : $val;
        }

        $reflect = new ReflectionClass($this);
        return $reflect->newInstanceArgs($values);
    }

    public function update_contact_address() {
        if (!isset($this->id) || $this->id < 0 || !isset($this->contact_id) || $this->contact_id < 1 || empty($this->street) || empty($this->city) || empty($this->country) || empty($this->postal_code)) {
            return null;
        }
        $table = $this->db->camelToUnderscore(get_class($this));
        $set_to = $columns = $changed_col = array();

        foreach (get_object_vars($this) as $prop => $val) {
            if ($prop !== 'db') {
                $columns[] = "`{$prop}`";
            }
        }
        $cols = implode(',', $columns);
        $orig_cols = $this->db->select_assoc("SELECT {$cols} FROM `{$table}` WHERE `id` = {$this->id}");

        foreach (get_object_vars($this) as $prop => $val) {
            if ($val != $orig_cols[$prop] && $prop !== 'db') {
                $changed_col[$prop] = $val;
                $GLOBALS['tracking']->add_event("Modified {$this->street}, {$this->city}, {$this->province}, {$this->country}, {$this->postal_code} {$prop} from {$orig_cols[$prop]} to {$val}", $this, $this->contact_id);
            }
        }

        foreach ($changed_col as $prop => $val) {
            if ((!isset($val) || empty($val) || $val < 1) && preg_match('/^(id|contact_id)/', $prop)) {
                return null;
            }
            $set_to[] = "`{$prop}` = '{$val}'";
        }

        $set = implode(',', $set_to);
        $this->db->update("UPDATE `{$table}` SET {$set} WHERE `id`={$this->id}");

        return $this;
    }

    public function delete_contact_address() {
        if (!isset($this->id) || $this->id < 1) {
            return null;
        }
        $table = $this->db->camelToUnderscore(get_class($this));
        $this->db->delete("DELETE FROM `{$table}` WHERE `id`={$this->id}");
        $GLOBALS['tracking']->add_event("Deleted {$this->street}, {$this->city}, {$this->province}, {$this->country}, {$this->postal_code}", $this, $this->contact_id);
        return $this;
    }

}

class ContactPhoneNumber {

    private $db;
    private $id;
    private $contact_id;
    private $phone_type;
    private $phone_number;

    public function __construct(&$db) {
        $args = func_get_args();
        $count = count($args);
        $i = 0;
        $this->id = -1;
        $this->contact_id = -1;

        foreach (get_object_vars($this) as $prop => $val) {
            if ($prop === 'db' && $args[$i] instanceof DBConnect) {
                $this->db = $db;
            } elseif (!preg_match('/^(db|id|contact_id)/', $prop) && $i < $count && isset($args[$i]) && !empty($args[$i])) {
                $this->set($prop, $args[$i]);
            } elseif (!preg_match('/^(db|id|contact_id)/', $prop)) {
                $this->{$prop} = "";
            }
            ++$i;
        }
    }

    public function __get($property) {
        if (isset($this->{$property}) && $property !== 'db') {
            return $this->db->sanitizeOutput($this->{$property});
        }
    }

    public function set($property, $value) {
        if (property_exists($this, $property) && $property !== 'db') {
            $this->db->consoleOut("Setting {$property} to {$value}", 'PHP');
            $this->{$property} = $this->db->sanitizeInput($value);
        }
    }

    public function create_contact_phone_number() {
        if (!isset($this->contact_id) || $this->contact_id < 1 || empty($this->phone_type) || empty($this->phone_number)) {
            return null;
        }
        $columns = $values = array();
        foreach (get_object_vars($this) as $prop => $val) {
            if (!preg_match('/^db|id/', $prop)) {
                $columns[] = "`{$prop}`";
                $values[] = "'{$val}'";
            }
        }
        $cols = implode(', ', $columns);
        $vals = implode(', ', $values);
        $table = $this->db->camelToUnderscore(get_class($this));
        $this->db->insert("INSERT INTO `{$table}` ({$cols}) VALUES ({$vals})");
        $GLOBALS['tracking']->add_event("Created {$this->phone_number}", $this, $this->contact_id);
        return $this;
    }

    public function get_as_json($array = null) {
        $ob_vars = is_array($array) ? $array : get_object_vars($this);
        foreach ($ob_vars as $prop => &$val) {
            if (is_array($val)) {
                $val = $this->get_as_json($val);
            } elseif (is_object($val) && method_exists($val, 'get_as_json')) {
                $val = $val->get_as_json();
            } elseif ($prop != 'db') {
                $val = $this->{$val};
            }
        }
        return json_encode($ob_vars);
    }

    public function get_all_contact_phone_number($contact_ids_only = false) {
        $phone_numbers = isset($this->id) && $this->id > 0 ? $this->retrieve_contact_phone_number_by_id() : $this->search_contact_phone_number();

        if (!$contact_ids_only) {
            return $phone_numbers;
        }
        $contact_ids = array();
        foreach ($phone_numbers as $phone_number) {
            if ($phone_number->contact_id > 0) {
                $contact_ids[] = $phone_number->contact_id;
            }
        }
        return $contact_ids;
    }

    public function get_contact_phone_number() {
        $phone_number = isset($this->id) && $this->id > 0 ? $this->retrieve_phone_numbers_by_id() : end($this->search_contact_phone_number());

        foreach (get_object_vars($phone_number) as $prop => $val) {
            if ($prop !== 'db') {
                $this->set($prop, $val);
            }
        }
        return $this;
    }

    private function search_contact_phone_number() {
        $phone_numbers = $have_value = $need_value = array();

        foreach (get_object_vars($this) as $prop => $val) {
            if (!preg_match('/^(db|id)/', $prop) && isset($val) && (!empty($val) || $val > 0)) {
                $have_value[] = "`{$prop}` LIKE '%{$val}%'";
            } elseif ($prop !== 'db') {
                $need_value[] = "`{$prop}`";
            }
        }

        if (empty($have_value)) {
            return null;
        }

        $table = $this->db->camelToUnderscore(get_class($this));
        $have = implode(" AND ", $have_value);
        $needs = empty($need_value) ? "" : ", " . implode(", ", $need_value);

        while ($row = $this->db->select_assoc("SELECT `id`{$needs} FROM `{$table}` WHERE {$have}")) {
            $values = array();
            foreach (get_object_vars($this) as $prop => $val) {
                $values[] = isset($row[$prop]) ? $row[$prop] : $val;
            }
            $reflect = new ReflectionClass($this);
            $phone_numbers[] = $reflect->newInstanceArgs($values);
        }

        return $phone_numbers;
    }

    private function retrieve_contact_phone_number_by_id($idIn = null) {
        $table = $this->db->camelToUnderscore(get_class($this));
        $id = isset($idIn) ? $idIn : $this->id;
        $columns = $values = array();
        foreach (get_object_vars($this) as $prop => $val) {
            if ($prop !== 'db') {
                $columns[] = "`$prop'";
            }
        }
        $cols = implode(", ", $columns);
        $row = $this->db->select_assoc("SELECT {$cols} FROM `{$table}` WHERE `id` = {$id}");
        foreach ($row as $prop => $val) {
            $values[] = isset($row[$prop]) ? $row[$prop] : $val;
        }
        $reflect = new ReflectionClass($this);
        return $reflect->newInstanceArgs($values);
    }

    public function update_contact_phone_number() {
        if (!isset($this->id) && $this->id < 1 || !isset($this->contact_id) || $this->contact_id < 1 && empty($this->phone_type) || empty($this->phone_number)) {
            return null;
        }
        $table = $this->db->camelToUnderscore(get_class($this));
        $set_to = $columns = $changed_col = array();

        foreach (get_object_vars($this) as $prop => $val) {
            if ($prop !== 'db') {
                $columns[] = "`{$prop}`";
            }
        }
        $cols = implode(',', $columns);
        $orig_cols = $this->db->select_assoc("SELECT {$cols} FROM `{$table}` WHERE `id` = {$this->id}");

        foreach (get_object_vars($this) as $prop => $val) {
            if ($val != $orig_cols[$prop] && $prop !== 'db') {
                $changed_col[$prop] = $val;
                $GLOBALS['tracking']->add_event("Modified {$this->phone_number} {$prop} from {$orig_cols[$prop]} to {$val}", $this, $this->contact_id);
            }
        }

        foreach ($changed_col as $prop => $val) {
            if ((!isset($val) || empty($val) || $val < 1) && preg_match('/^(id|contact_id)/', $prop)) {
                return null;
            }
            $set_to[] = "`{$prop}` = '{$val}'";
        }

        $set = implode(',', $set_to);
        $this->db->update("UPDATE `{$table}` SET {$set} WHERE `id` = {$this->id}");

        return $this;
    }

    public function delete_contact_phone_number() {
        if (!isset($this->id) || $this->id < 1) {
            return null;
        }
        $table = $this->db->camelToUnderscore(get_class($this));
        $query = "DELETE FROM `{$table}` WHERE `id` = {$this->id}";
        $this->db->delete($query);
        $GLOBALS['tracking']->add_event("Deleted {$this->phone_number}", $this, $this->contact_id);
        return $this;
    }

}
