<?php
/*
 * hash password for CIVS , db:wcdb
 * SQL:
 * insert into users (username,password,created,modified) values ('lmax','',now(),now());
 */

$opts = getopt("p:");
$plain = trim($opts['p']);
$passwd = password_hash ($plain, PASSWORD_BCRYPT);

printf("in:%s\nout:%s\n", $plain,$passwd);


?>
