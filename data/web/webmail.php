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
  header("Location: /SOGo/so/{$_SESSION['mailcow_cc_username']}");
  exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';

$has_iam_sso = false;
$iam_settings = identity_provider("get");
if (isset($iam_settings['authsource']) && $iam_settings['authsource'] != "ldap"){
  $has_iam_sso = true;
}

$template = 'webmail.twig';
$template_data = [
  'login_delay' => @$_SESSION['ldelay'],
  'has_iam_sso' => $has_iam_sso
];

$js_minifier->add('/web/js/site/webmail.js');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
