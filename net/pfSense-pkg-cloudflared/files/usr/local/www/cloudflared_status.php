<?php

require_once("guiconfig.inc");
require_once("/usr/local/pkg/cloudflared.inc");

$pgtitle = array(gettext("Status"), gettext("Cloudflare Tunnels"));
$shortcut_section = "cloudflared";

$tunnels = cloudflared_get_tunnels();
$selected_tunnel = $_GET['tunnel'] ?? $_POST['tunnel'] ?? '';

if (empty($selected_tunnel) && !empty($tunnels)) {
	$selected_tunnel = cloudflared_sanitize_id($tunnels[0]['name'] ?? '', 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	$target = $_POST['target_tunnel'] ?? $selected_tunnel;

	if (!empty($target)) {
		switch ($action) {
			case 'start':
				cloudflared_start_tunnel($target);
				$savemsg = sprintf(gettext("Started tunnel '%s'."), htmlspecialchars($target));
				break;
			case 'stop':
				cloudflared_stop_tunnel($target);
				$savemsg = sprintf(gettext("Stopped tunnel '%s'."), htmlspecialchars($target));
				break;
			case 'restart':
				cloudflared_restart_tunnel($target);
				$savemsg = sprintf(gettext("Restarted tunnel '%s'."), htmlspecialchars($target));
				break;
			case 'clear_log':
				$logfile = cloudflared_get_log_file($target);
				if (file_exists($logfile)) {
					@file_put_contents($logfile, "");
				}
				$savemsg = sprintf(gettext("Cleared log for tunnel '%s'."), htmlspecialchars($target));
				break;
		}
	}
}

$tab_array = array(
	array(gettext("Tunnels"), false, "cloudflared_tunnels.php"),
	array(gettext("Status & Logs"), true, "cloudflared_status.php")
);

include("head.inc");

if (!empty($savemsg)) {
	print_info_box($savemsg, 'success');
}

display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("Cloudflare Tunnels Status")?></h2>
	</div>
	<div class="panel-body">
		<?php if (empty($tunnels)): ?>
			<p class="text-muted"><?=gettext("No tunnels configured. Go to Services > Cloudflare Tunnels to add one.")?></p>
		<?php else: ?>
			<div class="table-responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th style="width: 40px;"><?=gettext("Status")?></th>
							<th><?=gettext("Name")?></th>
							<th><?=gettext("Description")?></th>
							<th><?=gettext("Protocol")?></th>
							<th><?=gettext("Log Level")?></th>
							<th><?=gettext("PID")?></th>
							<th class="action-icons"><?=gettext("Actions")?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($tunnels as $idx => $t):
							$id = cloudflared_sanitize_id($t['name'] ?? '', $idx);
							$enabled = cloudflared_is_enabled($t['enable'] ?? '');
							$pid = cloudflared_get_pid($id);
							$is_running = ($pid > 0);
						?>
							<tr>
								<td>
									<?php if (!$enabled): ?>
										<i class="fa-solid fa-ban text-warning" title="<?=gettext('Disabled')?>"></i>
									<?php elseif ($is_running): ?>
										<i class="fa-solid fa-check-circle text-success" title="<?=gettext('Running')?>"></i>
									<?php else: ?>
										<i class="fa-solid fa-times-circle text-danger" title="<?=gettext('Stopped')?>"></i>
									<?php endif; ?>
								</td>
								<td><strong><?=htmlspecialchars($t['name'] ?? $id)?></strong></td>
								<td><?=htmlspecialchars($t['descr'] ?? '')?></td>
								<td><?=strtoupper(htmlspecialchars($t['protocol'] ?? 'AUTO'))?></td>
								<td><?=strtoupper(htmlspecialchars($t['log_level'] ?? 'INFO'))?></td>
								<td><?=$is_running ? $pid : '-'?></td>
								<td class="action-icons">
									<form action="cloudflared_status.php" method="post" style="display:inline-block;margin:0;">
										<input type="hidden" name="target_tunnel" value="<?=htmlspecialchars($id)?>">
										<input type="hidden" name="tunnel" value="<?=htmlspecialchars($selected_tunnel)?>">
										<?php if ($enabled): ?>
											<?php if ($is_running): ?>
												<button type="submit" name="action" value="restart" class="btn btn-link btn-xs" style="padding:0 3px;" title="<?=gettext('Restart Tunnel')?>">
													<i class="fa-solid fa-arrow-rotate-right"></i>
												</button>
												<button type="submit" name="action" value="stop" class="btn btn-link btn-xs" style="padding:0 3px;" title="<?=gettext('Stop Tunnel')?>">
													<i class="fa-regular fa-circle-stop"></i>
												</button>
											<?php else: ?>
												<button type="submit" name="action" value="start" class="btn btn-link btn-xs" style="padding:0 3px;" title="<?=gettext('Start Tunnel')?>">
													<i class="fa-solid fa-play-circle"></i>
												</button>
											<?php endif; ?>
										<?php else: ?>
											<span class="text-muted"><i class="fa-solid fa-ban text-muted"></i></span>
										<?php endif; ?>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>

<?php if (!empty($tunnels)): ?>
<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title"><?=gettext("Tunnel Logs")?></h2>
	</div>
	<div class="panel-body">
		<form action="cloudflared_status.php" method="get" class="form-inline" style="margin-bottom: 15px;">
			<label for="tunnel-select"><?=gettext("Select Tunnel:")?>&nbsp;</label>
			<select name="tunnel" id="tunnel-select" class="form-control input-sm" onchange="this.form.submit()">
				<?php foreach ($tunnels as $idx => $t):
					$id = cloudflared_sanitize_id($t['name'] ?? '', $idx);
				?>
					<option value="<?=htmlspecialchars($id)?>" <?=$selected_tunnel === $id ? 'selected' : ''?>>
						<?=htmlspecialchars($t['name'] ?? $id)?> (<?=htmlspecialchars($id)?>)
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="btn btn-info btn-sm">
				<i class="fa-solid fa-arrows-rotate icon-embed-btn"></i> <?=gettext("Refresh")?>
			</button>
		</form>

		<form action="cloudflared_status.php" method="post">
			<input type="hidden" name="tunnel" value="<?=htmlspecialchars($selected_tunnel)?>">
			<div class="form-group">
				<textarea class="form-control" rows="18" readonly style="font-family: monospace; font-size: 12px; background-color: #1e1e1e; color: #00ff00;"><?php
					$logfile = cloudflared_get_log_file($selected_tunnel);
					if (file_exists($logfile)) {
						$lines = file($logfile);
						if ($lines !== false && !empty($lines)) {
							echo htmlspecialchars(implode("", array_slice($lines, -150)));
						} else {
							echo gettext("Log file is empty.");
						}
					} else {
						echo gettext("No logs found for this tunnel yet.");
					}
				?></textarea>
			</div>
			<button type="submit" name="action" value="clear_log" class="btn btn-warning btn-sm" onclick="return confirm('<?=gettext('Are you sure you want to clear this log?')?>');">
				<i class="fa-solid fa-trash-can icon-embed-btn"></i> <?=gettext("Clear Log")?>
			</button>
		</form>
	</div>
</div>
<?php endif; ?>

<?php include("foot.inc"); ?>
