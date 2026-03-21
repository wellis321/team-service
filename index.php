<?php
// Root redirect — sends visitors to the public/ app entry point
header('Location: /public/index.php', true, 301);
exit;
