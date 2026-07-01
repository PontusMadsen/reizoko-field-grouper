<style>
.fg-form { max-width: 700px; }
.fg-section { margin-bottom: 24px; padding: 20px; background: var(--ee-panel-bg, #fff); border: 1px solid var(--ee-panel-border, #dfe0ef); border-radius: 6px; }
.fg-section h3 { margin: 0 0 16px; font-size: 1.1em; font-weight: 600; }
.fg-row { margin-bottom: 16px; }
.fg-row label { display: block; font-weight: 500; margin-bottom: 4px; }
.fg-row .fg-desc { font-size: 0.85em; color: #8f90b6; margin-bottom: 6px; }
.fg-row input[type="text"],
.fg-row select,
.fg-row textarea { width: 100%; padding: 8px 12px; border: 1px solid var(--ee-panel-border, #dfe0ef); border-radius: 4px; font-size: 14px; }
.fg-radio-group { display: flex; gap: 16px; }
.fg-radio-group label { display: flex; align-items: center; gap: 6px; font-weight: 400; cursor: pointer; }
.fg-actions { margin-top: 24px; }
.fg-actions button { background: #5D63F1; color: #fff; border: none; padding: 10px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; }
.fg-actions button:hover { background: #4a50d4; }
.fg-tag { background: #e4e4ed; padding: 3px 10px; border-radius: 3px; font-size: 13px; display: inline-block; }
.fg-tag-suffix { background: #5D63F1; color: #fff; }
.fg-info-row { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
</style>

<div class="fg-form">
	<?php if (ee()->input->get('success')): ?>
	<div style="background: #dff0d8; border: 1px solid #b2dba1; color: #3c763d; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px;">
		<strong><?= lang('layout_updated') ?></strong> — <?= (int) ee()->input->get('success') ?> tab(s) created.
	</div>
	<?php endif; ?>

	<?= form_open($preview_url) ?>

		<div class="fg-section">
			<h3><?= lang('select_channel') ?></h3>
			<div class="fg-row">
				<select name="channel_id" id="channel-select">
					<option value="">—</option>
					<?php foreach ($channels as $channel): ?>
					<option value="<?= $channel->channel_id ?>"><?= htmlspecialchars($channel->channel_title, ENT_QUOTES, 'UTF-8') ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="fg-section">
			<h3><?= lang('select_layout') ?></h3>
			<div class="fg-row">
				<select name="layout_id" id="layout-select">
					<option value="">— <?= lang('select_channel_first') ?> —</option>
				</select>
			</div>
			<div id="existing-tabs" style="display:none; margin-top: 12px;">
				<div class="fg-desc"><?= lang('existing_tabs') ?>:</div>
				<div id="existing-tabs-list" class="fg-info-row"></div>
			</div>
			<div id="detected-suffixes" style="display:none; margin-top: 12px;">
				<div class="fg-desc"><?= lang('detected_suffixes') ?>:</div>
				<div id="suffixes-list" class="fg-info-row"></div>
			</div>
		</div>

		<div class="fg-section">
			<h3><?= lang('source_tab') ?></h3>
			<div class="fg-desc"><?= lang('source_tab_desc') ?></div>
			<div class="fg-row">
				<select name="source_tab_id" id="source-tab-select">
					<option value="">— <?= lang('select_layout_first') ?> —</option>
				</select>
			</div>
		</div>

		<div class="fg-section">
			<h3><?= lang('affix_rule') ?></h3>

			<div class="fg-row">
				<label><?= lang('delimiter') ?></label>
				<div class="fg-desc"><?= lang('delimiter_desc') ?></div>
				<input type="text" name="delimiter" value="_" style="width: 80px;">
			</div>

			<div class="fg-row">
				<label><?= lang('affix_position') ?></label>
				<div class="fg-radio-group">
					<label>
						<input type="radio" name="affix_position" value="suffix" checked>
						<?= lang('affix_suffix') ?>
					</label>
					<label>
						<input type="radio" name="affix_position" value="prefix">
						<?= lang('affix_prefix') ?>
					</label>
				</div>
			</div>

			<div class="fg-row">
				<label><?= lang('token_labels') ?></label>
				<div class="fg-desc"><?= lang('token_labels_desc') ?></div>
				<textarea name="token_labels" rows="4" placeholder="se=Swedish&#10;en=English&#10;de=German&#10;jp=Japanese"></textarea>
			</div>
		</div>

		<div class="fg-actions">
			<button type="submit"><?= lang('preview_btn') ?></button>
		</div>

	<?= form_close() ?>
</div>

<script>
var layoutsMap = <?= json_encode($layouts_map) ?>;

document.getElementById('channel-select').addEventListener('change', function() {
	var sel = document.getElementById('layout-select');
	sel.innerHTML = '';
	var layouts = layoutsMap[this.value] || [];
	if (layouts.length === 0) {
		sel.innerHTML = '<option value="">— No layouts —</option>';
		showTabs([]);
		showSuffixes([]);
		showSourceTabs([]);
		return;
	}
	layouts.forEach(function(l) {
		var opt = document.createElement('option');
		opt.value = l.id;
		opt.textContent = l.name;
		sel.appendChild(opt);
	});
	updateLayoutInfo(layouts[0]);
});

document.getElementById('layout-select').addEventListener('change', function() {
	var channelId = document.getElementById('channel-select').value;
	var layouts = layoutsMap[channelId] || [];
	var selected = layouts.find(function(l) { return l.id == this.value; }.bind(this));
	if (selected) updateLayoutInfo(selected);
});

function updateLayoutInfo(layout) {
	showTabs(layout.tabs || []);
	showSuffixes(layout.suffixes || []);
	showSourceTabs(layout.tabs || []);
}

function showTabs(tabs) {
	var container = document.getElementById('existing-tabs');
	var list = document.getElementById('existing-tabs-list');
	list.innerHTML = '';
	if (tabs.length === 0) { container.style.display = 'none'; return; }
	container.style.display = 'block';
	tabs.forEach(function(t) {
		var tag = document.createElement('span');
		tag.className = 'fg-tag';
		tag.textContent = t.name;
		list.appendChild(tag);
	});
}

function showSuffixes(suffixes) {
	var container = document.getElementById('detected-suffixes');
	var list = document.getElementById('suffixes-list');
	list.innerHTML = '';
	if (suffixes.length === 0) { container.style.display = 'none'; return; }
	container.style.display = 'block';
	suffixes.forEach(function(s) {
		var tag = document.createElement('span');
		tag.className = 'fg-tag fg-tag-suffix';
		tag.textContent = '_' + s;
		list.appendChild(tag);
	});
}

function showSourceTabs(tabs) {
	var sel = document.getElementById('source-tab-select');
	sel.innerHTML = '';
	if (tabs.length === 0) {
		sel.innerHTML = '<option value="">— No tabs —</option>';
		return;
	}
	tabs.forEach(function(t) {
		var opt = document.createElement('option');
		opt.value = t.id;
		opt.textContent = t.name;
		sel.appendChild(opt);
	});
}
</script>
