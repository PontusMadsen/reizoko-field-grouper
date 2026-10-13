<?php

class Reizoko_field_grouper_mcp
{
	/**
	 * Main CP page — pick channel + layout + source tab + affix rule
	 */
	public function index()
	{
		$this->_require_super_admin();

		$channels = ee('Model')->get('Channel')
			->order('channel_title', 'asc')
			->all();

		// Build channel → layouts map (with tabs and suffix info) for JS
		$layouts_map = array();

		// Collect all field IDs across all layouts for batch loading
		$all_field_ids = array();

		foreach ($channels as $channel)
		{
			$layouts = ee('Model')->get('ChannelLayout')
				->filter('channel_id', $channel->channel_id)
				->all();

			$layouts_map[$channel->channel_id] = array();
			foreach ($layouts as $layout)
			{
				$tabs = array();
				if (is_array($layout->field_layout))
				{
					foreach ($layout->field_layout as $tab)
					{
						if (isset($tab['name']) && isset($tab['id']))
						{
							$tab_info = array(
								'id'   => $tab['id'],
								'name' => $tab['name'],
							);
							$tabs[] = $tab_info;
						}
					}

					// Collect field IDs
					$ids = $this->_extract_field_ids($layout->field_layout);
					$all_field_ids = array_merge($all_field_ids, $ids);
				}

				$layouts_map[$channel->channel_id][] = array(
					'id'   => $layout->layout_id,
					'name' => $layout->layout_name,
					'tabs' => $tabs,
				);
			}
		}

		// Load all field models to detect suffixes
		$all_field_ids = array_unique($all_field_ids);
		$suffix_map = array();

		if ( ! empty($all_field_ids))
		{
			$fields = ee('Model')->get('ChannelField', $all_field_ids)->all();
			foreach ($fields as $f)
			{
				$parts = explode('_', $f->field_name);
				if (count($parts) >= 2)
				{
					$suffix = end($parts);
					$suffix_map[$f->field_id] = $suffix;
				}
			}
		}

		// Add detected suffixes per layout
		foreach ($layouts_map as $ch_id => &$layouts_list)
		{
			foreach ($layouts_list as &$layout_entry)
			{
				$layout = ee('Model')->get('ChannelLayout', $layout_entry['id'])->first();
				$suffixes = array();

				if ($layout && is_array($layout->field_layout))
				{
					foreach ($layout->field_layout as $tab)
					{
						if (empty($tab['fields']))
						{
							continue;
						}

						foreach ($tab['fields'] as $f)
						{
							if (isset($f['field']) && strpos($f['field'], 'field_id_') === 0)
							{
								$fid = (int) str_replace('field_id_', '', $f['field']);
								if (isset($suffix_map[$fid]))
								{
									$suffixes[] = $suffix_map[$fid];
								}
							}
						}
					}
				}

				$layout_entry['suffixes'] = array_values(array_unique($suffixes));
			}
		}
		unset($layouts_list, $layout_entry);

		$vars = array(
			'channels'    => $channels,
			'layouts_map' => $layouts_map,
			'preview_url' => ee('CP/URL')->make('addons/settings/reizoko_field_grouper/preview'),
			'cp_page_title' => lang('field_grouper_module_name'),
		);

		return array(
			'heading' => lang('field_grouper_module_name'),
			'body'    => ee('View')->make('reizoko_field_grouper:index')->render($vars),
			'breadcrumb' => array(
				ee('CP/URL')->make('addons/settings/reizoko_field_grouper')->compile() => lang('field_grouper_module_name'),
			),
		);
	}

