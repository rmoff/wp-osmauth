<?php

/*
 
Plugin Name: Osm Auth
 
Plugin URI: 
 
Description: Plugin to allow authentication through OSM.
 
Version: 1.0
 
Author: Smither
 
Author URI: 
 
License: GPLv2 or later
 
Text Domain: smither
 
*/

// Hook the appropriate WordPress action

add_action('init', 'prevent_wp_login');
include_once("template/LoginTemplaterRegister.php");

function get_var($system, $var)
{
    return OSM_AUTH_SETTINGS[$system][$var];
}

function getTokens($system, $authorization_code)
{
    $content = "grant_type=authorization_code&client_id=" . get_var($system, "client_id") . "&client_secret=" . get_var($system, "client_secret") . "&code=$authorization_code&redirect_uri=" . get_var($system, "redirect");
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => get_var($system, "token") . "?" . $content,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_RETURNTRANSFER => true,
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    if ($response === false) {
        echo "Failed";
        echo curl_error($curl);
        echo "Failed";
    } elseif (json_decode($response)->error) {
        echo "Error:<br />";
        echo $authorization_code;
        echo $response;
    }
    return json_decode($response);
}

function refreshTokens($system, $user)
{
    $linked_accounts = $user->get("linked_accounts");
    foreach ($linked_accounts as $id => $account) {
        if ($system == $system) {
            $content = "grant_type=refresh_token&client_id=" . get_var($system, "client_id") . "&client_secret=" . get_var($system, "client_secret") . "&refresh_token=" . $account["refresh_token"] . "&redirect_uri=" . get_var($system, "redirect");
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => get_var($system, "token") . "?" . $content,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_RETURNTRANSFER => true,
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            if ($response === false) {
                echo "Failed";
                echo curl_error($curl);
                echo "Failed";
            } elseif (json_decode($response)->error) {
                echo "Error:<br />";
                echo $account["refresh_token"];
                echo $response;
            }
            $tokens = json_decode($response);
            $decoded = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $tokens->access_token)[1]))));
            $access_expiry = $decoded->exp;
            $linked_accounts[$id] = array(
                "system" => $account["system"],
                "access_token" => $tokens->access_token,
                "access_expiry" => $access_expiry,
                "refresh_token" => $tokens->refresh_token
            );
        }
    }
    update_user_meta($user->ID, 'linked_accounts', $linked_accounts);
    clean_user_cache($user->ID);
}

function callOSMEndpointWithSessionId($system, $endpoint, $session_id=NULL, $access_token = NULL)
{    
    global $current_user;
    if (!$access_token) {
        $linked_accounts = $current_user->get('linked_accounts');
        foreach ($linked_accounts as $id => $account) {
            refreshTokens($account["system"], $current_user);
        }
        $linked_accounts = $current_user->get('linked_accounts');
        foreach ($linked_accounts as $id => $account) {
            $access_token = $account["access_token"];
        }
    }
    $curl = curl_init();
    $cookie_header=is_null($session_id)?[]:["Cookie: OYM=3~$session_id"];
    curl_setopt_array($curl, array(
        CURLOPT_URL => get_var($system, "base") . $endpoint,
        CURLOPT_POST => TRUE,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array_merge(array(
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/x-www-form-urlencoded'
        ),$cookie_header),
        CURLOPT_RETURNTRANSFER => TRUE
    ));
    $response = curl_exec($curl);
    curl_close($curl);
    return $response;
}
function callOSMEndpoint($system, $endpoint, $access_token = NULL)
{
    return callOSMEndpointWithSessionId($system, $endpoint, NULL, $access_token);
}
function get_osm_data($access_token = NULL)
{
    $raw = callOSMEndpoint("OSM", "/ext/generic/startup/?action=getData", $access_token);
    $data = json_decode(ltrim($raw, "var data_holder = "));
    return $data;
}
function get_data($system, $access_token = NULL)
{
    $raw = callOSMEndpoint($system, "/ext/generic/startup/?action=getData", $access_token);
    $data = json_decode(ltrim($raw, "var data_holder = "));
    return $data;
}
function random_str(
    $length,
    $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"£$%^&*()_+-={}[]:@~;\'#<>?,./\|'
) {
    $str = '';
    $max = mb_strlen($keyspace, '8bit') - 1;
    if ($max < 1) {
        throw new Exception('$keyspace must be at least two characters long');
    }
    for ($i = 0; $i < $length; ++$i) {
        $str .= $keyspace[random_int(0, $max)];
    }
    return $str;
}
function osm_login_url()
{
    $redirect = (isset($_GET['redirect_to'])) ? $_GET['redirect_to'] : home_url();
    $redirect = (isset($_GET['state'])) ? base64_decode($_GET['state']) : $redirect;
    $state = base64_encode(json_encode(array(
        'system' => 'OSM',
        'redirect' => $redirect
    )));
    echo get_var("OSM", "base") . "/oauth/authorize/?response_type=code&client_id=" . get_var("OSM", "client_id") . "&redirect_uri=" . get_var("OSM", "redirect") . "&state=$state";
}
function ogm_login_url()
{
    $redirect = (isset($_GET['redirect_to'])) ? $_GET['redirect_to'] : home_url();
    $redirect = (isset($_GET['state'])) ? base64_decode($_GET['state']) : $redirect;
    $state = base64_encode(json_encode(array(
        'system' => 'OGM',
        'redirect' => $redirect
    )));
    echo get_var("OGM", "base") . "/oauth/authorize/?response_type=code&client_id=" . get_var("OGM", "client_id") . "&redirect_uri=" . get_var("OGM", "redirect") . "&state=$state";
}

