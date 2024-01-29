<?php

class User
{

    // GENERAL

    public static function user_info($d)
    {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='" . $user_id . "'";
        else if ($phone) $where = "phone='" . $phone . "'";
        else return [];
        // info
        $q = DB::query("SELECT user_id, phone, access FROM users WHERE " . $where . " LIMIT 1;") or die(DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access']
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0
            ];
        }
    }

    public static function user_full_info($user_id)
    {
        $item = NULL;
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, email, phone, access FROM users WHERE user_id=" . $user_id . ";") or die(DB::error());
        if ($row = DB::fetch_row($q)) {
            $item = [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone']),
            ];
            $plot_ids = $item['plot_id'] ? explode(',', $item['plot_id']) : [0];
            $plots = [];
            $where = [];
            foreach ($plot_ids as $id)
                $where[] = 'plot_id=' . $id;
            $where = $where ? "WHERE " . implode(" OR ", $where) : '';
            $q = DB::query("SELECT plot_id, number, size, price FROM plots " . $where . " ORDER BY `number`;") or die(DB::error());
            while ($row = DB::fetch_row($q)) {
                $plots[] = [
                    'plot_id' => $row['plot_id'],
                    'number' => $row['number'],
                    'size' => $row['size'],
                    'price' => $row['price'],
                    'selected' => true,
                ];
            }
            $item['plots'] = $plots;
        }
        return $item;
    }

    public static function users_list_plots($number)
    {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%" . $number . "%' ORDER BY user_id;") or die(DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach ($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    public static function users_list_all()
    {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, email, phone, last_login
            FROM users ORDER BY user_id;") or die(DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone']),
                'last_login' => date('Y-m-d h:i:s', $row['last_login']),
            ];
        }
        // output
        return $items;
    }

    public static function users_list($d = [])
    {
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];
        // where
        $where = [];
        if ($search) {
            $where[] = "CONCAT(first_name,' ',last_name) LIKE '%" . $search . "%'";
            $where[] = "phone LIKE '%" . $search . "%'";
            $where[] = "email LIKE '%" . $search . "%'";
        }
        $where = $where ? "WHERE " . implode(" OR ", $where) : "";
        //info
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, email, phone, last_login
            FROM users " . $where . " ORDER BY user_id LIMIT " . $offset . ", " . $limit . ";") or die(DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'numbers' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone']),
                'last_login' => date('Y-m-d h:i:s', $row['last_login']),
            ];
        }
        // paginator
        $q = DB::query("SELECT count(*) FROM users " . $where . ";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search=' . $search;
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function users_fetch($d = [])
    {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    public static function user_edit_window($d = [])
    {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $user = User::user_full_info($user_id);
        $plots = $user['plots'];
        unset($user['plots']);
        HTML::assign('user', $user);
        $query = DB::query("SELECT plot_id, number, size, price FROM plots WHERE status=0 ORDER BY `number`;");
        while ($row = DB::fetch_row($query)) {
            $plots[] = [
                'plot_id' => (int) $row['plot_id'],
                'number' => $row['number'],
                'size' => $row['size'],
                'price' => number_format($row['price'], 0, '', ' '),
                'selected' => false,
            ];
        }
        $plots = self::unique_multidim_array($plots, 'plot_id');
        HTML::assign('plots', $plots);
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = [])
    {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $first_name = isset($d['first_name']) && trim($d['first_name']) ? trim($d['first_name']) : '';
        $last_name = isset($d['last_name']) && trim($d['last_name']) ? trim($d['last_name']) : '';
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        $email = isset($d['email']) && trim($d['email']) ? trim($d['email']) : '';
        $email = mb_strtolower($email, 'UTF-8');
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        // update
        if ($user_id) {
            $set = [];
            $set[] = isset($d['plots']) ? "plot_id='" . implode(',', $d['plots']) . "'" : "plot_id=''";
            $set[] = "first_name='" . $first_name . "'";
            $set[] = "last_name='" . $last_name . "'";
            $set[] = "email='" . $email . "'";
            $set[] = "phone='" . $phone . "'";
            $set[] = "updated='" . Session::$ts . "'";
            $set = implode(", ", $set);
            DB::query("UPDATE users SET " . $set . " WHERE user_id='" . $user_id . "' LIMIT 1;") or die(DB::error());
        } else {
            $plots = isset($d['plots']) ? implode(',', $d['plots']) : '';
            DB::query("INSERT INTO users (
                village_id,
                plot_id,
                access,
                first_name,
                last_name,
                email,
                phone,
                phone_code,
                phone_attempts_code,
                phone_attempts_sms,
                updated,
                last_login
            ) VALUES (
                '1',
                '" . $plots . "',
                '1',
                '" . $first_name  . "',
                '" . $last_name . "',
                '" . $email  . "',
                '" . $phone . "',
                '1111',
                '0',
                '0',
                '" . Session::$ts . "',
                '0'
            );") or die(DB::error());
        }
        // output
        return User::users_fetch(['offset' => $offset]);
    }

    public static function verify_user_data($d = [])
    {
        $response = [];
        $first_name = isset($d['first_name']) && trim($d['first_name']) ? trim($d['first_name']) : '';
        $last_name = isset($d['last_name']) && trim($d['last_name']) ? trim($d['last_name']) : '';
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        $email = isset($d['email']) && trim($d['email']) ? trim($d['email']) : '';
        $email = mb_strtolower($email, 'UTF-8');

        $response['first_name'] = preg_match("/^[A-Za-z]([ \-]?[A-Za-z])*$/", $first_name) ? 1 : 0;
        $response['last_name'] = preg_match("/^[A-Za-z]([ \-]?[A-Za-z])*$/", $last_name) ? 1 : 0;
        $response['email'] = preg_match('/^((([0-9A-Za-z]{1}[-0-9A-z\.]{1,}[0-9A-Za-z]{1})|([0-9А-Яа-я]{1}[-0-9А-я\.]{1,}[0-9А-Яа-я]{1}))@([-A-Za-z]{1,}\.){1,2}[-A-Za-z]{2,})$/u', $email) ? 1 : 0;
        $response['phone'] = $phone >= 100000 ? 1 : 0;
        return json_encode($response);
    }

    public static function delete_user($d = [])
    {
        $response['status'] = 'error';
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        if ($user_id) {
            DB::query("DELETE FROM users WHERE user_id='" . $user_id . "' LIMIT 1;") or die(DB::error());
            $response['stutus'] = 'OK';
            return User::users_fetch(['offset' => $offset]);
        }
    }

    private static function unique_multidim_array($array, $key)
    {
        $temp_array = array();
        $key_array = array();

        foreach ($array as $val) {
            if (!in_array($val[$key], $key_array)) {
                $key_array[] = $val[$key];
                $temp_array[] = $val;
            }
        }
        return $temp_array;
    }
}
