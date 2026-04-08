<?php
/**
 * IVR Chart Designer
 * Manages the creation and visualization of IVR call routing trees
 */

class ivr_chart_designer {

	/**
	 * Create a new IVR chart node
	 * @param string $node_type - Type: 'ivr', 'extension', 'ring_group', 'voicemail'
	 * @param string $node_id - Unique ID for the node
	 * @param array $config - Node configuration
	 * @return array - Node data
	 */
	public static function create_node($node_type, $node_id, $config = []) {
		$valid_types = ['ivr', 'extension', 'ring_group', 'voicemail', 'transfer', 'disconnect'];
		
		if (!in_array($node_type, $valid_types)) {
			throw new Exception("Invalid node type: $node_type");
		}

		$node = [
			'id' => $node_id,
			'type' => $node_type,
			'label' => $config['label'] ?? ucfirst($node_type),
			'config' => $config,
			'children' => [],
			'x' => $config['x'] ?? 0,
			'y' => $config['y'] ?? 0,
		];

		//type-specific defaults
		switch ($node_type) {
			case 'ivr':
				$node['config']['prompt'] = $config['prompt'] ?? 'Please press 1 for sales, 2 for support';
				$node['config']['timeout'] = $config['timeout'] ?? 5;
				$node['config']['max_attempts'] = $config['max_attempts'] ?? 3;
				break;
			case 'extension':
				$node['config']['extension'] = $config['extension'] ?? '100';
				break;
			case 'ring_group':
				$node['config']['name'] = $config['name'] ?? 'Ring Group';
				$node['config']['extensions'] = $config['extensions'] ?? [];
				break;
			case 'voicemail':
				$node['config']['extension'] = $config['extension'] ?? '';
				break;
		}

		return $node;
	}

	/**
	 * Add a child node to an IVR
	 * @param array $parent_node - Parent node
	 * @param array $child_node - Child node to add
	 * @param int $digit - DTMF digit (1-9) that routes to this child
	 * @return array - Updated parent node
	 */
	public static function add_child_node(&$parent_node, $child_node, $digit = null) {
		if ($parent_node['type'] !== 'ivr') {
			throw new Exception("Can only add children to IVR nodes");
		}

		if ($digit !== null && ($digit < 0 || $digit > 9)) {
			throw new Exception("DTMF digit must be between 0 and 9");
		}

		if (!isset($parent_node['children'])) {
			$parent_node['children'] = [];
		}

		$key = $digit !== null ? $digit : count($parent_node['children']);
		$parent_node['children'][$key] = $child_node;

		return $parent_node;
	}

	/**
	 * Generate a visual representation (JSON) of the chart
	 * @param array $root_node - Root node of the tree
	 * @return array - Visualization data
	 */
	public static function generate_visualization($root_node) {
		$vis = [
			'nodes' => [],
			'edges' => [],
		];

		self::_build_visualization($root_node, $vis, null, null);

		return $vis;
	}

	/**
	 * Recursive helper to build visualization
	 */
	private static function _build_visualization($node, &$vis, $parent_id, $digit) {
		//add node to visualization
		$vis['nodes'][] = [
			'id' => $node['id'],
			'type' => $node['type'],
			'label' => $node['label'],
			'x' => $node['x'] ?? 0,
			'y' => $node['y'] ?? 0,
			'config' => $node['config'],
		];

		//add edge from parent
		if ($parent_id !== null) {
			$vis['edges'][] = [
				'from' => $parent_id,
				'to' => $node['id'],
				'label' => $digit !== null ? "Press $digit" : '',
			];
		}

		//process children
		if (isset($node['children']) && is_array($node['children'])) {
			foreach ($node['children'] as $digit => $child) {
				self::_build_visualization($child, $vis, $node['id'], $digit);
			}
		}
	}

	/**
	 * Validate chart structure
	 * @param array $root_node - Root node
	 * @return array - Validation result with status and errors
	 */
	public static function validate_chart($root_node) {
		$errors = [];
		$visited_ids = [];

		self::_validate_node($root_node, $errors, $visited_ids);

		return [
			'valid' => empty($errors),
			'errors' => $errors,
		];
	}

