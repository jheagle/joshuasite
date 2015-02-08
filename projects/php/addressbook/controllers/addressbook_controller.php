<?php

require_once($_SERVER["DOCUMENT_ROOT"] . '/models/customer_class.php');

if (!empty($_POST['first_name']) && !empty($_POST['last_name']) && !empty($_POST['address']) &&
        !empty($_POST['phone'])) {
    $customer_id = isset($_POST['customer_id']) ? $_POST['customer_id'] : -1;
    $addresses = array();
    $num_addresses = count($_POST['address']['postal']);
    foreach ($_POST['address'] as $key => $value) {
        if ((empty($value) && $key != "apartment") || count($value) != $num_addresses &&
                $key != "id") {
            header('Location: ../index.php');
            return;
        }
        foreach ($value as $val) {
            if (empty($val) && $key != "apartment") {
                header('Location: ../index.php');
                return;
            }
        }
    }
    for ($i = 0; $i < $num_addresses; ++$i) {
        $apartment = empty($_POST['address']['apartment'][$i]) ? "" : $_POST['address']['apartment'][$i] . "-";
        $street = $apartment . $_POST['address']['number'][$i] . " " . $_POST['address']['street'][$i];
        $address_id = isset($_POST['address']['id'][$i]) ? $_POST['address']['id'][$i] : -1;
        $addresses[] = new customer_address($address_id, $customer_id, $street, $_POST['address']['city'][$i], $_POST['address']['province'][$i], $_POST['address']['country'][$i], $_POST['address']['postal'][$i]);
    }
    $phones = array();
    $has_work_phone = FALSE;
    foreach ($_POST['phone']['type'] as $value) {
        if ($value == "work") {
            $has_work_phone = TRUE;
            break;
        }
    }
    if (!$has_work_phone || count($_POST['phone']['type']) != count($_POST['phone']['number'])) {
        header('Location: ../index.php');
        return;
    }
    for ($i = 0; $i < count($_POST['phone']['number']); ++$i) {
        $phone_id = isset($_POST['phone']['id'][$i]) ? $_POST['phone']['id'][$i] : -1;
        $phones[] = new customer_phone_number($phone_id, $customer_id, $_POST['phone']['type'][$i], $_POST['phone']['number'][$i]);
    }
    $customer = new customer($customer_id, $_POST['first_name'], $_POST['middle_name'], $_POST['last_name'], $addresses, $phones, $_POST['email'], $_POST['notes']);

    if (isset($_POST['customer_id']) && isset($_POST['address']['id']) && isset($_POST['phone']['id'])) {
        if (isset($_POST['submit']) && $_POST['submit'] === 'Update') {
            $customer->update_customer();
        } elseif (isset($_POST['submit']) && $_POST['submit'] === 'Delete') {
            $customer->delete_customer();
        }
    } elseif (isset($_POST['submit']) && $_POST['submit'] === 'Create') {
        $customer->create_customer();
    }

    $customers_json = array();
    $customer = new customer();
    $customers = $customer->get_all_customers();
    foreach ($customers as $cust) {
        $customers_json[] = $cust->get_as_json();
    }
    header('Content-Type: application/json');
    echo json_encode($customers_json);
    return;
}

if (isset($_POST['customer_id'])) {
    $customer = new customer($_POST['customer_id']);
    $customer = $customer->get_customer();
    header('Content-Type: application/json');
    echo json_encode($customer->get_as_json());
    return;
}
