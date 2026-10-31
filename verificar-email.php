<?php
/**
 * Passe Livre Suzano — Redirecionamento de Verificação de E-mail
 */
require_once __DIR__ . '/includes/auth_guard.php';
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: verificar-codigo.php' . $query);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="refresh" content="0; url=verificar-codigo.php<?= $query ?>">
  <script>
    const query = window.location.search;
    window.location.replace('verificar-codigo.php' + query);
  </script>
  <title>Redirecionando para verificação de e-mail...</title>
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body>
  <p>Redirecionando para verificação de e-mail... Se não for redirecionado, <a href="verificar-codigo.php<?= $query ?>">clique aqui</a>.</p>
</body>
</html>
