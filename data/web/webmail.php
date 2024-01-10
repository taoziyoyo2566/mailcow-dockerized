<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'admin') {
  header('Location: /debug');
  exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'domainadmin') {
  header('Location: /mailbox');
  exit();
}
elseif (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'user') {
  $session_var_user_allowed = 'sogo-sso-user-allowed';
  $session_var_pass = 'sogo-sso-pass';

  // load master password
  $sogo_sso_pass = file_get_contents("/etc/sogo-sso/sogo-sso.pass");
  // register username and password in session
  $_SESSION[$session_var_user_allowed][] = $_SESSION['mailcow_cc_username'];
  $_SESSION[$session_var_pass] = $sogo_sso_pass;

  header("Location: /SOGo/so/{$_SESSION['mailcow_cc_username']}");
  exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
$_SESSION['index_query_string'] = $_SERVER['QUERY_STRING'];

$template = 'webmail.twig';
$template_data = [
  'login_delay' => @$_SESSION['ldelay'],
  'has_iam_sso' => ($iam_provider) ? true : false
];

$js_minifier->add('/web/js/site/webmail.js');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
