<?php
define('ACTION_START', 0);
define('ACTION_CHALLENGE', 1);
define('ACTION_FALLBACK',2);
define('ACTION_SUCCESS', 254);
define('ACTION_FAILED', 255);

// Prevents JavaScript XSS attacks aimed at stealing the session ID
ini_set('session.cookie_httponly', 1);
// Prevents passing the session ID through URLs
ini_set('session.use_only_cookies', 1);
// Uses a secure connection (HTTPS) if possible
ini_set('session.cookie_secure', 1);

session_start();
$fh = fopen('paymentkey.' . $_SESSION['cxID'] . '.dat', 'r');

if ($fh) {
//    $action = fgets( $fh );
//    $url = fgets( $fh );
//    $creq = fgets( $fh );
//    $session = trim(fgets( $fh ));
    $data = json_decode(fgets($fh));
    fclose($fh);

}



// Delete the payment key file as soon as the iframe is rendered. We no longer need this.
unlink('paymentkey.' . $_SESSION['cxID'] . '.dat');

if (!$data->creq) {
    die ('Unable to process 3D Secure data.');
}

if (ACTION_FALLBACK === $data->action) {
    echo '<form id="pa-form" action="' . $data->url . '" method="post">
    <input type="hidden" name="PaReq" value="' . $data->creq . '" />
    <input type="hidden" name="TermUrl" value="' . $termurl . '" />
    <input type="hidden" name="MD" value="' . $data->session . '" />
    <p>Please click button below to proceed to 3D secure.</p> <input type="submit" value="Go" />
    </form>';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("pa-form");b&&b.submit()})</script>';
}

if (ACTION_CHALLENGE === $data->action) {
    echo '<form id="pa-form" action="' . $data->url . '" method="post">
    <input type="hidden" name="creq" value="' . $data->creq . '" />
    <input type="hidden" name="threeDSSessionData" value="' . base64_encode($data->session) . '" />
    <p>Please click button below to proceed to 3D secure.</p> <input type="submit" value="Go" />
    </form>';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("pa-form");b&&b.submit()})</script>';
}

