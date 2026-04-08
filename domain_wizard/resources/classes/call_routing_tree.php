<?php
/**
 * Call Routing Tree
 * Generates and displays the complete inbound call routing structure
 */

class call_routing_tree {

	/**
	 * Generate complete inbound call tree
	 * @param string $domain_uuid - Domain UUID
	 * @param array $gateways - Gateway/trunk list
	 * @param array $ivr_config - IVR configuration
	 * @param array $extensions - Extensions list
	 * @return array - Tree structure
	 */
	public static function generate_inbound_tree($domain_uuid, $gateways = [], $ivr_config = [], $extensions = []) {
		$tree = [
			'root' => 'inbound_calls',
			'nodes' => [],
			'edges' => [],
		];

		//root node - Inbound Calls
		$tree['nodes']['inbound_calls'] = [
			'id' => 'inbound_calls',
			'type' => 'root',
			'label' => 'Inbound Calls',
			'description' => 'Entry point for all incoming calls',
			'icon' => 'phone-in',
		];

		//add gateway/trunk nodes
		$gateway_count = 0;
		foreach ($gateways as $gateway) {
			$gateway_id = 'gateway_' . sanitize_filename(str_replace(' ', '_', $gateway['name'] ?? 'gateway_' . $gateway_count));
			
			$tree['nodes'][$gateway_id] = [
				'id' => $gateway_id,
				'type' => 'gateway',
				'label' => $gateway['name'] ?? 'Gateway ' . ($gateway_count + 1),
				'description' => 'Incoming trunk: ' . ($gateway['context'] ?? 'default'),
				'config' => $gateway,
				'icon' => 'link-variant',
			];

			//edge: Inbound -> Gateway
			$tree['edges'][] = [
				'from' => 'inbound_calls',
				'to' => $gateway_id,
				'type' => 'trunk',
				'label' => 'DID: ' . ($gateway['did'] ?? '*'),
			];

			$gateway_count++;
		}

		//add IVR or direct routing based on config
		if (!empty($ivr_config)) {
			self::_add_ivr_nodes($tree, $ivr_config, 'inbound_calls', $extensions);
		} else {
			//direct routing to extensions or ring groups
			foreach ($extensions as $ext) {
				$ext_id = 'extension_' . $ext['extension'];
				
				$tree['nodes'][$ext_id] = [
					'id' => $ext_id,
					'type' => 'extension',
					'label' => $ext['extension'] . ' - ' . ($ext['user_context'] ?? 'Unknown'),
					'description' => 'Extension: ' . $ext['extension'],
					'config' => $ext,
					'icon' => 'phone',
				];

				//edge: Inbound -> Extension
				$tree['edges'][] = [
					'from' => 'inbound_calls',
					'to' => $ext_id,
					'type' => 'direct',
					'label' => 'Direct Dial',
				];
			}
		}

		return $tree;
	}

