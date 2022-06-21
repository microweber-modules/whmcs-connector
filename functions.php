<?php


if (!defined('MW_WHMCS_CONNECTOR_SETTINGS_FILE')) {
    define('MW_WHMCS_CONNECTOR_SETTINGS_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'settings.json');
    define('MW_WHMCS_CONNECTOR_SETTINGS_FILE_LOCAL', storage_path() . DIRECTORY_SEPARATOR . 'whmcs_connector.json');
}


event_bind('mw.admin.dashboard.main', function ($params = false) {


    $is_data = mw()->user_manager->session_get('mw_hosting_data');
    if ($is_data and is_array($is_data)) {
        //print '<module type="users/mw_login/hosting" />';
    }

});


event_bind('on_load', function ($params = false) {

});


event_bind('mw.user.before_login', function ($params = false) {
    return mw_whmcs_remote_user_login($params);
});

event_bind('mw.ui.admin.login.form.after', function ($params = false) {

    $brandingContent = @file_get_contents(MW_WHITE_LABEL_SETTINGS_FILE_LOCAL);
    $whiteLabelSettings = json_decode($brandingContent, TRUE);
    if (is_array($whiteLabelSettings)) {

        if (isset($whiteLabelSettings['external_login_server_enable']) && $whiteLabelSettings['external_login_server_enable'] == false) {
            return;
        }

    }

    $btn_url = mw_whmcs_remote_get_connector_url().'/index.php?m=microweber_addon&function=go_to_product&domain='. site_url();

    if (strpos(mw_root_path(), 'public_html') !== false) {
        $username_path = explode('public_html', mw_root_path());
        if (isset($username_path[0])) {
            $username_path = explode('/', $username_path[0]);
            if ($username_path) {
                $username_path = array_filter($username_path);
                if ($username_path) {
                    $username_path = array_pop($username_path);
                    if ($username_path) {
                        $btn_url = mw_whmcs_remote_get_connector_url().   '/index.php?m=microweber_addon&function=go_to_product&username2=' . $username_path . '&return_domain=' . site_url();
                    }
                }
            }
        }
    }
    
    if (strpos(mw_root_path(), 'httpdocs') !== false) {
    $domain_name_path = explode('httpdocs', mw_root_path());
        if (isset($domain_name_path[0])) {
            $domain_name_path = explode('/', $domain_name_path[0]);
            if ($domain_name_path) {
                $domain_name_path = array_filter($domain_name_path);
                if ($domain_name_path) {
                    $domain_name_path = array_pop($domain_name_path);
                    if ($domain_name_path) {
                        $btn_url = mw_whmcs_remote_get_connector_url().   '/index.php?m=microweber_addon&function=go_to_product&domain=' . $domain_name_path . '&return_domain=' . site_url();
                    }
                }
            }
        }
    }
    
    print "<center>";
    print "<h4>" . _e('OR', true). "</h4>";

   /* print "<h4>Use Microweber.com Account</h4>";
   */
    print "<br>";

    $login_button = _e('Login with your account', true);

    if (isset($whiteLabelSettings['external_login_server_button_text'])) {
        $login_button = $whiteLabelSettings['external_login_server_button_text'];
    }

    print '<a class="mw-ui-btn  mw-ui-btn-info mw-ui-btn-big" href="' . $btn_url . '"><span class="mw-icon-login"></span>'.$login_button.'</a>';
    print "</center>";


    return;
});


function mw_whmcs_remote_get_connector_url()
{
    $file = false;
    if (is_file(MW_WHMCS_CONNECTOR_SETTINGS_FILE_LOCAL)) {
        $file = MW_WHMCS_CONNECTOR_SETTINGS_FILE_LOCAL;
    } elseif (is_file(MW_WHMCS_CONNECTOR_SETTINGS_FILE)) {
        $file = MW_WHMCS_CONNECTOR_SETTINGS_FILE;
    }

    if (is_file($file)) {
        try {
            $response = app()->http->url($file)->get();
            $settings = @json_decode($response, true);
            if ($settings and isset($settings['whmcs_url'])) {
                return $settings['whmcs_url'];
            }

            if ($settings and isset($settings['url'])) {
                return $settings['url'];
            }
        }
        catch (\Exception $e) {

        }


    }

}


function mw_whmcs_remote_user_login($params = false)
{


    if ($params == false) {
        return;
    }
    if (!is_array($params)) {
        $params = parse_params($params);
    }
    $postfields = array();
    $postfields['action'] = 'validatelogin';
    if (isset($params['email'])) {
        $params['username'] = $params['email'];
    }

    if (!isset($params['username'])) {
        return false;
    }
    if (!isset($params['password'])) {
        return false;
    }
    $postfields = $params;
    $postfields["email"] = $params['username'];
    $postfields["password2"] = $params['password'];
    $postfields["domain"] = site_url();
 //dd($postfields);

    $result = mw_whmcs_remote_user_login_exec($postfields);
   // dd($result);
    if (isset($result['hosting_data'])) {
        mw()->user_manager->session_set('mw_hosting_data', $result['hosting_data']);
    }


    if (isset($result['result']) and $result['result'] == 'success' and isset($result['userid'])) {

        cache_clear('users');

        $check_if_exists = get_users('no_cache=1&one=1&email=' . $params['username']);
        if (!$check_if_exists) {
            $check_if_exists = get_users('no_cache=1&one=1&username=' . $params['username']);

        }
        if (!$check_if_exists) {
            $check_if_exists = get_users('no_cache=1&one=1&oauth_provider=mw_login&oauth_uid=' . intval($result['userid']));
        }

        $upd = array();
        if ($check_if_exists == false) {
            // $upd['id'] = 0;
        } else {
            $upd['id'] = $check_if_exists['id'];


        }
        if (is_array($check_if_exists) and isset($check_if_exists['is_active'])) {
            $upd['is_active'] = $check_if_exists['is_active'];
        } else {
            $upd['is_active'] = 1;
        }


        $upd['email'] = $params['username'];
        $upd['password'] = $params['password'];
        $upd['is_admin'] = 1;


        $upd['oauth_uid'] = $result['userid'];
        $upd['oauth_provider'] = 'mw_login';
        if (!defined('MW_FORCE_USER_SAVE')) {
            define('MW_FORCE_USER_SAVE',1);
        }

        $s = save_user($upd);


      //  dd($s);

        if (intval($s) > 0) {


            $login = mw()->user_manager->make_logged($s);
        //    dd($login);

            if (isset($login['success']) or isset($login['error'])) {
                return $login;
            }
        }

    } else if (isset($result['error'])) {
        return $result;
    }


}

function mw_whmcs_remote_user_login_exec($params)
{
    if (!is_array($params)) {
        $params = parse_params($params);
    }


    $cache_time = false;
    if (isset($params['cache'])) {
        $cache_time = intval($params['cache']);
    }

    $url = mw_whmcs_remote_get_connector_url().'/index.php?m=microweber_addon&function=login_to_my_website';







    $postfields = $params;
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    $data = curl_exec($ch);


    curl_close($ch);



//    var_dump($data);
//    exit;

//    print_r($url);
////    print_r($postfields);
//    print_r($data);
//    exit;

    //var_dump($data);

    $data = @json_decode($data, true);
//    var_dump($data);
//    exit;

    return $data;

}
 

function showMicroweberAdsBar() {

    $showBar = false;
    $showBarUrl = false;

    $whmcsSettingsFile = modules_path() . 'whmcs_connector/settings.json';
    $whmcsSettingsFile = normalize_path($whmcsSettingsFile, false);
    if (is_file($whmcsSettingsFile)) {

        $whmcsUrl = false;
        $whmcsSettingsFileContent = file_get_contents($whmcsSettingsFile);
        $settings = json_decode($whmcsSettingsFileContent, true);

        if (isset($settings['show_ads_bar']) && $settings['show_ads_bar'] == false) {
            return array('show'=>false);
        }
        if (isset($settings['url'])) {
            $whmcsUrl = $settings['url'];
        }
        if (isset($settings['whmcs_url'])) {
            $whmcsUrl = $settings['whmcs_url'];
        }

        if ($whmcsUrl) {
            $checkDomainUrl = $whmcsUrl . '/index.php?m=microweber_addon&function=check_domain_is_premium&domain=' . $_SERVER['HTTP_HOST'];

            try {
                $checkDomain = @app()->http->url($checkDomainUrl)->get();
                $checkDomain = @json_decode($checkDomain, true);

                if (isset($checkDomain['free']) && $checkDomain['free'] == true && isset($checkDomain['ads_bar_url'])) {
                    $showBarUrl = $whmcsUrl . $checkDomain['ads_bar_url'];
                    $showBar = true;
                }
            }
            catch (\Exception $e) {
                //code to handle the exception
            }

        }

    }

    return array('show'=>$showBar, 'iframe_url'=>$showBarUrl);
}

event_bind('mw.front', function () {
    $css = '
        <style>
        .js-microweber-add-iframe-wrapper {
            height: 54px;
            width: 100%;
            min-height: 54px !important;
            max-height: 54px !important;
            position:relative;
        }
        .js-microweber-add-iframe {
            z-index: 99999;
            position: fixed;
            min-height: 0;
            height: 54px !important;
            border: 0;
            left: 0;
            right: 0;
            top: 0;
            width: 100%;
            overflow: hidden;
        }
        .sticky-nav .sticky, 
        .navigation-holder .navigation, 
        .header-section.sticker{
            top: 54px;
        }
        .nav-bar {
            margin-top: 54px !important;
        }
        </style>
    ';

    $bar = showMicroweberAdsBar();

    if ($bar['show'] && !is_live_edit()) {
       mw()->template->foot($css . '<div class="js-microweber-add-iframe-wrapper"><iframe class="js-microweber-add-iframe" scrolling="no" frameborder="0" src="'.$bar['iframe_url'].'"></iframe></div>');
    }

});