	/**
	 * Preview — show source tab duplication per suffix
	 */
	public function preview()
	{
		$this->_require_super_admin();

		$layout_id = (int) ee()->input->post('layout_id');
		$source_tab_id = ee()->input->post('source_tab_id');
		$delimiter = ee()->input->post('delimiter') ?: '_';
		$position = ee()->input->post('affix_position') ?: 'suffix';
		$token_labels = ee()->input->post('token_labels') ?: '';

		$layout = ee('Model')->get('ChannelLayout', $layout_id)->first();

		if ( ! $layout)
		{
			ee()->functions->redirect(ee('CP/URL')->make('addons/settings/reizoko_field_grouper'));
		}

		$channel = ee('Model')->get('Channel', $layout->channel_id)->first();
		$field_layout = $layout->field_layout;

		// Find source tab
		$source_tab = NULL;
		foreach ($field_layout as $tab)
		{
			if ($tab['id'] === $source_tab_id)
			{
				$source_tab = $tab;
				break;
			}
		}

		if ( ! $source_tab)
		{
			ee()->functions->redirect(ee('CP/URL')->make('addons/settings/reizoko_field_grouper'));
		}

		// Get source tab's field IDs (these are the "base" fields)
		$source_field_ids = array();
		foreach (($source_tab['fields'] ?? array()) as $f)
		{
			if (isset($f['field']) && strpos($f['field'], 'field_id_') === 0)
			{
				$source_field_ids[] = (int) str_replace('field_id_', '', $f['field']);
			}
		}

		// Load source field models
		$source_fields = array();
		if ( ! empty($source_field_ids))
		{
			$models = ee('Model')->get('ChannelField', $source_field_ids)->all();
			foreach ($models as $f)
			{
				$source_fields[$f->field_id] = $f;
			}
		}

		// Get all custom fields in the layout
		$all_field_ids = $this->_extract_field_ids($field_layout);
		$all_fields = array();
		if ( ! empty($all_field_ids))
		{
			$models = ee('Model')->get('ChannelField', $all_field_ids)->all();
			foreach ($models as $f)
			{
				$all_fields[$f->field_id] = $f;
			}
		}

		// Build set of field IDs already in their own tabs (not publish, not source)
		$already_tabbed = array();
		foreach ($field_layout as $tab)
		{
			if ($tab['id'] === 'publish' || $tab['id'] === $source_tab_id)
			{
				continue;
			}

			if ( ! empty($tab['fields']))
			{
				foreach ($tab['fields'] as $f)
				{
					if (isset($f['field']) && strpos($f['field'], 'field_id_') === 0)
					{
						$already_tabbed[] = (int) str_replace('field_id_', '', $f['field']);
					}
				}
			}
		}

		// Find suffix tokens by matching suffixed versions of source fields
		$tokens = array();
		$token_field_map = array();

		foreach ($all_fields as $field)
		{
			// Skip fields already in their own tab
			if (in_array($field->field_id, $already_tabbed))
			{
				continue;
			}

			$token = $this->_extract_token($field->field_name, $delimiter, $position);

			if ($token === NULL)
			{
				continue;
			}

			// Check if this field's base name (without suffix) matches a source field
			$base_name = $this->_strip_token($field->field_name, $delimiter, $position);

			foreach ($source_fields as $src)
			{
				if ($src->field_name === $base_name)
				{
					$tokens[$token] = TRUE;
					$token_field_map[$token][] = array(
						'field'        => $field,
						'source_field' => $src,
					);
					break;
				}
			}
		}

		// Check which tokens already have tabs
		$existing_tab_ids = array();
		foreach ($field_layout as $tab)
		{
			$existing_tab_ids[] = $tab['id'];
		}

		$label_map = $this->_parse_token_labels($token_labels);

		$vars = array(
			'channel'          => $channel,
			'layout'           => $layout,
			'source_tab'       => $source_tab,
			'source_fields'    => $source_fields,
			'tokens'           => array_keys($tokens),
			'token_field_map'  => $token_field_map,
			'label_map'        => $label_map,
			'existing_tab_ids' => $existing_tab_ids,
			'layout_id'        => $layout_id,
			'source_tab_id'    => $source_tab_id,
			'delimiter'        => $delimiter,
			'position'         => $position,
			'token_labels'     => $token_labels,
			'base_url'         => ee('CP/URL')->make('addons/settings/reizoko_field_grouper'),
			'apply_url'        => ee('CP/URL')->make('addons/settings/reizoko_field_grouper/apply'),
			'cp_page_title'    => lang('preview_heading'),
		);

		return array(
			'heading' => lang('preview_heading'),
			'body'    => ee('View')->make('reizoko_field_grouper:preview')->render($vars),
			'breadcrumb' => array(
				ee('CP/URL')->make('addons/settings/reizoko_field_grouper')->compile() => lang('field_grouper_module_name'),
			),
		);
	}

