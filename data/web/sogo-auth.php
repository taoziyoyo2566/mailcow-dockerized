<?php

ob_start();

function setAuthHeaders($username, $password) {
  $auth = "";
  $type = "";
  if (!empty($username) && !empty($password)) {
    $auth = "Basic " . base64_encode("$username:$password");
    $type = "Basic";
  }

  http_response_code(200);
  header("X-User: $username");
  header("X-Auth: $auth");
  header("X-Auth-Type: $type");
}

$session_var_user_allowed = 'sogo-sso-user-allowed';
$session_var_pass = 'sogo-sso-pass';

$ALLOW_ADMIN_EMAIL_LOGIN = (preg_match(
  "/^([yY][eE][sS]|[yY])+$/",
  $_ENV["ALLOW_ADMIN_EMAIL_LOGIN"]
));

$request = null;
if (isset($_SERVER['PHP_AUTH_USER'])) {
  $request = "basic-auth";
} elseif (isset($_GET['login'])) {
  $request = "admin-login";
} elseif (isset($_SERVER['HTTP_X_ORIGINAL_URI']) &&
          strcasecmp(substr($_SERVER['HTTP_X_ORIGINAL_URI'], 0, 6), "/SOGo/") === 0) {
  $request = "auth-request";
}


switch ($request) {
  case "basic-auth":
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

    $username = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];
    $is_eas = (preg_match('/^(\/SOGo|)\/Microsoft-Server-ActiveSync.*/', $original_uri) === 1);
    $is_dav = (preg_match('/^(\/SOGo|)\/dav.*/', $original_uri) === 1);
    $original_uri = isset($_SERVER['HTTP_X_ORIGINAL_URI']) ? $_SERVER['HTTP_X_ORIGINAL_URI'] : '';

    $login_check = check_login($username, $password, array('dav' => $is_dav, 'eas' => $is_eas));
    if ($login_check === 'user') {
      setAuthHeaders($username, $password);
      ob_end_flush();
      exit;
    }

    http_response_code(401);
    ob_end_flush();
    exit;
  break;
  case "admin-login":
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/prerequisites.inc.php';

    $is_dual = (!empty($_SESSION["dual-login"]["username"])) ? true : false;
    $login = html_entity_decode(rawurldecode($_GET["login"]));

    if (isset($_SESSION['mailcow_cc_role'])) {
      // Check if login is allowed:
      // - User has the "login as" ACL
      // - Admin email login is enabled in mailcow.conf
      // - Dual login is not active
      // - The current role is not "user" or dual login role is admin / domainadmin
      $is_login_allowed = (
        $_SESSION['acl']['login_as'] == "1" &&
        $ALLOW_ADMIN_EMAIL_LOGIN !== 0 &&
        ($_SESSION['mailcow_cc_role'] != "user" || $_SESSION['dual-login']['role'] == "admin" || $_SESSION['dual-login']['role'] == "domainadmin")
      );
      if ($is_login_allowed) {
        // set dual login session
        $_SESSION[$session_var_user_allowed][] = $login;
        if (!$is_dual) {
          $_SESSION["dual-login"]["username"] = $_SESSION['mailcow_cc_username'];
          $_SESSION["dual-login"]["role"]     = $_SESSION['mailcow_cc_role'];
        }
        $_SESSION['mailcow_cc_username']    = $login;
        $_SESSION['mailcow_cc_role']        = "user";

        // update sasl logs
        $service = ($app_passwd_data['eas'] === true) ? 'EAS' : 'DAV';
        $stmt = $pdo->prepare("REPLACE INTO sasl_log (`service`, `app_password`, `username`, `real_rip`) VALUES ('SSO', 0, :username, :remote_addr)");
        $stmt->execute(array(
          ':username' => $login,
          ':remote_addr' => ($_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'])
        ));

        // redirect to sogo (sogo will get the correct credentials via nginx auth_request
        http_response_code(200);
        header("Location: /SOGo/so/{$login}");
        ob_end_flush();
        exit;
      }
    }

    http_response_code(401);
    header("Location: /");
    ob_end_flush();
    exit;
  break;
  case "auth-request":
    session_start();

    $email_list = array(
      ($_SESSION['mailcow_cc_username'] ?? ''),     // Current user
      ($_SESSION["dual-login"]["username"] ?? ''),  // Dual login user
    );

    $url_parts = explode("/", $_SERVER['HTTP_X_ORIGINAL_URI']);
    if (count($url_parts) >= 4) {
      array_push($email_list, $url_parts[3]);       // Requested user
    }

    foreach($email_list as $email) {
      // check if this email is in session allowed list
      if (
        !empty($email) &&
        filter_var($email, FILTER_VALIDATE_EMAIL) &&
        is_array($_SESSION[$session_var_user_allowed]) &&
        in_array($email, $_SESSION[$session_var_user_allowed])
      ) {
        $password = file_get_contents("/etc/sogo-sso/sogo-sso.pass");
        setAuthHeaders($email, $password);
        ob_end_flush();
        exit;
      }
    }

    http_response_code(401);
    ob_end_flush();
    exit;
  break;
  default:
    setAuthHeaders("", "");
    ob_end_flush();
    exit;
  break;
}

