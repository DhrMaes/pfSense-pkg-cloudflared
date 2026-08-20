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
	_pidfile="/var/run/cloudflared/cloudflared_${_target}.pid"
	if [ -f "${_pidfile}" ]; then
		_pid=$(cat "${_pidfile}" 2>/dev/null)
		if [ -n "${_pid}" ]; then
			kill ${_pid} 2>/dev/null
		fi
		rm -f "${_pidfile}"
	fi
}

start_all() {
	if [ "${cloudflared_enable}" != "YES" ] || [ ! -f "${config_json}" ]; then
		return 0
	fi

	/usr/local/bin/php -r '
		$json = json_decode(@file_get_contents("'$config_json'"), true);
		if (is_array($json)) {
			foreach (array_keys($json) as $id) {
				echo $id . "\n";
			}
		}
	' | while read -r tid; do
		if [ -n "${tid}" ]; then
			start_one "${tid}"
		fi
	done
}

stop_all() {
	for pidfile in /var/run/cloudflared/cloudflared_*.pid; do
		if [ -f "${pidfile}" ]; then
			_pid=$(cat "${pidfile}" 2>/dev/null)
			if [ -n "${_pid}" ]; then
				kill ${_pid} 2>/dev/null
			fi
			rm -f "${pidfile}"
		fi
	done
}

target="$2"

case "$1" in
	start)
		if [ -n "${target}" ]; then
			start_one "${target}"
		else
			start_all
		fi
		;;
	stop)
		if [ -n "${target}" ]; then
			stop_one "${target}"
		else
			stop_all
		fi
		;;
	restart)
		if [ -n "${target}" ]; then
			stop_one "${target}"
			sleep 1
			start_one "${target}"
		else
			stop_all
			sleep 1
			start_all
		fi
		;;
	status)
		if [ -n "${target}" ]; then
			pidfile="/var/run/cloudflared/cloudflared_${target}.pid"
			if [ -f "${pidfile}" ] && kill -0 $(cat "${pidfile}") 2>/dev/null; then
				echo "cloudflared instance [${target}] is running (PID $(cat ${pidfile}))."
				exit 0
			else
				echo "cloudflared instance [${target}] is not running."
				exit 1
			fi
		else
			running=0
			for pidfile in /var/run/cloudflared/cloudflared_*.pid; do
				if [ -f "${pidfile}" ] && kill -0 $(cat "${pidfile}") 2>/dev/null; then
					echo "cloudflared instance [$(basename ${pidfile} .pid | sed 's/cloudflared_//')] is running (PID $(cat ${pidfile}))."
					running=1
				fi
			done
			if [ "${running}" -eq 0 ]; then
				echo "No cloudflared instances are currently running."
				exit 1
			fi
		fi
		;;
	*)
		echo "Usage: $0 {start|stop|restart|status} [tunnel_id]"
		exit 1
		;;
esac
