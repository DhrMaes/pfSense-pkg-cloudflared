<?php

require_once("config.inc");
require_once("functions.inc");
require_once("service-utils.inc");
require_once("util.inc");

define('CLOUDFLARED_CONFIG_DIR', '/usr/local/etc/cloudflared');
define('CLOUDFLARED_LOG_DIR', '/var/log/cloudflared');
define('CLOUDFLARED_RUN_DIR', '/var/run/cloudflared');
define('CLOUDFLARED_TUNNELS_JSON', CLOUDFLARED_CONFIG_DIR . '/tunnels.json');
define('CLOUDFLARED_RC_ENV', '/etc/rc.conf.d/cloudflared');
define('CLOUDFLARED_RC_SCRIPT', '/usr/local/etc/rc.d/cloudflared');

function cloudflared_write_rc_script() {
	$rc = <<<'EOF'
#!/bin/sh

# PROVIDE: cloudflared
# REQUIRE: NETWORKING SERVERS
# KEYWORD: shutdown

. /etc/rc.subr

name="cloudflared"
rcvar="cloudflared_enable"

load_rc_config ${name}

: ${cloudflared_enable:="NO"}

config_json="/usr/local/etc/cloudflared/tunnels.json"
command="/usr/sbin/daemon"
cf_bin="/usr/local/bin/cloudflared"

start_one() {
	_target="$1"
	if [ ! -f "${config_json}" ]; then
		return 1
	fi

	/usr/local/bin/php -r '
		require_once("functions.inc");
		$json = json_decode(@file_get_contents("'$config_json'"), true);
		if (isset($json["'$_target'"])) {
			$t = $json["'$_target'"];
			$pidfile = $t["pidfile"];
			if (file_exists($pidfile)) {
				$pid = trim(@file_get_contents($pidfile));
				if (is_numeric($pid) && posix_kill((int)$pid, 0)) {
					exit(0);
				}
			}

			@mkdir("/var/run/cloudflared", 0755, true);
			@mkdir("/var/log/cloudflared", 0755, true);

			$token = $t["token"];
			$logfile = $t["logfile"];
			$protocol = ($t["protocol"] !== "auto" && !empty($t["protocol"])) ? "--protocol " . escapeshellarg($t["protocol"]) : "";
			$loglevel = !empty($t["loglevel"]) ? "--loglevel " . escapeshellarg($t["loglevel"]) : "";
			$metrics = !empty($t["metrics"]) ? "--metrics " . escapeshellarg($t["metrics"]) : "";
			$extra = !empty($t["extra_args"]) ? $t["extra_args"] : "";

			$cmd = sprintf(
				"/usr/sbin/daemon -f -p %s -o %s /usr/local/bin/cloudflared tunnel --no-autoupdate %s %s %s %s run --token %s",
				escapeshellarg($pidfile),
				escapeshellarg($logfile),
				$loglevel,
				$protocol,
				$metrics,
				$extra,
				escapeshellarg($token)
			);
			mwexec($cmd);
		}
	'
}

stop_one() {
	_target="$1"
	/usr/local/bin/php -r '
		$pidfile = "/var/run/cloudflared/cloudflared_" . "'$_target'" . ".pid";
		if (file_exists($pidfile)) {
			$pid = trim(@file_get_contents($pidfile));
			if (is_numeric($pid)) {
				posix_kill((int)$pid, SIGTERM);
				for ($i = 0; $i < 10; $i++) {
					if (!posix_kill((int)$pid, 0)) break;
					usleep(200000);
				}
				if (posix_kill((int)$pid, 0)) {
					posix_kill((int)$pid, SIGKILL);
				}
			}
			@unlink($pidfile);
		}
	'
}

start_all() {
	if [ ! -f "${config_json}" ]; then
		return 0
	fi

	/usr/local/bin/php -r '
		require_once("functions.inc");
		$json = json_decode(@file_get_contents("'$config_json'"), true);
		if (is_array($json)) {
			@mkdir("/var/run/cloudflared", 0755, true);
			@mkdir("/var/log/cloudflared", 0755, true);

			foreach ($json as $id => $t) {
				$pidfile = $t["pidfile"];
				if (file_exists($pidfile)) {
					$pid = trim(@file_get_contents($pidfile));
					if (is_numeric($pid) && posix_kill((int)$pid, 0)) {
						continue;
					}
				}

				$token = $t["token"];
				$logfile = $t["logfile"];
				$protocol = ($t["protocol"] !== "auto" && !empty($t["protocol"])) ? "--protocol " . escapeshellarg($t["protocol"]) : "";
				$loglevel = !empty($t["loglevel"]) ? "--loglevel " . escapeshellarg($t["loglevel"]) : "";
				$metrics = !empty($t["metrics"]) ? "--metrics " . escapeshellarg($t["metrics"]) : "";
				$extra = !empty($t["extra_args"]) ? $t["extra_args"] : "";

				$cmd = sprintf(
					"/usr/sbin/daemon -f -p %s -o %s /usr/local/bin/cloudflared tunnel --no-autoupdate %s %s %s %s run --token %s",
					escapeshellarg($pidfile),
					escapeshellarg($logfile),
					$loglevel,
					$protocol,
					$metrics,
					$extra,
					escapeshellarg($token)
				);
				mwexec($cmd);
			}
		}
	'
}

stop_all() {
	/usr/local/bin/php -r '
		$pids = glob("/var/run/cloudflared/cloudflared_*.pid") ?: [];
		foreach ($pids as $pidfile) {
			$pid = trim(@file_get_contents($pidfile));
			if (is_numeric($pid)) {
				posix_kill((int)$pid, SIGTERM);
				for ($i = 0; $i < 10; $i++) {
					if (!posix_kill((int)$pid, 0)) break;
					usleep(200000);
				}
				if (posix_kill((int)$pid, 0)) {
					posix_kill((int)$pid, SIGKILL);
				}
			}
			@unlink($pidfile);
		}
	'
}

status_all() {
	if [ ! -f "${config_json}" ]; then
		echo "No tunnels configured."
		return 0
	fi

	/usr/local/bin/php -r '
		$json = json_decode(@file_get_contents("'$config_json'"), true);
		if (is_array($json)) {
			foreach ($json as $id => $t) {
				$pidfile = $t["pidfile"];
				$name = $t["name"];
				$running = false;
				if (file_exists($pidfile)) {
					$pid = trim(@file_get_contents($pidfile));
					if (is_numeric($pid) && posix_kill((int)$pid, 0)) {
						$running = true;
						echo "Tunnel [{$name}] ({$id}) is running with PID {$pid}.\n";
					}
				}
				if (!$running) {
					echo "Tunnel [{$name}] ({$id}) is stopped.\n";
				}
			}
		}
	'
}

case "$1" in
	start)
		if [ -n "$2" ]; then
			start_one "$2"
		else
			start_all
		fi
		;;
	stop)
		if [ -n "$2" ]; then
			stop_one "$2"
		else
			stop_all
		fi
		;;
	restart)
		if [ -n "$2" ]; then
			stop_one "$2"
			sleep 1
			start_one "$2"
		else
			stop_all
			sleep 1
			start_all
		fi
		;;
	status)
		status_all
		;;
	*)
		echo "Usage: $0 {start|stop|restart|status} [tunnel_id]"
		exit 1
		;;
