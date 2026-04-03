<?php
session_start();      // otevře existující session
session_destroy();    // zničí session (odhlásí uživatele)

header("Location: login.html?logout=true");  // přesměruje zpět na login s parametrem
exit();
?>