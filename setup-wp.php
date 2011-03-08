<?php

//Database
$mysql_user = "";
$mysql_password = "";

//Filesystem
$web_root = "/var/www/";
$apache_sites_avail = "/etc/apache2/sites-available/";
$apache_sites_enabled = "/etc/apache2/sites-enabled/";

//Template
$tar_file = "template.tar";
$sql_file = "template.sql";

//Commandline
$domain = $argv[1];

//Format domainname
$stripped_domain = preg_replace("/[^a-zA-Z]/", "", $domain);

//kolla så att användarnamnet skickas med

//Skapa mappen
if (!mkdir($web_root.$domain, 0, true)) {
    die('Failed to create folders...');
}

//Skapa databas
mysql_connect($mysql_host,$mysql_user,$mysql_password) or die('Cannot contact databaseserver');
$sql = "CREATE database ${stripped_domain}";
mysql_query($sql) or die('Cannot create database');
mysql_close();
//Packa upp filer
$folder_dest = $web_root.$domain;
$str_exec = "tar xvf $tar_file -C $folder_dest"; 
//Packa upp .tar arkivet i rooten
exec($str_exec) or die('Cannot unpack template file');

//Skapa databasanvändare och lösenord
$password = rand(99999,99999999);

mysql_connect($mysql_host,$mysql_user,$mysql_password) or die('Cannot connect database');
$sql = "CREATE USER '${stripped_domain}'@'localhost' IDENTIFIED BY '${password}'";
mysql_query($sql);

$sql2 = "GRANT ALL PRIVILEGES ON ${stripped_domain}.* TO '${stripped_domain}'@'localhost'";
mysql_query($sql2);
mysql_close();

//Läs in databasfilen
$filename = "${web_root}${domain}/${sql_file}";

$exec_str = "mysql -u ${stripped_domain} -p${password} ${stripped_domain} < ${filename}";
exec($exec_str);

//Sök och ersätt användare och lösenord i WP-config
//Läs in hela filen i en sträng
$filename = "${web_root}${domain}/wp-config.php";
$file = implode("\n",file($filename));
$handle = fopen($filename, "w+");

//Sök och ersätt strängar
$needle_password = "#PASSWORD#";
$needle_username = "#USERNAME#";
$newcontent = str_replace($needle_password,$password,$file);
$newcontent2 = str_replace($needle_username,$stripped_domain,$newcontent);
fwrite($handle,$newcontent2,strlen($file));
fclose($handle);

//Sätt vettiga rättigheter
$exec_str = "chown -R www-data:www-data ${folder_dest} && chmod -R 755 ${folder_dest}";
exec($exec_str);

//Ersätt URL i databasen?
mysql_connect($mysql_host,$stripped_domain,$password);
mysql_select_db($stripped_domain);
$url = "http://".$argv[1];
$sql = "update wp_options SET option_value='${url}' where option_name='siteurl'";
$sql2 = "update wp_options SET option_value='${url}' where option_name='home'";
mysql_query($sql);
mysql_query($sql2);
//Sök och ersätt i Vhostkonfen



//Klart? Skicka mail till någon?
//Meddela att apache2 måste startas om för att ändringen ska gå igenom
echo "Lösenord: ".$password."\n\n";
?>
