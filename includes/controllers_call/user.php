<?php

function controller_user($act, $d)
{
    if ($act == 'edit_window') return User::user_edit_window($d);
    if ($act == 'edit_update') return User::user_edit_update($d);
    if ($act == 'verify_data') return User::verify_user_data($d);
    if ($act == 'delete_user') return User::delete_user($d);
    return '';
}