function refresh_user_roles()
{
    $user = wp_get_current_user();
    $linked_accounts = $user->get('linked_accounts');
    foreach ($linked_accounts as $id => $account) {
        refreshTokens($account["system"], $user);
    }
    $user = wp_get_current_user();
    $linked_accounts = $user->get('linked_accounts');
    $parent_sections = array();
    $leader_sections = array();
    foreach ($linked_accounts as $id => $account) {
        $section_ids = get_var($account["system"], "section_ids");
        $data = get_data($account["system"], $account["access_token"]);
        $all_parent_sections = (array)($data->globals->member_access);
        $parent_sections = array_replace($parent_sections, array_filter(
            $all_parent_sections,
            fn ($key) => in_array($key, $section_ids),
            ARRAY_FILTER_USE_KEY
        ));
        if ($data->globals->roles != null) {
            $leader_sections = array_replace($leader_sections, array_filter($data->globals->roles, function ($role) use ($section_ids) {
                return in_array($role->sectionid, $section_ids, true);
            }));
        }
    }
    update_user_meta($user->ID, 'nickname', $data->globals->fullname);
    update_user_meta($user->ID, 'first_name', $data->globals->firstname);
    update_user_meta($user->ID, 'last_name', $data->globals->lastname);
    update_user_meta($user->ID, 'display_name', $data->globals->firstname . " " . substr($data->globals->lastname, 0, 1));
    $userdata = array(
        'ID' => $user->ID,
        'display_name' => $data->globals->firstname . " " . substr($data->globals->lastname, 0, 1),
    );
    wp_update_user($userdata);
    $roles = in_array("administrator", $user->roles) ? "administrator" : "";
    $user->set_role($roles);
    foreach ($parent_sections as $section_id => $section_info) {
        $parent_role = get_role("{$section_id}_parent");
        if (!$parent_role) {
            add_role("{$section_id}_parent", "{$section_info->label} Parent", []);
        }
        $user->add_role("{$section_id}_parent");
    }
    foreach ($leader_sections as $section_info) {
        if ($section_info->permissions->user === 100) {
            $leader_role = get_role("{$section_info->sectionid}_admin");
            if (!$leader_role) {
                add_role("{$section_info->sectionid}_admin", "{$section_info->sectionname} Admin", []);
            }
            $user->add_role("{$section_info->sectionid}_admin");
        }
        $leader_role = get_role("{$section_info->sectionid}_leader");
        if (!$leader_role) {
            add_role("{$section_info->sectionid}_leader", "{$section_info->sectionname} Leader", []);
        }
        $user->add_role("{$section_info->sectionid}_leader");
        foreach (["category" => ["public", "parent", "leader"], "attachment_category" => ["public", "parent", "leader"]] as $taxonomy => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (!term_exists($section_info->sectionid . "_" . $prefix, $taxonomy)) {
                    wp_insert_term(
                        $prefix . " " . $section_info->sectionname,   // the term name
                        $taxonomy, // the taxonomy
                        array(
                            'description' => $prefix . " " . $section_info->sectionname,
                            'slug'        => $section_info->sectionid . "_" . $prefix,
                            'parent'      => (int)term_exists($prefix, $taxonomy)["term_id"],
                        )
                    );
                }
            }
        }
    }
    // echo ("<script>console.log(`post leader section iterator`)</script>");
    clean_user_cache($user->ID);
}

