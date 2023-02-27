<?php

/**
 * Test access i refresh tokena (firebase/php-jwt package)
 */

session_start();

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
require_once 'vendor/autoload.php';

if(isset($_POST['submit']))
{
    if($_POST['name'] == "" || $_POST['password'] == "")
    {
        $_SESSION['MESSAGE'] = "Korisničko ime i lozinka su obavezni";
        header('location: /PROJECTS/php-jwt-signin-system/');
        return;
    }

    $_SESSION['MESSAGE'] = '';
    $now = new \DateTime('now', new \DateTimeZone('Europe/Zagreb'));

    $user = $_POST['name'];
    $password = $_POST['password'];

    // konekcija na bazu i dohvacanje korisnika
    $db = mysqli_connect('localhost', 'root', '', 'jwt');
    $sql = mysqli_query($db, "SELECT * FROM users WHERE `name` LIKE '%$user%'");

    if (mysqli_num_rows($sql))
    {
        // treba sakriti u .env i dodati u .gitignore
        $secretKey = 'eyJhbGciOiJIUzI1NiJ9.eyJSb2xlIjoiRGlyZWt0b3JTdmVtaXJhIiwiSXNzdWVyIjoiSmFTYW1Jc3N1ZXIiLCJVc2VybmFtZSI6Ik1hdGVqIiwiZXhwIjoxNjc3NDU4NTM2LCJpYXQiOjE2Nzc0NTg1MzZ9.-rtaICDbhr_Uw16yA6O36JDQt2gw2OV2OG8M1mkWq6E';
        $serverName = 'localhost';

        // dodano vrijeme za refresh token
        $issuedRefresh = new \DateTime('now +10 minutes', new \DateTimeZone('Europe/Zagreb'));

        // expire za refresh token
        $expireRefresh = $issuedRefresh->format("d-m-Y h:i:s");

        // expire za access token
        $expire = $now->modify('+5 minutes')->format("d-m-Y h:i:s");

        // podaci za generiranje access tokena
        $data = [
                'iat' => $now->format("d-m-Y h:i:s"), // iat - issued at time
                'iss' => $serverName, // iss - issuer
                'exp' => $expire, // kad token istice
                'userName' => $user,
                'password' => $password,
        ];

        while ($row = mysqli_fetch_assoc($sql))
        {
            $userId = $row['id'];

            if((int) $row['token_expires'] == 0)
            {
                // generiranje access tokena
                $accessToken = JWT::encode($data, $secretKey, 'HS512');

                // podaci za generiranje refresh tokena
                $dataRefreshToken = [
                    'iat' => $now->format("d-m-Y h:i:s"), // iat - issued at time
                    'iss' => $serverName, // iss - issuer
                    'exp' => $expireRefresh, // kad token istice
                    'userName' => $user,
                    'password' => $password,
                ];

                // generiranje refresh tokena
                $refreshToken = JWT::encode($dataRefreshToken, $secretKey, 'HS512');

                // spremi access token
                mysqli_query($db, "UPDATE users SET token='$accessToken', token_expires='$expire'"); // radim update jer sam vec rucno kreirao usera u bazi

                // spremi refresh tokena
                mysqli_query($db, "INSERT INTO refresh_token (user_id, refresh_token, expiry) 
                    VALUES ('$userId', '$refreshToken', '$expireRefresh')");

                if ($accessToken)
                {
                    $_SESSION['access_token'] = $accessToken;
                    $_SESSION['access_token_expires'] = $expire;
                    $_SESSION['refresh_token_expires'] = $expireRefresh;

                    $_SESSION['MESSAGE'] = "Access token je generiran";

                    header("location: /PROJECTS/php-jwt-signin-system/");
                    return;
                }

            } else {
                // ako token postoji u bazi
                $currentTime = new \DateTime('now', new \DateTimeZone('Europe/Zagreb'));

                if ($_SESSION['access_token_expires'] < $currentTime->format("d-m-Y h:i:s"));
                {
                    // access token istekao
                    $res = mysqli_query($db, "SELECT * FROM refresh_token WHERE user_id='$userId'");

                    while ($resRow = mysqli_fetch_assoc($res))
                    {
                        $currentTime = new \DateTime('now', new \DateTimeZone('Europe/Zagreb'));

                        if (isset($resRow['refresh_token']))
                        {
                            if($resRow['expiry'] < $currentTime->format("d-m-Y h:i:s"))
                            {
                                // ako je refresh token istekao
                                $_SESSION['MESSAGE'] = "Refresh token je istekao " . $resRow['expiry'];

                                unset($_SESSION['access_token']);
                                unset($_SESSION['access_token_expires']);

                                header("location: /PROJECTS/php-jwt-signin-system/");
                                return;
                            }else {
                                // ako refresh token nije istekao -> generiraj novi access token
                                // generiranje access tokena
                                $accessToken = JWT::encode($data, $secretKey, 'HS512');

                                // updejtanje access tokena
                                mysqli_query($db, "UPDATE users SET token='$accessToken', token_expires='$expire'");

                                $_SESSION['access_token'] = $accessToken;
                                $_SESSION['access_token_expires'] = $expire;
                            }
                        }
                    }
                }

                header("location: /PROJECTS/php-jwt-signin-system/");
                return;

            }

            header("location: /PROJECTS/php-jwt-signin-system/");
            return;
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="">
<title>Tokens</title>

<link href="assets/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/sign-in.css" rel="stylesheet">
</head>

<body class="text-center">

<main class="form-signin w-100 m-auto">
  <form method="post">
    <img class="mb-4" src="assets/brand/bootstrap-logo.svg" alt="" width="72" height="57">
    <h1 class="h3 mb-3 fw-normal">Please sign in</h1>

    <div class="form-floating">
      <input type="text" class="form-control" id="name" name="name" placeholder="Name" value="matej">
      <label for="name">Email address</label>
    </div>
    <div class="form-floating">
      <input type="password" class="form-control" id="password" name="password" placeholder="Password" value="12345678">
      <label for="password">Password</label>
    </div>

    <button class="w-100 btn btn-lg btn-primary mt-3 mb-3" type="submit" name="submit">Sign in</button>

    <?php
    // info o stanju tokena
    if (isset($_SESSION['access_token_expires']))
    {
      echo "<div class='alert alert-success text-center'>Access token je valjan do " . $_SESSION['access_token_expires'] . "</div>";
      echo "<div class='alert alert-success text-center'>Refresh token je valjan do " . $_SESSION['refresh_token_expires'] . "</div>";
    }
    elseif (isset($_SESSION['MESSAGE'])) // ako je refresh token istekao
    {
      echo "<div class='alert alert-danger text-center'>" . $_SESSION['MESSAGE'] . "</div>";
    }
    else {
      echo "";
    }
    ?>

    <?php
    if (isset($_SESSION['access_token'])): ?>
    <form action="clear.php?clear=yes">
        <input class="btn btn-warning" type="submit" value="Obriši tokene">
    </form>
    <?php endif; ?>

    <p class="mt-5 mb-3 text-muted">&copy; 2023</p>
  </form>
</main>

</body>
</html>
