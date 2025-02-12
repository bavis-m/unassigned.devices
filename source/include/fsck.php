<?php
/* Copyright 2015, Guilherme Jardim
 * Copyright 2016-2021, Dan Landon
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

$plugin = "unassigned.devices";
require_once("plugins/{$plugin}/include/lib.php");
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$translations = file_exists("$docroot/webGui/include/Translations.php");

if ($translations) {
	/* add translations */
	$_SERVER['REQUEST_URI'] = 'unassigneddevices';
	require_once "$docroot/webGui/include/Translations.php";
} else {
	/* legacy support (without javascript) */
	$noscript = true;
	require_once "$docroot/plugins/$plugin/include/Legacy.php";
}

readfile('logging.htm');

function write_log($string) {
	global $var;

	if (empty($string)) {
		return;
	}
	$string = str_replace("\n", "<br />", $string);
	$string = str_replace('"', "\\\"", trim($string));
	echo "<script>addLog(\"{$string}\");</script>";
	@flush();
}

if ( isset($_GET['device']) && isset($_GET['fs']) ) {
	$device	= $_GET['device'];
	$fs		= $_GET['fs'];
	$check_type	= isset($_GET['check_type']) ? $_GET['check_type'] : 'ro';
	$luks	= $_GET['luks'];
	$serial	= $_GET['serial'];
	write_log("FS: $fs<br /><br />");
	if ($fs == "crypto_LUKS") {
		$mapper	= basename($device);
		$cmd	= "luksOpen {$luks} ".escapeshellarg($mapper);
		$pass	= decrypt_data(get_config($serial, "pass"));
		if (! $pass) {
			if (file_exists($var['luksKeyfile'])) {
				unassigned_log("Using luksKeyfile to open the 'crypto_LUKS' device.");
				$o		= shell_exec("/sbin/cryptsetup ".$cmd." -d ".escapeshellarg($var['luksKeyfile'])." 2>&1");
			} else {
				unassigned_log("Using Unraid api to open the 'crypto_LUKS' device.");
				$o		= shell_exec("/usr/local/sbin/emcmd 'cmdCryptsetup=$cmd' 2>&1");
			}
		} else {
			$luks_pass_file = "{$paths['luks_pass']}_".basename($luks);
			file_put_contents($luks_pass_file, $pass);
			unassigned_log("Using disk password to open the 'crypto_LUKS' device.");
			$o		= shell_exec("/sbin/cryptsetup ".$cmd." -d ".escapeshellarg($luks_pass_file)." 2>&1");
			unset($pass);
			exec("/bin/shred -u ".escapeshellarg($luks_pass_file));
		}
		if ($o) {
			echo("luksOpen error: ".$o."<br />");
			return;
		}
	}
	$file_system = $fs;
	if ($fs == "crypto_LUKS") {
		$o = shell_exec("/sbin/fsck -vy ".escapeshellarg($device)." 2>&1");
		if (strpos($o, 'XFS') !== false) {
			$file_system = "xfs";
		} elseif (strpos($o, 'REISERFS') !== false) {
			$file_system = "resierfs";
		} elseif (strpos($o, 'BTRFS') !== false) {
			$file_system = "btrfs";
		}
	}
	$command = get_fsck_commands($file_system, $device, $check_type)." 2>&1";
	write_log($command."<br /><br />");
	$proc = popen($command, 'r');
	while (! feof($proc)) {
		write_log(fgets($proc));
	}
	if ($fs == "crypto_LUKS") {
		shell_exec("/sbin/cryptsetup luksClose ".escapeshellarg($mapper));
	}
}
write_log("<center><button type='button' onclick='document.location=\"/plugins/{$plugin}/include/fsck.php?device={$device}&fs={$fs}&luks={$luks}&serial={$serial}&check_type=rw&type="._('Done')."\"'>"._('Run with CORRECT flag')."</button></center>");
?>