	/**
	 * Add IVR nodes to tree recursively
	 */
	private static function _add_ivr_nodes(&$tree, $ivr_node, $parent_id, $extensions) {
		$node_id = 'ivr_' . $ivr_node['id'];
		
		$tree['nodes'][$node_id] = [
			'id' => $node_id,
			'type' => 'ivr',
			'label' => $ivr_node['label'] ?? 'IVR Menu',
			'description' => $ivr_node['config']['prompt'] ?? 'Press a key to continue',
			'config' => $ivr_node['config'],
			'icon' => 'menu-down',
		];

		//edge from parent
		$tree['edges'][] = [
			'from' => $parent_id,
			'to' => $node_id,
			'type' => 'route',
			'label' => 'Main IVR',
		];

		//process children (menu options)
		if (isset($ivr_node['children'])) {
			foreach ($ivr_node['children'] as $digit => $child) {
				if ($child['type'] === 'ivr') {
					//nested IVR
					self::_add_ivr_nodes($tree, $child, $node_id, $extensions);
				} elseif ($child['type'] === 'extension') {
					//direct to extension
					$ext_id = 'extension_' . $child['config']['extension'];
					
					if (!isset($tree['nodes'][$ext_id])) {
						$tree['nodes'][$ext_id] = [
							'id' => $ext_id,
							'type' => 'extension',
							'label' => $child['config']['extension'] . ' - ' . ($child['label'] ?? 'Extension'),
							'description' => 'Extension: ' . $child['config']['extension'],
							'icon' => 'phone',
						];
					}

					$tree['edges'][] = [
						'from' => $node_id,
						'to' => $ext_id,
						'type' => 'dtmf',
						'label' => "Press $digit",
					];
				} elseif ($child['type'] === 'ring_group') {
					//ring group
					$rg_id = 'ring_group_' . $child['config']['name'];
					
					if (!isset($tree['nodes'][$rg_id])) {
						$tree['nodes'][$rg_id] = [
							'id' => $rg_id,
							'type' => 'ring_group',
							'label' => $child['config']['name'],
							'description' => 'Ring multiple extensions',
							'config' => $child['config'],
							'icon' => 'phone-multiple',
						];

						//add edges from ring group to extensions
						foreach ($child['config']['extensions'] ?? [] as $rg_ext) {
							$rg_ext_id = 'extension_' . $rg_ext;
							
							if (!isset($tree['nodes'][$rg_ext_id])) {
								$tree['nodes'][$rg_ext_id] = [
									'id' => $rg_ext_id,
									'type' => 'extension',
									'label' => $rg_ext,
									'description' => 'Extension: ' . $rg_ext,
									'icon' => 'phone',
								];
							}

							$tree['edges'][] = [
								'from' => $rg_id,
								'to' => $rg_ext_id,
								'type' => 'parallel',
								'label' => 'Ring',
							];
						}
					}

					$tree['edges'][] = [
						'from' => $node_id,
						'to' => $rg_id,
						'type' => 'dtmf',
						'label' => "Press $digit",
					];
				}
			}
		}
	}

	/**
	 * Generate HTML visualization of the tree
	 * @param array $tree - Tree structure
	 * @return string - HTML visualization
	 */
	public static function generate_html_visualization($tree) {
		$html = '<div class="call-routing-tree">';
		$html .= '<h3>Inbound Call Routing Tree</h3>';
		
		//generate SVG diagram
		$html .= self::_generate_svg_diagram($tree);
		
		$html .= '<div class="legend">';
		$html .= '<h4>Legend</h4>';
		$html .= '<ul>';
		$html .= '<li><span class="icon gateway-icon"></span> Gateway/Trunk - Entry point</li>';
		$html .= '<li><span class="icon ivr-icon"></span> IVR Menu - Call routing menu</li>';
		$html .= '<li><span class="icon extension-icon"></span> Extension - Individual user</li>';
		$html .= '<li><span class="icon ring-group-icon"></span> Ring Group - Multiple extensions</li>';
		$html .= '</ul>';
		$html .= '</div>';
		
		$html .= '</div>';
		
		return $html;
	}

