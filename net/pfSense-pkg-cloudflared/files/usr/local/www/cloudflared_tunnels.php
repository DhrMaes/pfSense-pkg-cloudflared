<?php

require_once("guiconfig.inc");
require_once("/usr/local/pkg/cloudflared.inc");

$pgtitle = array(gettext("Services"), gettext("Cloudflare Tunnels"));
$shortcut_section = "cloudflared";

if ($_POST) {
	$pconfig = $_POST;
	$tunnels = cloudflared_get_tunnels();
	$changed = false;

	if (isset($_POST['del_x']) || isset($_POST['act']) && $_POST['act'] === 'del') {
		if (isset($_POST['id']) && isset($tunnels[$_POST['id']])) {
			$id = cloudflared_sanitize_id($tunnels[$_POST['id']]['name'] ?? '', (int)$_POST['id']);
			cloudflared_stop_tunnel($id);
			unset($tunnels[$_POST['id']]);
			$tunnels = array_values($tunnels);
			$changed = true;
			$savemsg = gettext("Selected tunnel deleted successfully.");
		} elseif (isset($_POST['entries']) && is_array($_POST['entries'])) {
			foreach ($_POST['entries'] as $idx) {
				if (isset($tunnels[$idx])) {
					$id = cloudflared_sanitize_id($tunnels[$idx]['name'] ?? '', (int)$idx);
					cloudflared_stop_tunnel($id);
					unset($tunnels[$idx]);
				}
			}
			$tunnels = array_values($tunnels);
			$changed = true;
			$savemsg = gettext("Selected tunnels deleted successfully.");
		}
	} elseif (isset($_POST['toggle_x']) || (isset($_POST['act']) && $_POST['act'] === 'toggle')) {
		if (isset($_POST['id']) && isset($tunnels[$_POST['id']])) {
			$current = $tunnels[$_POST['id']]['enable'] ?? '';
			$tunnels[$_POST['id']]['enable'] = cloudflared_is_enabled($current) ? 'off' : 'on';
			$changed = true;
			$savemsg = gettext("Tunnel status toggled.");
		} elseif (isset($_POST['entries']) && is_array($_POST['entries'])) {
			foreach ($_POST['entries'] as $idx) {
				if (isset($tunnels[$idx])) {
					$current = $tunnels[$idx]['enable'] ?? '';
					$tunnels[$idx]['enable'] = cloudflared_is_enabled($current) ? 'off' : 'on';
				}
			}
			$changed = true;
			$savemsg = gettext("Selected tunnels toggled.");
		}
	}

	if ($changed) {
		config_set_path('installedpackages/cloudflared/config', $tunnels);
		write_config(gettext("Updated Cloudflare Tunnels via Web GUI"));
		cloudflared_resync_config();
		header("Location: cloudflared_tunnels.php?savemsg=" . urlencode($savemsg));
		exit;
	}
}

$tunnels = cloudflared_get_tunnels();
$savemsg = $_GET['savemsg'] ?? '';

$tab_array = array(
	array(gettext("Tunnels"), true, "cloudflared_tunnels.php"),
	array(gettext("Status & Logs"), false, "cloudflared_status.php")
);

include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}

display_top_tabs($tab_array);
?>

<form action="cloudflared_tunnels.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext("Cloudflare Tunnels")?></h2>
		</div>
		<div class="panel-body">
			<div class="table-responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th style="width: 20px;"><input type="checkbox" id="selectAll" name="selectAll"></th>
							<th style="width: 40px;"><?=gettext("Status")?></th>
							<th><?=gettext("Name")?></th>
							<th><?=gettext("Description")?></th>
							<th><?=gettext("Protocol")?></th>
							<th><?=gettext("Log Level")?></th>
							<th class="action-icons"><?=gettext("Actions")?></th>
						</tr>
					</thead>
					<tbody>
						<?php if (empty($tunnels)): ?>
							<tr>
								<td colspan="7" class="text-center text-muted">
									<?=gettext("No Cloudflare Tunnels configured yet.")?>
								</td>
							</tr>
						<?php else: ?>
							<?php foreach ($tunnels as $idx => $t):
								$enabled = cloudflared_is_enabled($t['enable'] ?? '');
							?>
								<tr>
									<td>
										<input type="checkbox" name="entries[]" value="<?=$idx?>">
									</td>
									<td>
										<?php if ($enabled): ?>
											<i class="fa-solid fa-check text-success" title="<?=gettext('Enabled')?>"></i>
										<?php else: ?>
											<i class="fa-solid fa-times text-danger" title="<?=gettext('Disabled')?>"></i>
										<?php endif; ?>
									</td>
									<td><strong><?=htmlspecialchars($t['name'] ?? "tunnel_{$idx}")?></strong></td>
									<td><?=htmlspecialchars($t['descr'] ?? '')?></td>
									<td><?=strtoupper(htmlspecialchars($t['protocol'] ?? 'AUTO'))?></td>
									<td><?=strtoupper(htmlspecialchars($t['log_level'] ?? 'INFO'))?></td>
									<td class="action-icons">
										<?php if ($enabled): ?>
											<a href="?act=toggle&amp;id=<?=$idx?>" class="fa-solid fa-ban" title="<?=gettext('Disable')?>" usepost></a>
										<?php else: ?>
											<a href="?act=toggle&amp;id=<?=$idx?>" class="fa-regular fa-square-check" title="<?=gettext('Enable')?>" usepost></a>
										<?php endif; ?>
										<a href="pkg_edit.php?xml=cloudflared.xml&amp;id=<?=$idx?>" class="fa-solid fa-pencil" title="<?=gettext('Edit')?>"></a>
										<a href="?act=del&amp;id=<?=$idx?>" class="fa-solid fa-trash-can" title="<?=gettext('Delete')?>" usepost></a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<nav class="action-buttons">
		<a href="pkg_edit.php?xml=cloudflared.xml" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add new tunnel')?>">
			<i class="fa-solid fa-plus icon-embed-btn"></i>
			<?=gettext("Add")?>
		</a>
		<button id="del_x" name="del_x" type="submit" class="btn btn-danger btn-sm" value="delete" disabled title="<?=gettext('Delete selected tunnels')?>" usepost>
			<i class="fa-solid fa-trash-can icon-embed-btn"></i>
			<?=gettext("Delete")?>
		</button>
		<button id="toggle_x" name="toggle_x" type="submit" class="btn btn-primary btn-sm" value="toggle" disabled title="<?=gettext('Toggle selected tunnels')?>" usepost>
			<i class="fa-solid fa-ban icon-embed-btn"></i>
			<?=gettext("Toggle")?>
		</button>
	</nav>
</form>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	function updateActionButtons() {
		var selected = $('input[name="entries[]"]:checked').length;
		$('#del_x').prop('disabled', selected === 0);
		$('#toggle_x').prop('disabled', selected === 0);
	}

	$('#selectAll').click(function() {
		$('input[name="entries[]"]').prop('checked', this.checked);
		updateActionButtons();
	});

	$('input[name="entries[]"]').click(function() {
		if (!this.checked) {
			$('#selectAll').prop('checked', false);
		}
		updateActionButtons();
	});
});
//]]>
</script>

<?php include("foot.inc"); ?>
