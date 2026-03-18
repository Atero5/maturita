<?php
session_start();      // otevře existující session
session_destroy();    // zničí session (odhlásí uživatele)

header("Location: login.html");  // přesměruje zpět na login
exit();
?>