	/**
	 * Apply — duplicate source tab for each selected token
	 */
	public function apply()
	{
		$this->_require_super_admin();

		$layout_id = (int) ee()->input->post('layout_id');
		$source_tab_id = ee()->input->post('source_tab_id');
		$delimiter = ee()->input->post('delimiter') ?: '_';
		$position = ee()->input->post('affix_position') ?: 'suffix';
		$token_labels = ee()->input->post('token_labels') ?: '';
		$selected_tokens = (array) ee()->input->post('tokens');

		if (empty($selected_tokens))
		{
			ee('CP/Alert')->makeInline('shared-form')
				->asIssue()
				->withTitle(lang('grouper_error'))
				->addToBody(lang('no_tokens_selected'))
				->defer();

			ee()->functions->redirect(ee('CP/URL')->make('addons/settings/reizoko_field_grouper'));
		}

		$layout = ee('Model')->get('ChannelLayout', $layout_id)->first();

		if ( ! $layout)
		{
			ee()->functions->redirect(ee('CP/URL')->make('addons/settings/reizoko_field_grouper'));
		}

		$field_layout = $layout->field_layout;
		$label_map = $this->_parse_token_labels($token_labels);

		// Find source tab and its field order
		$source_tab = NULL;
		$source_tab_index = 0;
		foreach ($field_layout as $i => $tab)
		{
			if ($tab['id'] === $source_tab_id)
			{
				$source_tab = $tab;
				$source_tab_index = $i;
				break;
			}
		}

		if ( ! $source_tab)
		{
			ee()->functions->redirect(ee('CP/URL')->make('addons/settings/reizoko_field_grouper'));
		}

		// Get source field models
		$source_field_ids = array();
		foreach (($source_tab['fields'] ?? array()) as $f)
		{
			if (isset($f['field']) && strpos($f['field'], 'field_id_') === 0)
			{
				$source_field_ids[] = (int) str_replace('field_id_', '', $f['field']);
			}
		}

		$source_fields = array();
		if ( ! empty($source_field_ids))
		{
			$models = ee('Model')->get('ChannelField', $source_field_ids)->all();
			foreach ($models as $f)
			{
				$source_fields[$f->field_id] = $f;
			}
		}

		// Get all fields to find suffixed matches
		$all_field_ids = $this->_extract_field_ids($field_layout);
		$all_fields = array();
		if ( ! empty($all_field_ids))
		{
			$models = ee('Model')->get('ChannelField', $all_field_ids)->all();
			foreach ($models as $f)
			{
				$all_fields[$f->field_id] = $f;
			}
		}

		// Build set of field IDs already in their own tabs
		$already_tabbed = array();
		foreach ($field_layout as $tab)
		{
			if ($tab['id'] === 'publish' || $tab['id'] === $source_tab_id)
			{
				continue;
			}

			if ( ! empty($tab['fields']))
			{
				foreach ($tab['fields'] as $f)
				{
					if (isset($f['field']) && strpos($f['field'], 'field_id_') === 0)
					{
						$already_tabbed[] = 'field_id_' . str_replace('field_id_', '', $f['field']);
					}
				}
			}
		}

		// Build token → field mapping (preserving source tab field order)
		$token_field_ids = array();
		foreach ($selected_tokens as $token)
		{
			$token_field_ids[$token] = array();

			// For each source field, find its suffixed counterpart
			foreach (($source_tab['fields'] ?? array()) as $src_entry)
			{
				if ( ! isset($src_entry['field']) || strpos($src_entry['field'], 'field_id_') !== 0)
				{
					continue;
				}

				$src_fid = (int) str_replace('field_id_', '', $src_entry['field']);

				if ( ! isset($source_fields[$src_fid]))
				{
					continue;
				}

				$src_name = $source_fields[$src_fid]->field_name;
				$target_name = ($position === 'suffix')
					? $src_name . $delimiter . $token
					: $token . $delimiter . $src_name;

				// Find matching field (skip if already in a tab)
				foreach ($all_fields as $f)
				{
					if ($f->field_name === $target_name)
					{
						$fid_key = 'field_id_' . $f->field_id;
						if ( ! in_array($fid_key, $already_tabbed))
						{
							$token_field_ids[$token][] = $fid_key;
						}
						break;
					}
				}
			}
		}

		// Remove matched fields from the publish tab only
		$fields_to_move = array();
		foreach ($token_field_ids as $fids)
		{
			$fields_to_move = array_merge($fields_to_move, $fids);
		}

		foreach ($field_layout as &$tab)
		{
			if ($tab['id'] !== 'publish')
			{
				continue;
			}

			$tab['fields'] = array_values(array_filter($tab['fields'], function ($f) use ($fields_to_move)
			{
				return ! in_array($f['field'], $fields_to_move);
			}));
		}
		unset($tab);

		// Build new tabs — insert right after source tab
		$new_tabs = array();
		foreach ($selected_tokens as $token)
		{
			if (empty($token_field_ids[$token]))
			{
				continue;
			}

			$tab_label = isset($label_map[$token])
				? $label_map[$token]
				: $source_tab['name'] . ' ' . strtoupper($token);

			$tab_id = 'custom__' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $tab_label));

