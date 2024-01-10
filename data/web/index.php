<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (isset($_SESSION['mailcow_cc_role']) && isset($_SESSION['oauth2_request'])) {
  $oauth2_request = $_SESSION['oauth2_request'];
  unset($_SESSION['oauth2_request']);
  header('Location: ' . $oauth2_request);
  exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'admin') {
  header('Location: /debug');
  exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'domainadmin') {
  header('Location: /mailbox');
  exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'user') {    
  $user_details = mailbox("get", $_SESSION['mailcow_cc_role']);
  if ($user_details['attributs']['sogo_access'] == 1) {
    header("Location: /SOGo/so/{$_SESSION['mailcow_cc_role']}");
  } else {
    header("Location: /user");
  }
  exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
$_SESSION['index_query_string'] = $_SERVER['QUERY_STRING'];

$has_iam_sso = false;
$iam_settings = identity_provider("get");
if (isset($iam_settings['authsource']) && $iam_settings['authsource'] != "ldap"){
  $has_iam_sso = true;
}

$template = 'index.twig';
$template_data = [
  'oauth2_request' => @$_SESSION['oauth2_request'],
  'is_mobileconfig' => str_contains($_SESSION['index_query_string'], 'mobileconfig'),
  'login_delay' => @$_SESSION['ldelay'],
  'has_iam_sso' => $has_iam_sso
];

$js_minifier->add('/web/js/site/index.js');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