	/**
	 * Recursive validation helper
	 */
	private static function _validate_node($node, &$errors, &$visited_ids, $path = '') {
		//check for duplicate IDs
		if (isset($visited_ids[$node['id']])) {
			$errors[] = "Duplicate node ID: {$node['id']}";
		}
		$visited_ids[$node['id']] = true;

		//validate node structure
		if (empty($node['type'])) {
			$errors[] = "Node at path '$path' has no type";
		}

		//type-specific validation
		switch ($node['type']) {
			case 'ivr':
				if (empty($node['config']['prompt'])) {
					$errors[] = "IVR node '{$node['id']}' has no prompt";
				}
				break;
			case 'extension':
				if (empty($node['config']['extension'])) {
					$errors[] = "Extension node '{$node['id']}' has no extension";
				}
				break;
		}

		//validate children
		if (isset($node['children']) && is_array($node['children'])) {
			foreach ($node['children'] as $digit => $child) {
				self::_validate_node($child, $errors, $visited_ids, $path . "->" . $child['id']);
			}
		}
	}

	/**
	 * Export chart as JSON
	 * @param array $root_node - Root node
	 * @return string - JSON representation
	 */
	public static function export_json($root_node) {
		return json_encode($root_node, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Import chart from JSON
	 * @param string $json - JSON representation
	 * @return array - Root node
	 */
	public static function import_json($json) {
		$node = json_decode($json, true);
		
		if ($node === null) {
			throw new Exception("Invalid JSON format");
		}

		//validate imported structure
		$validation = self::validate_chart($node);
		if (!$validation['valid']) {
			throw new Exception("Validation failed: " . implode(", ", $validation['errors']));
		}

		return $node;
	}

	/**
	 * Generate HTML visualization of the chart
	 * @param array $root_node - Root node
	 * @return string - HTML/SVG visualization
	 */
	public static function generate_html_visualization($root_node) {
		$viz = self::generate_visualization($root_node);
		
		$html = '<svg class="ivr-chart" width="800" height="600" xmlns="http://www.w3.org/2000/svg">';
		$html .= '<defs>';
		$html .= '<style>';
		$html .= '.ivr-node { fill: #4CAF50; stroke: #333; stroke-width: 2; }';
		$html .= '.ivr-node-ext { fill: #2196F3; stroke: #333; stroke-width: 2; }';
		$html .= '.ivr-node-rg { fill: #FF9800; stroke: #333; stroke-width: 2; }';
		$html .= '.ivr-node-text { font-family: Arial; font-size: 12px; text-anchor: middle; }';
		$html .= '.ivr-edge { stroke: #333; stroke-width: 1; marker-end: url(#arrowhead); }';
		$html .= '.ivr-edge-label { font-family: Arial; font-size: 10px; }';
		$html .= '</style>';
		$html .= '<marker id="arrowhead" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">';
		$html .= '<polygon points="0 0, 10 3, 0 6" fill="#333" />';
		$html .= '</marker>';
		$html .= '</defs>';

		//draw edges first
		foreach ($viz['edges'] as $edge) {
			$html .= '<line class="ivr-edge" x1="' . ($edge['from_x'] ?? 100) . '" y1="' . ($edge['from_y'] ?? 50) . '" x2="' . ($edge['to_x'] ?? 200) . '" y2="' . ($edge['to_y'] ?? 150) . '" />';
			if (!empty($edge['label'])) {
				$html .= '<text class="ivr-edge-label" x="' . (($edge['from_x'] ?? 100 + ($edge['to_x'] ?? 200)) / 2) . '" y="' . (($edge['from_y'] ?? 50 + ($edge['to_y'] ?? 150)) / 2 - 5) . '">' . htmlspecialchars($edge['label']) . '</text>';
			}
		}

		//draw nodes
		$y_offset = 50;
		foreach ($viz['nodes'] as $idx => $node) {
			$x = $node['x'] ?: (($idx % 3) * 200 + 100);
			$y = $node['y'] ?: floor($idx / 3) * 120 + $y_offset;
			$class = 'ivr-node';
			
			if ($node['type'] === 'extension') {
				$class = 'ivr-node-ext';
			} elseif ($node['type'] === 'ring_group') {
				$class = 'ivr-node-rg';
			}
			
			$html .= '<rect class="' . $class . '" x="' . ($x - 40) . '" y="' . ($y - 25) . '" width="80" height="50" rx="5" />';
			$html .= '<text class="ivr-node-text" x="' . $x . '" y="' . $y . '">' . htmlspecialchars($node['label']) . '</text>';
		}

		$html .= '</svg>';
		
		return $html;
	}
}