			$tab_fields = array();
			foreach ($token_field_ids[$token] as $fid)
			{
				$tab_fields[] = array(
					'field'     => $fid,
					'visible'   => TRUE,
					'collapsed' => FALSE,
					'width'     => 100,
				);
			}

			$new_tabs[] = array(
				'id'      => $tab_id,
				'name'    => $tab_label,
				'visible' => TRUE,
				'fields'  => $tab_fields,
			);
		}

		// Insert after source tab
		array_splice($field_layout, $source_tab_index + 1, 0, $new_tabs);

		// Save
		$layout->field_layout = $field_layout;
		$layout->save();

		$url = ee('CP/URL')->make('addons/settings/reizoko_field_grouper');
		$url->setQueryStringVariable('success', count($new_tabs));
		ee()->functions->redirect($url);
	}

	/**
	 * Changing publish layouts is an admin task, so restrict to Super Admins
	 */
	private function _require_super_admin()
	{
		if ( ! ee('Permission')->isSuperAdmin())
		{
			show_error(lang('unauthorized_access'), 403);
		}
	}

	/**
	 * Extract custom field IDs from layout structure
	 */
	private function _extract_field_ids($field_layout)
	{
		$ids = array();

		foreach ($field_layout as $tab)
		{
			if (empty($tab['fields']))
			{
				continue;
			}

			foreach ($tab['fields'] as $f)
			{
				if (isset($f['field']) && strpos($f['field'], 'field_id_') === 0)
				{
					$ids[] = (int) str_replace('field_id_', '', $f['field']);
				}
			}
		}

		return array_unique($ids);
	}

	/**
	 * Extract affix token from field name
	 */
	private function _extract_token($field_name, $delimiter, $position)
	{
		$parts = explode($delimiter, $field_name);

		if (count($parts) < 2)
		{
			return NULL;
		}

		if ($position === 'suffix')
		{
			return end($parts);
		}
		else
		{
			return $parts[0];
		}
	}

	/**
	 * Strip token from field name to get base name
	 */
	private function _strip_token($field_name, $delimiter, $position)
	{
		$parts = explode($delimiter, $field_name);

		if (count($parts) < 2)
		{
			return $field_name;
		}

		if ($position === 'suffix')
		{
			array_pop($parts);
		}
		else
		{
			array_shift($parts);
		}

		return implode($delimiter, $parts);
	}

	/**
	 * Parse token→label map from textarea
	 */
	private function _parse_token_labels($input)
	{
		$map = array();
		$lines = array_filter(array_map('trim', explode("\n", $input)));

		foreach ($lines as $line)
		{
			if (strpos($line, '=') !== FALSE)
			{
				list($token, $label) = explode('=', $line, 2);
			}
			elseif (strpos($line, ':') !== FALSE)
			{
				list($token, $label) = explode(':', $line, 2);
			}
			else
			{
				continue;
			}

			$map[trim($token)] = trim(strip_tags($label));
		}

		return $map;
	}
}