	/**
	 * Generate SVG diagram
	 */
	private static function _generate_svg_diagram($tree) {
		$svg = '<svg class="call-tree-diagram" width="100%" height="600" xmlns="http://www.w3.org/2000/svg">';
		
		//styles
		$svg .= '<defs><style>';
		$svg .= '.tree-node { fill: #f0f0f0; stroke: #333; stroke-width: 2; }';
		$svg .= '.tree-node-gateway { fill: #e8f5e9; }';
		$svg .= '.tree-node-ivr { fill: #e3f2fd; }';
		$svg .= '.tree-node-extension { fill: #fff3e0; }';
		$svg .= '.tree-node-ring-group { fill: #fce4ec; }';
		$svg .= '.tree-edge { stroke: #666; stroke-width: 2; fill: none; }';
		$svg .= '.tree-label { font-family: Arial; font-size: 12px; text-anchor: middle; }';
		$svg .= '.tree-edge-label { font-family: Arial; font-size: 10px; fill: #666; }';
		$svg .= '</style></defs>';

		//draw edges
		$positions = self::_calculate_node_positions($tree);
		
		foreach ($tree['edges'] as $edge) {
			$from_pos = $positions[$edge['from']] ?? ['x' => 100, 'y' => 50];
			$to_pos = $positions[$edge['to']] ?? ['x' => 200, 'y' => 150];
			
			$svg .= '<path class="tree-edge" d="M ' . $from_pos['x'] . ' ' . $from_pos['y'] . ' Q ' . (($from_pos['x'] + $to_pos['x']) / 2) . ' ' . (($from_pos['y'] + $to_pos['y']) / 2 + 20) . ' ' . $to_pos['x'] . ' ' . $to_pos['y'] . '" />';
			
			//edge label
			$mid_x = ($from_pos['x'] + $to_pos['x']) / 2;
			$mid_y = ($from_pos['y'] + $to_pos['y']) / 2;
			if (!empty($edge['label'])) {
				$svg .= '<text class="tree-edge-label" x="' . $mid_x . '" y="' . ($mid_y - 5) . '">' . htmlspecialchars($edge['label']) . '</text>';
			}
		}

		//draw nodes
		foreach ($tree['nodes'] as $node) {
			$pos = $positions[$node['id']] ?? ['x' => 100, 'y' => 50];
			$node_class = 'tree-node tree-node-' . str_replace('_', '-', $node['type']);
			
			$width = 120;
			$height = 60;
			
			$svg .= '<rect class="' . $node_class . '" x="' . ($pos['x'] - $width / 2) . '" y="' . ($pos['y'] - $height / 2) . '" width="' . $width . '" height="' . $height . '" rx="5" />';
			$svg .= '<text class="tree-label" x="' . $pos['x'] . '" y="' . ($pos['y'] - 5) . '" font-weight="bold">' . htmlspecialchars($node['label']) . '</text>';
			$svg .= '<text class="tree-label" x="' . $pos['x'] . '" y="' . ($pos['y'] + 15) . '" font-size="10">' . htmlspecialchars(substr($node['description'], 0, 20)) . '</text>';
		}

		$svg .= '</svg>';
		
		return $svg;
	}

	/**
	 * Calculate node positions for tree layout
	 */
	private static function _calculate_node_positions($tree) {
		$positions = [];
		$root_id = $tree['root'];
		
		//place root node at top center
		$positions[$root_id] = ['x' => 400, 'y' => 50];

		//calculate children positions
		$level = 1;
		$current_level = [$root_id];
		$x_spacing = 150;
		$y_spacing = 120;

		while (!empty($current_level)) {
			$next_level = [];
			$children_by_parent = [];

			//group nodes by parent
			foreach ($tree['edges'] as $edge) {
				if (in_array($edge['from'], $current_level)) {
					if (!isset($children_by_parent[$edge['from']])) {
						$children_by_parent[$edge['from']] = [];
					}
					$children_by_parent[$edge['from']][] = $edge['to'];
					$next_level[] = $edge['to'];
				}
			}

			$next_level = array_unique($next_level);

			//position children
			$children_count = count($next_level);
			$start_x = 200 - ($children_count * $x_spacing / 2);
			
			foreach ($next_level as $idx => $child_id) {
				$positions[$child_id] = [
					'x' => $start_x + ($idx * $x_spacing),
					'y' => 50 + ($level * $y_spacing),
				];
			}

			$current_level = $next_level;
			$level++;
		}

		return $positions;
	}

	/**
	 * Export tree as JSON
	 */
	public static function export_json($tree) {
		return json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Generate text representation
	 */
	public static function generate_text_representation($tree) {
		$text = "Call Routing Tree\n";
		$text .= "=================\n\n";

		foreach ($tree['nodes'] as $node) {
			$text .= "Node: " . $node['label'] . "\n";
			$text .= "  Type: " . $node['type'] . "\n";
			$text .= "  Description: " . $node['description'] . "\n";
			$text .= "\n";
		}

		$text .= "\nRouting Rules\n";
		$text .= "--------------\n";

		foreach ($tree['edges'] as $edge) {
			$from_label = $tree['nodes'][$edge['from']]['label'] ?? $edge['from'];
			$to_label = $tree['nodes'][$edge['to']]['label'] ?? $edge['to'];
			$text .= $from_label . " → " . $to_label;
			if (!empty($edge['label'])) {
				$text .= " (" . $edge['label'] . ")";
			}
			$text .= "\n";
		}

		return $text;
	}
}
