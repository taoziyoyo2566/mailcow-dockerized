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

$template = 'webmail.twig';
$template_data = [
  'login_delay' => @$_SESSION['ldelay'],
  'has_iam_sso' => ($iam_provider) ? true : false
];

$js_minifier->add('/web/js/site/webmail.js');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