function prevent_wp_login()
{
    global $wp;
    $wp->add_query_var('err');
    // WP tracks the current page - global the variable to access it
    global $pagenow;
    if ($pagenow !== 'wp-login.php' || $_SERVER['REQUEST_METHOD'] === 'POST') {
        return;
    }
    // Check if a $_GET['action'] is set, and if so, load it into $action variable
    $action = (isset($_GET['action'])) ? $_GET['action'] : false;
    $code = (isset($_GET['code'])) ? $_GET['code'] : false;
    $redirect = (isset($_GET['redirect_to'])) ? $_GET['redirect_to'] : home_url();
    $state = json_decode(base64_decode($_GET['state']));
    $redirect = (isset($state->redirect)) ? $state->redirect : $redirect;
    $section_ids = get_var($state->system, "section_ids");
    if ($code) {
        $tokens = getTokens($state->system, $code);
        $decoded = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $tokens->access_token)[1]))));
        $access_expiry = $decoded->exp;
        $username = $decoded->sub;
        $user = get_users(array(
            'meta_key' => 'linked_account',
            'meta_value' => $state->system . "_" . $username,
            'number' => 1
        ));
        // echo ("<script>console.log('" . print_r(sizeof($user), true) . "')</script>");
        $user = ((sizeof($user) >= 1) ? $user[0] : FALSE);
        // echo ("<script>console.log(`" . print_r($user, true) . "`)</script>");
        $data = get_data($state->system, $tokens->access_token);
        // print_r($data->globals);
        $all_parent_sections = $data->globals->member_access;
        $parent_sections = array_filter(
            (array) $all_parent_sections,
            function ($key) use ($section_ids) {
                return in_array($key, $section_ids);
            },
            ARRAY_FILTER_USE_KEY
        );
        $leader_sections = array_filter($data->globals->roles, function ($role) use ($section_ids) {
            return in_array($role->sectionid, $section_ids, true);
        });
        // print_r($section_ids);
        // print_r($parent_sections);
        // print_r($leader_sections);
        if (!$parent_sections && !$leader_sections) {
            if ($user) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
                wp_delete_user($user);
            }
            wp_clear_auth_cookie();
            wp_redirect("/login?err=noSections");
            die;
        } else {
            // If user doesn't exist, create it.
            if (!$user) {
                $user = get_user_by("ID", wp_create_user($data->globals->email, random_str(128), $data->globals->email));
            }
            $linked_accounts = get_user_meta($user->ID, "linked_accounts", true);
            // echo ("<script>console.log(`" . print_r($linked_accounts, true) . "`)</script>");
            if (!$linked_accounts) {
                $linked_accounts = array();
            }
            if (!in_array($state->system . "_" . $username, get_user_meta($user->ID, "linked_account"))) {
                add_user_meta($user->ID, "linked_account", $state->system . "_" . $username);
            }
            $linked_accounts[$state->system . "_" . $username] = array(
                "system" => $state->system,
                "access_token" => $tokens->access_token,
                "access_expiry" => $access_expiry,
                "refresh_token" => $tokens->refresh_token
            );
            update_user_meta($user->ID, 'linked_accounts', $linked_accounts);
            // echo ("<script>console.log(`pre update user meta`)</script>");
            refresh_user_roles();
            // echo ("<script>console.log(`post leader section iterator`)</script>");
            clean_user_cache($user->ID);
            wp_clear_auth_cookie();
            do_action('wp_login', $data->globals->email);
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID, true);
            update_user_caches($user);
            wp_redirect($redirect);
            // echo ("<script>console.log(`end`)</script>");
            exit;
        }
    } elseif (!is_user_logged_in() && (!$action || ($action && !in_array($action, array('logout'))))) { // Check if we're on the login page, and ensure the action is not 'logout'   
        // Load the home page url
        // Redirect to the home page
        $state = base64_encode(json_encode(array(
            'system' => 'OSM',
            'redirect' => $redirect
        )));
        wp_redirect("/login");
        // Stop execution to prevent the page loading for any reason
        die;
    } elseif (is_user_logged_in() && (!$action || ($action && !in_array($action, array('logout'))))) {
        wp_redirect($redirect);
    } elseif (is_user_logged_in() && $action && in_array($action, array('logout'))) {
        $session_id=$get_osm_data()["globals"]["session_id"]
        throw new ErrorException(callOSMEndpointWithSessionId("OSM", "/ext/users/auth/?action=logout",$session_id));
        callOSMEndpoint("OSM", "/v3/settings/oauth/access/1240/delete");
        wp_logout();
        wp_redirect($redirect);
        die;
    }
}

add_filter('login_url', 'remove_wp_login', 10, 3);
function remove_wp_login()
{
    if ($_SERVER['REQUEST_URI'] != '/wp-login.php') return;
    if ($_SERVER['METHOD'] != 'GET') return;
    header('Location: /login');
    exit;
}

add_action('wp_logout', 'auto_redirect_after_logout');

function auto_redirect_after_logout()
{
    wp_safe_redirect(home_url());
    exit;
}

include plugin_dir_path(__FILE__) . 'settings.php';
include_once plugin_dir_path(__FILE__) . 'SecureRestrictions.php';