esac
EOF;
	safe_mkdir('/usr/local/etc/rc.d', 0755);
	file_put_contents(CLOUDFLARED_RC_SCRIPT, $rc);
	@chmod(CLOUDFLARED_RC_SCRIPT, 0755);
}

function cloudflared_install() {
	safe_mkdir(CLOUDFLARED_CONFIG_DIR, 0755);
	safe_mkdir(CLOUDFLARED_LOG_DIR, 0755);
	safe_mkdir(CLOUDFLARED_RUN_DIR, 0755);

	cloudflared_write_rc_script();
	cloudflared_resync_config();
}

function cloudflared_deinstall() {
	cloudflared_stop_all();

	if (file_exists(CLOUDFLARED_RC_ENV)) {
		@unlink(CLOUDFLARED_RC_ENV);
	}
	if (file_exists(CLOUDFLARED_TUNNELS_JSON)) {
		@unlink(CLOUDFLARED_TUNNELS_JSON);
	}
}

function cloudflared_is_enabled($val) {
	if (empty($val)) {
		return false;
	}
	return in_array(strtolower(trim((string)$val)), array('on', 'yes', 'true', '1', 'checked'), true);
}

function cloudflared_get_tunnels() {
	global $config;
	$tunnels = config_get_path('installedpackages/cloudflared/config', []);
	if (!is_array($tunnels)) {
		return array();
	}
	return $tunnels;
}

function cloudflared_sanitize_id($name, $index = 0) {
	$clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', trim($name));
	if (empty($clean)) {
		return "tunnel_" . $index;
	}
	return strtolower($clean);
}

function cloudflared_get_pid_file($tunnel_id) {
	return CLOUDFLARED_RUN_DIR . "/cloudflared_{$tunnel_id}.pid";
}

function cloudflared_get_log_file($tunnel_id) {
	return CLOUDFLARED_LOG_DIR . "/cloudflared_{$tunnel_id}.log";
}

function cloudflared_get_pid($tunnel_id) {
	$pidfile = cloudflared_get_pid_file($tunnel_id);
	if (file_exists($pidfile)) {
		$pid = trim(file_get_contents($pidfile));
		if (is_numeric($pid) && posix_kill((int)$pid, 0)) {
			return (int)$pid;
		}
	}
	return 0;
}

function cloudflared_is_tunnel_running($tunnel_id) {
	return (cloudflared_get_pid($tunnel_id) > 0);
}

