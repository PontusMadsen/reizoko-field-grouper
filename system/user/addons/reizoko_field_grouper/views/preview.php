<style>
.fg-preview { max-width: 800px; }
.fg-tab-preview { margin-bottom: 16px; padding: 16px 20px; background: var(--ee-panel-bg, #fff); border: 1px solid var(--ee-panel-border, #dfe0ef); border-radius: 6px; }
.fg-tab-preview h3 { margin: 0 0 12px; font-size: 1.05em; font-weight: 600; color: #5D63F1; display: flex; align-items: center; gap: 10px; }
.fg-tab-preview h3 .fg-tab-count { font-weight: 400; font-size: 0.85em; color: #8f90b6; }
.fg-tab-preview h3 input[type="checkbox"] { margin: 0; }
.fg-tab-source { border-left: 3px solid #5D63F1; }
.fg-tab-source h3 { color: #8f90b6; }
.fg-field-list { list-style: none; padding: 0; margin: 0; }
.fg-field-list li { padding: 6px 12px; margin-bottom: 4px; background: #f7f7fb; border-radius: 4px; font-size: 14px; display: flex; justify-content: space-between; }
.fg-field-list li .fg-field-type { color: #8f90b6; font-size: 0.85em; }
.fg-field-list li .fg-field-arrow { color: #5D63F1; font-size: 0.85em; }
.fg-actions { margin-top: 24px; display: flex; gap: 12px; }
.fg-actions button, .fg-actions a { padding: 10px 24px; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-block; }
.fg-actions button { background: #5D63F1; color: #fff; border: none; }
.fg-actions button:hover { background: #4a50d4; }
.fg-actions a { background: #e4e4ed; color: #0d0d19; border: none; }
.fg-info { background: #f0f0f8; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
</style>

<div class="fg-preview">
	<?= ee('CP/Alert')->getAllInlines() ?>

	<div class="fg-info">
		<?= lang('layout_label') ?>: <strong><?= htmlspecialchars($layout->layout_name, ENT_QUOTES, 'UTF-8') ?></strong>
		&middot; <?= lang('channel_label') ?>: <strong><?= htmlspecialchars($channel->channel_title, ENT_QUOTES, 'UTF-8') ?></strong>
	</div>

	<!-- Source tab (template) -->
	<div class="fg-tab-preview fg-tab-source">
		<h3><?= lang('source_tab_label') ?>: <?= htmlspecialchars($source_tab['name'], ENT_QUOTES, 'UTF-8') ?> <span class="fg-tab-count">(<?= count($source_fields) ?> fields — template)</span></h3>
		<ul class="fg-field-list">
			<?php foreach ($source_fields as $field): ?>
			<li>
				<?= htmlspecialchars($field->field_label, ENT_QUOTES, 'UTF-8') ?>
				<span class="fg-field-type">{<?= $field->field_name ?>}</span>
			</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<?= form_open($apply_url) ?>
		<input type="hidden" name="layout_id" value="<?= $layout_id ?>">
		<input type="hidden" name="source_tab_id" value="<?= htmlspecialchars($source_tab_id, ENT_QUOTES, 'UTF-8') ?>">
		<input type="hidden" name="delimiter" value="<?= htmlspecialchars($delimiter, ENT_QUOTES, 'UTF-8') ?>">
		<input type="hidden" name="affix_position" value="<?= htmlspecialchars($position, ENT_QUOTES, 'UTF-8') ?>">
		<input type="hidden" name="token_labels" value="<?= htmlspecialchars($token_labels, ENT_QUOTES, 'UTF-8') ?>">

		<?php if (empty($tokens)): ?>
		<div class="fg-tab-preview">
			<p><?= lang('no_suffixed_fields') ?></p>
		</div>
		<?php else: ?>

		<h3 style="margin-bottom: 12px;"><?= lang('new_tabs_heading') ?></h3>

		<?php foreach ($tokens as $token): ?>
		<?php
			$tab_label = isset($label_map[$token]) ? $label_map[$token] : $source_tab['name'] . ' ' . strtoupper($token);
			$fields_for_token = isset($token_field_map[$token]) ? $token_field_map[$token] : array();
		?>
		<div class="fg-tab-preview">
			<h3>
				<input type="checkbox" name="tokens[]" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>" checked>
				<?= htmlspecialchars($tab_label, ENT_QUOTES, 'UTF-8') ?>
				<span class="fg-tab-count">(<?= count($fields_for_token) ?> fields)</span>
			</h3>
			<ul class="fg-field-list">
				<?php foreach ($fields_for_token as $entry): ?>
				<li>
					<?= htmlspecialchars($entry['field']->field_label, ENT_QUOTES, 'UTF-8') ?>
					<span class="fg-field-arrow">{<?= $entry['source_field']->field_name ?>} &rarr; {<?= $entry['field']->field_name ?>}</span>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endforeach; ?>

		<?php endif; ?>

		<?php if ( ! empty($tokens)): ?>
		<div class="fg-actions">
			<button type="submit"><?= lang('apply_btn') ?></button>
			<a href="<?= $base_url ?>"><?= lang('back_btn') ?></a>
		</div>
		<?php endif; ?>
	<?= form_close() ?>
</div>
