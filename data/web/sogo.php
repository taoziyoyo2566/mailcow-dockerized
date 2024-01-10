<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

if (isset($_SESSION['mailcow_cc_role']) && $_SESSION['mailcow_cc_role'] == 'user') {
  header("Location: /SOGo/so/{$_SESSION['mailcow_cc_username']}");
  exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/header.inc.php';
$_SESSION['return_to'] = $_SERVER['REQUEST_URI'];
$_SESSION['index_query_string'] = $_SERVER['QUERY_STRING'];

$template = 'sogo.twig';
$template_data = [
];

$js_minifier->add('/web/js/site/sogo.js');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.inc.php';