function cloudflared_validate_input($post, &$input_errors) {
	if (isset($post['name'])) {
		$name = trim($post['name']);
		if (empty($name)) {
			$input_errors[] = gettext("Tunnel Name is required.");
		} elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
			$input_errors[] = gettext("Tunnel Name must contain only alphanumeric characters, dashes, and underscores.");
		}

		$current_id = isset($_GET['id']) ? (int)$_GET['id'] : -1;
		$tunnels = cloudflared_get_tunnels();
		foreach ($tunnels as $idx => $t) {
			if ($idx !== $current_id && strtolower(trim($t['name'])) === strtolower($name)) {
				$input_errors[] = sprintf(gettext("A tunnel with the name '%s' already exists."), htmlspecialchars($name));
				break;
			}
		}
	}

	if (isset($post['enable']) && cloudflared_is_enabled($post['enable'])) {
		$token = trim($post['tunnel_token'] ?? '');

		if (empty($token)) {
			$input_errors[] = gettext("Tunnel Token is required when the tunnel is enabled.");
		} elseif (!preg_match('/^[A-Za-z0-9_\-=\.\s]+$/', $token)) {
			$input_errors[] = gettext("Tunnel Token contains invalid characters.");
		}

		if (!empty($post['metrics_address']) && !preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}:[0-9]{1,5}$/', trim($post['metrics_address']))) {
			$input_errors[] = gettext("Metrics Address must be in IP:PORT format (e.g. 127.0.0.1:2000).");
		}
	}
}

function cloudflared_build_runtime_config() {
	$tunnels = cloudflared_get_tunnels();
	$runtime = array();

	foreach ($tunnels as $idx => $t) {
		if (!cloudflared_is_enabled($t['enable'] ?? '')) {
			continue;
		}

		$name = $t['name'] ?? "tunnel_{$idx}";
		$id = cloudflared_sanitize_id($name, $idx);
		$raw_token = $t['tunnel_token'] ?? '';
		$token = trim(base64_decode($raw_token) ?: $raw_token);

		if (empty($token)) {
			continue;
		}

		$runtime[$id] = array(
			'name'        => $name,
			'id'          => $id,
			'token'       => $token,
			'protocol'    => !empty($t['protocol']) ? $t['protocol'] : 'auto',
			'log_level'   => !empty($t['log_level']) ? $t['log_level'] : 'info',
			'metrics'     => !empty($t['metrics_address']) ? $t['metrics_address'] : '',
			'extra_args'  => !empty($t['extra_args']) ? $t['extra_args'] : '',
			'pidfile'     => cloudflared_get_pid_file($id),
			'logfile'     => cloudflared_get_log_file($id)
		);
	}

	safe_mkdir(CLOUDFLARED_CONFIG_DIR, 0755);
	file_put_contents(CLOUDFLARED_TUNNELS_JSON, json_encode($runtime, JSON_PRETTY_PRINT));
	return $runtime;
}

function cloudflared_start_tunnel($tunnel_id) {
	cloudflared_write_rc_script();
	if (file_exists(CLOUDFLARED_RC_SCRIPT)) {
		mwexec(CLOUDFLARED_RC_SCRIPT . " start " . escapeshellarg($tunnel_id));
	}
}

function cloudflared_stop_tunnel($tunnel_id) {
	if (file_exists(CLOUDFLARED_RC_SCRIPT)) {
		mwexec(CLOUDFLARED_RC_SCRIPT . " stop " . escapeshellarg($tunnel_id));
	}
}

function cloudflared_restart_tunnel($tunnel_id) {
	cloudflared_stop_tunnel($tunnel_id);
	sleep(1);
	cloudflared_start_tunnel($tunnel_id);
}

function cloudflared_stop_all() {
	if (file_exists(CLOUDFLARED_RC_SCRIPT)) {
		mwexec(CLOUDFLARED_RC_SCRIPT . " stop");
	}
}

function cloudflared_restart_all() {
	cloudflared_write_rc_script();
	if (file_exists(CLOUDFLARED_RC_SCRIPT)) {
		mwexec(CLOUDFLARED_RC_SCRIPT . " restart");
	}
}

function cloudflared_resync_config() {
	safe_mkdir(CLOUDFLARED_CONFIG_DIR, 0755);
	safe_mkdir(CLOUDFLARED_LOG_DIR, 0755);
	safe_mkdir(CLOUDFLARED_RUN_DIR, 0755);

	cloudflared_write_rc_script();
	$runtime = cloudflared_build_runtime_config();

	// Stop any running tunnel processes that are disabled or deleted
	$pid_files = glob(CLOUDFLARED_RUN_DIR . "/cloudflared_*.pid") ?: [];
	foreach ($pid_files as $pid_file) {
		if (preg_match('/cloudflared_(.+)\.pid$/', $pid_file, $m)) {
			$tid = $m[1];
			if (!isset($runtime[$tid])) {
				cloudflared_stop_tunnel($tid);
			}
		}
	}

	if (!empty($runtime)) {
		file_put_contents(CLOUDFLARED_RC_ENV, "cloudflared_enable=\"YES\"\n");
		cloudflared_restart_all();
	} else {
		file_put_contents(CLOUDFLARED_RC_ENV, "cloudflared_enable=\"NO\"\n");
		cloudflared_stop_all();
	}
}
