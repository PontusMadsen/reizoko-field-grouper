# Field Grouper for ExpressionEngine 7

Auto-arrange channel fields into publish layout tabs by naming convention. Companion to [Field Cloner](https://github.com/PontusMadsen/reizoko-field-cloner).

**The problem:** After cloning fields for multiple languages (`title_se`, `title_de`, `title_ja`), all the clones land in a single "Publish" tab — making the entry form a chaotic mess.

**The solution:** Pick a source tab (e.g. "Content"), and Field Grouper detects all suffixed counterparts, previews the grouping, and creates matching tabs ("Content SE", "Content DE") with fields in the same order as the original.

## Features

- Scans publish layouts for suffix/prefix-grouped fields
- Duplicates source tab structure per language token
- Preserves field order from the source tab
- Preview before applying — see exactly what tabs will be created
- Custom tab labels via `token=Label` mapping (e.g. `se=Swedish`)
- Configurable delimiter (default `_`) and affix position (suffix/prefix)
- Skips fields already assigned to their own tabs — safe to re-run
- Only moves fields from the "Publish" tab — never disturbs existing custom tabs

## Workflow

1. Create base fields → put in a field group → attach to channel
2. Create a "Content" tab in the publish layout, arrange fields
3. Use **Field Cloner** to create language variants (`_se`, `_de`, etc.)
4. Attach the new field groups to the channel
5. Use **Field Grouper** to auto-create matching tabs in the layout

## Requirements

- ExpressionEngine 7.5+
- PHP 8.1+

## Installation

1. Copy `system/user/addons/reizoko_field_grouper` to your EE addons directory
2. Install via Add-ons in the control panel

## Usage

1. Go to **Add-ons → Field Grouper**
2. Select a channel and layout
3. Pick the source tab to duplicate (e.g. "Content")
4. Set delimiter and affix position
5. Optionally map tokens to tab labels (`se=Swedish`)
6. Click **Preview Grouping** to review
7. Check the tabs you want to create
8. Click **Create Tabs**

## License

MIT
