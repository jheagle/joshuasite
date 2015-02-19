<?php

require_once('TrackingClass.php');

$tracking = new tracking(isset($_SESSION['ab_user']) ? $_SESSION['ab_user'] : "");

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
            if ($prop === 'db' && $args[$i] instanceof PDO) {
                $this->db = $db;
            } elseif (!preg_match('/^(db|id)/', $prop) && ++$i < $count && isset($args[$i]) && !empty($args[$i])) {
                $this->set($prop, $args[$i]);
            } elseif (!preg_match('/^(db|id)/', $prop)) {
                $this->{$prop} = preg_match('/^(address|phone_number)/', $prop) ? array() : "";
            }
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
            foreach ($value as $val) {
                $this->add_{$property}($val);
            }
        } elseif (preg_match('/^(address|phone_number)/', $property)) {
            $this->add_contact_info($value);
        } elseif (property_exists($this, $property) && $property !== 'db') {
            $this->{$property} = $this->db->sanitizeInput($value);
        }
    }

    public function get_contact_info($type, $contact_id = -1) {
        if ($contact_id < 1) {
            $contact_id = $this->id;
        }
        $info = $type === 'address' ? new ContactAddress(-1, $contact_id) : new ContactPhoneNumber(-1, $contact_id);
        $this->set($type, $info->get_all_{$type}());
        return $this->{$type};
    }

    public function add_contact_info($contact_info) {
        if (get_class($contact_info) == "ContactAddress") {
            $this->address[] = $contact_info;
        } elseif (get_class($contact_info) == "ContactPhoneNumber") {
            $phone_type = $contact_info->phone_type;
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
                continue;
            }
            foreach ($key as &$v) {
                $v->set('contact_id', $this->id);
                $v->set('phone_type', $key);
                $v->create_contact_{$type}();
            }
        }
        return $this->{$type};
    }

    public function delete_contact_info($contact_info) {
        if (preg_match('/^(ContactAddress|ContactPhoneNumber)/', get_class($contact_info)) && (in_array($contact_info, $this->address) || in_array($contact_info->phone_type, $this->phone_number))) {
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
    }

    public function create_contact() {
        $columns = $values = array();
        $required = array('first_name', 'address', 'phone_number');

        foreach (get_object_vars($this) as $prop => $val) {
            if ($prop !== 'id' && $prop !== 'db' && (!isset($val) || empty($val) || $val < 0) && in_array($prop, $required)) {
                return null;
            } elseif ($prop !== 'id' && $prop !== 'db') {
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
        $contact_list = array();
        $q = "";

        foreach (get_object_vars($this) as $prop => $val) {
            $q .= $prop !== 'db' && ($summary && !preg_match('/^(email|notes)/', $prop)) ? "`{$prop}` " : "";
        }

        $query = "SELECT {$q}FROM `{$table}`";

        while ($row = $this->db->select_assoc($query)) {
            $contact_list[] = new Contact($row['id'], $row['first_name'], $row['middle_name'], $row['last_name'], null, null, $row['email'], $row['notes']);
        }

        if (!$summary) {
            foreach ($contact_list as &$contact) {
                $contact->set('address', $contact->get_contact_info('address'));
                $contact->set('phone_numbers', $contact->get_contact_info('phone_numbers'));
            }
        }

        if (is_array($contact_list)) {
            usort($contact_list, array($this, "compare_contacts"));
        }

        return $contact_list;
    }

    public function get_contact() {
        $contact = new Contact();
        if (isset($this->id) && $this->id > 0) {
            $contact = $this->retrieve_contact_by_id($this->id, false);
        } else {
            $contacts = $this->search_contact();
            $last_id = count($contacts) - 1;

            $contact = $contacts[$last_id];
        }
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

        if ($only_contact) {
            return $contact_ids;
        }

        $contact_addr_ids = $this->search_contact_id_by_addresses($contact_ids);
        $contact_phone_ids = $this->search_contact_id_by_phone_number($contact_addr_ids);

        return $contact_phone_ids;
    }

    private function search_contact_id_by_addresses($contact_ids = array()) {
        if (isset($this->address) && !empty($this->address)) {
            $addr_cust_ids = array();
            foreach ($this->address as $address) {
                $addrs = $address->get_all_contact_addresses();
                if (is_array($addrs)) {
                    foreach ($addrs as $addr) {
                        if (isset($addr) && $addr->contact_id > 0) {
                            $addr_cust_ids[] = $addr->contact_id;
                        }
                    }
                }
            }
            if (!empty($addr_cust_ids)) {
                $contact_ids = empty($contact_ids) ? $addr_cust_ids : array_intersect($contact_ids, $addr_cust_ids);
            }
        }
        return $contact_ids;
    }

    private function search_contact_id_by_phone_number($contact_ids = array()) {
        if (isset($this->phone_number)) {
            $phone_cust_ids = array();
            foreach ($this->phone_number as $phone_number) {
                if (isset($phone_number) && !empty($phone_number)) {
                    foreach ($phone_number as $number) {
                        $nums = $number->get_all_contact_phone_numbers();
                        if (is_array($nums)) {
                            foreach ($nums as $num) {
                                if (isset($num) && $num->contact_id >
                                        0) {
                                    $phone_cust_ids[] = $num->contact_id;
                                }
                            }
                        }
                    }
                }
            }
            if (!empty($phone_cust_ids)) {
                $contact_ids = empty($contact_ids) ? $phone_cust_ids : array_intersect($contact_ids, $phone_cust_ids);
            }
        }
        return $contact_ids;
    }

    private function retrieve_contacts_by_ids($contact_ids = array(), $summary = true) {
        if (!empty($contact_ids)) {
            $contacts = array();

            foreach ($contact_ids as $contact_id) {
                $contacts[] = $this->retrieve_contact_by_id($contact_id, $summary);
            }
            return $contacts;
        }
    }

    private function retrieve_contact_by_id($contact_id, $summary = true) {
        $table = $this->db->camelToUnderscore(get_class($this));
        $contact_id = isset($contact_id) ? $contact_id : $this->id;
        if ($summary) {
            $query = "SELECT `first_name`, `middle_name`, `last_name` FROM `{$table}` WHERE `id`={$contact_id}";

            $row = $this->db->select_assoc($query);
            return new Contact($this->db->sanitizeOutput($contact_id), $this->db->sanitizeOutput($row['first_name']), $this->db->sanitizeOutput($row['middle_name']), $this->db->sanitizeOutput($row['last_name']));
        } else {
            $addr = new ContactAddress(-1, $contact_id);
            $address = $addr->get_all_contact_addresses();
            $phone_num = new ContactPhoneNumber(-1, $contact_id);
            $phone_number = $phone_num->get_all_contact_phone_numbers();
            $query = "SELECT `first_name`, `middle_name`, `last_name`, `email`, `notes` FROM `{$table}` WHERE `id`={$contact_id}";

            $row = $this->db->select_assoc($query);
            return new Contact($this->db->sanitizeOutput($contact_id), $this->db->sanitizeOutput($row['first_name']), $this->db->sanitizeOutput($row['middle_name']), $this->db->sanitizeOutput($row['last_name']), $address, $phone_number, $this->db->sanitizeOutput($row['email']), $this->db->sanitizeOutput($row['notes']));
        }
    }

    public function update_contact() {
        if (isset($this->id) && $this->id > 0 && !empty($this->first_name) &&
                !empty($this->last_name) && !empty($this->address) && !empty($this->phone_number['work'])) {
            $temp_contact = $this->search_contact();
            $table = $this->db->camelToUnderscore(get_class($this));
            $query = "UPDATE `{$table}` SET `first_name`='{$this->first_name}',`middle_name`='{$this->middle_name}',`last_name`='{$this->last_name}',`email`='{$this->email}',`notes`='{$this->notes}' WHERE `id`={$this->id}";
            $this->db->update($query);
            $GLOBALS['tracking']->add_event("Modified {$this->first_name} {$this->middle_name} {$this->last_name}", $this, $this->id);

            $address_ids = array();
            foreach ($this->address as $address) {
                if ($address->id > 0) {
                    $address_ids[] = $address->id;
                }
            }

            $temp_addr = new ContactAddress(-1, $this->id);
            $addresses = $temp_addr->get_all_contact_addresses();
            foreach ($addresses as &$address) {
                if (!in_array($address->id, $address_ids)) {
                    $address->delete_contact_address();
                }
            }

            $addresses_cnt = count($this->address);

            for ($i = 0; $i < $addresses_cnt; ++$i) {
                if ($this->address[$i]->id > 0) {
                    $this->address[$i]->update_contact_address();
                } else {
                    $this->address[$i]->create_contact_address();
                }
            }

            $phone_ids = array();
            foreach ($this->phone_number as $type => $phone_number) {
                foreach ($phone_number as $number) {
                    if ($number->id > 0) {
                        $phone_ids[] = $number->id;
                    }
                }
            }

            $temp_phone = new ContactPhoneNumber(-1, $this->id);
            $phones = $temp_phone->get_all_contact_phone_numbers();

            foreach ($phones as &$phone) {
                if (!in_array($phone->id, $phone_ids)) {
                    $phone->delete_contact_phone_number();
                }
            }

            foreach ($this->phone_number as $phone_type => &$phone_number) {
                $numbers_cnt = count($phone_number);
                for ($i = 0; $i < $numbers_cnt; ++$i) {
                    if ($phone_number[$i]->id > 0) {
                        $phone_number[$i]->update_contact_phone_number();
                    } else {
                        $phone_number[$i]->create_contact_phone_number();
                    }
                }
            }
            return $this;
        }
    }

    public function delete_contact() {
        if (isset($this->id) && $this->id > 0) {
            foreach ($this->address as &$address) {
                $address->delete_contact_address();
            }

            foreach ($this->phone_number as $phone_type => &$phone_number) {
                foreach ($phone_number as &$number) {
                    $number->delete_contact_phone_number();
                }
            }

            $table = $this->db->camelToUnderscore(get_class($this));
            $query = "DELETE FROM `{$table}`
                                WHERE `id`={$this->id}";
            $this->db->delete($query);
            $GLOBALS['tracking']->add_event("Deleted {$this->first_name} {$this->middle_name} {$this->last_name}", $this, $this->id);
            return $this;
        }
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
            if ($prop === 'db' && $args[$i] instanceof PDO) {
                $this->db = $db;
            } elseif (!preg_match('/^(db|id|contact_id)/', $prop) && ++$i < $count && isset($args[$i]) && !empty($args[$i])) {
                $this->set($prop, $args[$i]);
            } elseif (!preg_match('/^(db|id|contact_id)/', $prop)) {
                $this->{$prop} = "";
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
            $this->{$property} = $this->db->sanitizeInput($value);
        }
    }

    public function create_contact_address() {
        if (isset($this->contact_id) && $this->contact_id > 0 && !empty($this->street) && !empty($this->city) && !empty($this->province) && !empty($this->country) && !empty($this->postal_code)) {
            $table = $this->db->camelToUnderscore(get_class($this));
            $query = "INSERT INTO `{$table}` (`contact_id`,`street`,`city`,`province`,`country`,`postal_code`) VALUES ({$this->contact_id},'{$this->street}','{$this->city}','{$this->province}','{$this->country}','{$this->postal_code}')";
            $this->db->insert($query);
            $GLOBALS['tracking']->add_event("Created {$this->street}, {$this->city}, {$this->province}, {$this->country}, {$this->postal_code}", $this, $this->contact_id);
            return $this;
        }
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

    public function get_all_contact_addresses() {
        $addresses = array();
        if (isset($this->id) && $this->id > 0) {
            $addresses[] = $this->retrieve_address_by_id();
        } else {
            $addresses = $this->search_contact_address();
        }
        return $addresses;
    }

    public function get_contact_address() {
        $address = new ContactAddress();
        if (isset($this->id) && $this->id > 0) {
            $address = $this->retrieve_address_by_id();
        } else {
            $addr = $this->search_contact_address();
            if (count($addr) > 0) {
                $address = $addr[count($addr) - 1];
            }
        }
        if ($address->id > 0) {
            $this->set('id', $address->id);
            $this->set('contact_id', $address->contact_id);
            $this->set('street', $address->street);
            $this->set('city', $address->city);
            $this->set('province', $address->province);
            $this->set('country', $address->country);
            $this->set('postal_code', $address->postal_code);
            return $this;
        }
    }

    private function search_contact_address() {
        $addresses = array();

        $need_value = array();
        $have_value = array();

        if (isset($this->contact_id) && !empty($this->contact_id) && $this->contact_id >
                0) {
            $have_value[] = "`contact_id`={$this->contact_id}";
        } else {
            $need_value[] = "`contact_id`";
        }

        if (isset($this->street) && !empty($this->street)) {
            $have_value[] = "`street` LIKE '%{$this->street}%'";
        } else {
            $need_value[] = "`street`";
        }

        if (isset($this->city) && !empty($this->city)) {
            $have_value[] = "`city` LIKE '%{$this->city}%'";
        } else {
            $need_value[] = "`city`";
        }

        if (isset($this->province) && !empty($this->province)) {
            $have_value[] = "`province`='{$this->province}'";
        } else {
            $need_value[] = "`province`";
        }

        if (isset($this->country) && !empty($this->country)) {
            $have_value[] = "`country` LIKE '%{$this->country}%'";
        } else {
            $need_value[] = "`country`";
        }

        if (isset($this->postal_code) && !empty($this->postal_code)) {
            $have_value[] = "`postal_code` LIKE '%{$this->postal_code}%'";
        } else {
            $need_value[] = "`postal_code`";
        }

        if (!empty($have_value)) {
            $table = $this->db->camelToUnderscore(get_class($this));
            $needs = empty($need_value) ? "" : ", " . implode(", ", $need_value);
            $have = implode(" AND ", $have_value);
            $query = "SELECT `id`{$needs} FROM `{$table}` WHERE {$have}";


            while ($row = $this->db->select_assoc($query)) {
                $id = isset($row['id']) ? $row['id'] : $this->id;
                $contact_id = isset($row['contact_id']) ? $row['contact_id'] : $this->contact_id;
                $street = isset($row['street']) ? $row['street'] : $this->street;
                $city = isset($row['city']) ? $row['city'] : $this->city;
                $province = isset($row['province']) ? $row['province'] : $this->province;
                $country = isset($row['country']) ? $row['country'] : $this->country;
                $postal_code = isset($row['postal_code']) ? $row['postal_code'] : $this->postal_code;
                $addresses[] = new ContactAddress($this->db->sanitizeOutput($id), $this->db->sanitizeOutput($contact_id), $this->db->sanitizeOutput($street), $this->db->sanitizeOutput($city), $this->db->sanitizeOutput($province), $this->db->sanitizeOutput($country), $this->db->sanitizeOutput($postal_code));
            }
            return $addresses;
        }
    }

    private function retrieve_address_by_id($id = null) {
        $table = $this->db->camelToUnderscore(get_class($this));
        $id = isset($id) ? $id : $this->id;
        $query = "SELECT `contact_id`,`street`,`city`,`province`,`country`,`postal_code` FROM `{$table}` WHERE `id`={$id}";
        $row = $this->db->select_assoc($query);
        return new ContactAddress($id, $this->db->sanitizeOutput($row['contact_id']), $this->db->sanitizeOutput($row['street']), $this->db->sanitizeOutput($row['city']), $this->db->sanitizeOutput($row['province']), $this->db->sanitizeOutput($row['country']), $this->db->sanitizeOutput($row['postal_code']));
    }

    public function update_contact_address() {
        if (isset($this->id) && $this->id > 0 && isset($this->contact_id) && $this->contact_id > 0 && !empty($this->street) && !empty($this->city) && !empty($this->country) && !empty($this->postal_code)) {
            $table = $this->db->camelToUnderscore(get_class($this));
            $query = "UPDATE `{$table}` SET `street`='{$this->street}',`city`='{$this->city}',`province`='{$this->province}', `country`='($this->country}', `postal_code`='{$this->postal_code}' WHERE `id`={$this->id}";
            $this->db->update($query);
            $GLOBALS['tracking']->add_event("Modified {$this->street}, {$this->city}, {$this->province}, {$this->country}, {$this->postal_code}", $this, $this->contact_id);
            return $this;
        }
    }

    public function delete_contact_address() {
        if (isset($this->id) && $this->id > 0) {
            $table = $this->db->camelToUnderscore(get_class($this));
            $query = "DELETE FROM `{$table}` WHERE `id`={$this->id}";
            $this->db->delete($query);
            $GLOBALS['tracking']->add_event("Deleted {$this->street}, {$this->city}, {$this->province}, {$this->country}, {$this->postal_code}", $this, $this->contact_id);
            return $this;
        }
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
            if ($prop === 'db' && $args[$i] instanceof PDO) {
                $this->db = $db;
            } elseif (!preg_match('/^(db|id|contact_id)/', $prop) && ++$i < $count && isset($args[$i]) && !empty($args[$i])) {
                $this->set($prop, $args[$i]);
            } elseif (!preg_match('/^(db|id|contact_id)/', $prop)) {
                $this->{$prop} = "";
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
            $this->{$property} = $this->db->sanitizeInput($value);
        }
    }

    public function create_contact_phone_number() {
        if (isset($this->contact_id) && $this->contact_id >= 0 && !empty($this->phone_type) && !empty($this->phone_number)) {
            $table = $this->db->camelToUnderscore(get_class($this));
            $query = "INSERT INTO `{$table}` (`contact_id`,`phone_type`,`phone_number`) VALUES ({$this->contact_id},'{$this->phone_type}','{$this->phone_number}')";
            $this->db->insert($query);
            $GLOBALS['tracking']->add_event("Created {$this->phone_number}", $this, $this->contact_id);
            return $this;
        }
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

    public function get_all_contact_phone_numbers() {
        $phone_numbers = array();
        if (isset($this->id) && $this->id > 0) {
            $phone_numbers[] = $this->retrieve_contact_phone_number_by_id();
        } else {
            $phone_numbers = $this->search_contact_phone_number();
        }
        return $phone_numbers;
    }

    public function get_contact_phone_number() {
        $phone_number = new ContactPhoneNumber();
        if (isset($this->id) && $this->id > 0) {
            $phone_number = $this->retrieve_phone_numbers_by_id();
        } else {
            $phone_num = $this->search_contact_phone_number();
            if (count($phone_num) > 0) {
                $phone_number = $phone_num[count($phone_num) - 1];
            }
        }
        if ($phone_number->id > 0) {
            $this->set('id', $phone_number->id);
            $this->set('contact_id', $phone_number->contact_id);
            $this->set('phone_type', $phone_number->phone_type);
            $this->set('phone_number', $phone_number->phone_number);
            return $this;
        }
    }

    private function search_contact_phone_number() {
        $phone_numbers = array();
        $need_value = array();
        $have_value = array();

        if (isset($this->contact_id) && $this->contact_id > 0) {
            $have_value[] = "`contact_id`={$this->contact_id}";
        } else {
            $need_value[] = "`contact_id`";
        }

        if (isset($this->phone_type) && !empty($this->phone_type)) {
            $have_value[] = "`phone_type`='{$this->phone_type}'";
        } else {
            $need_value[] = "`phone_type`";
        }

        if (isset($this->phone_number) && !empty($this->phone_number)) {
            $have_value[] = "`phone_number` LIKE '%{$this->phone_number}%'";
        } else {
            $need_value[] = "`phone_number`";
        }

        if (!empty($have_value)) {
            $table = $this->db->camelToUnderscore(get_class($this));
            $needs = empty($need_value) ? "" : ", " . implode(", ", $need_value);
            $have = implode(" AND ", $have_value);
            $query = "SELECT `id`{$needs} 
                            FROM `{$table}`
                           WHERE {$have}";

            while ($row = $this->db->select_assoc($query)) {
                $id = isset($row['id']) ? $row['id'] : $this->id;
                $contact_id = isset($row['contact_id']) ? $row['contact_id'] : $this->contact_id;
                $phone_type = isset($row['phone_type']) ? $row['phone_type'] : $this->phone_type;
                $phone_number = isset($row['phone_number']) ? $row['phone_number'] : $this->phone_number;
                $phone_numbers[] = new ContactPhoneNumber($this->db->sanitizeOutput($id), $this->db->sanitizeOutput($contact_id), $this->db->sanitizeOutput($phone_type), $this->db->sanitizeOutput($phone_number));
            }

            return $phone_numbers;
        }
    }

    private function retrieve_contact_phone_number_by_id($id = null) {
        $table = $this->db->camelToUnderscore(get_class($this));
        $id = isset($id) ? $id : $this->id;
        $query = "SELECT `contact_id`, `phone_type`, `phone_number`
                        FROM `{$table}`
                       WHERE `id`={$id}";
        $row = $this->db->select_assoc($query);
        return new ContactPhoneNumber($id, $this->db->sanitizeOutput($row['contact_id']), $this->db->sanitizeOutput($row['phone_type']), $this->db->sanitizeOutput($row['phone_number']));
    }

    public function update_contact_phone_number() {
        if (isset($this->id) && $this->id > 0 && isset($this->contact_id) && $this->contact_id > 0 && !empty($this->phone_type) && !empty($this->phone_number)) {
            $table = $this->db->camelToUnderscore(get_class($this));
            $query = "UPDATE `{$table}`
                             SET `phone_type`='{$this->phone_type}',`phone_number`='{$this->phone_number}' 
                           WHERE `id`={$this->id}";
            $this->db->update($query);
            $GLOBALS['tracking']->add_event("Modified {$this->phone_number}", $this, $this->contact_id);
            return $this;
        }
    }

    public function delete_contact_phone_number() {
        if (isset($this->id) && $this->id > 0) {
            $table = $this->db->camelToUnderscore(get_class($this));
            $query = "DELETE FROM `{$table}`
                                WHERE `id`={$this->id}";
            $this->db->delete($query);
            $GLOBALS['tracking']->add_event("Deleted {$this->phone_number}", $this, $this->contact_id);
            return $this;
        }
    }

}
