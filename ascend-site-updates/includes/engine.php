<?php
/**
 * Pure functions that apply one change to an Elementor elements tree.
 * No WordPress calls here, so it can be unit-tested with plain PHP.
 *
 * Each apply function returns [ $elements, $status, $detail ] where status is:
 *   'applied' - the change was made on this run
 *   'done'    - the tree already contains the result (safe to re-run)
 *   'missing' - the target was not found; nothing changed
 */

if ( ! function_exists( 'asu_apply_change' ) ) {

	function asu_apply_change( array $elements, array $change ) {
		switch ( $change['type'] ) {
			case 'replace':
				return asu_op_replace( $elements, $change );
			case 'set':
				return asu_op_set( $elements, $change );
			case 'remove':
				return asu_op_remove( $elements, $change );
			case 'remove_item':
				return asu_op_remove_item( $elements, $change );
			case 'add_item':
				return asu_op_add_item( $elements, $change );
			case 'set_item':
				return asu_op_set_item( $elements, $change );
			case 'insert':
				return asu_op_insert( $elements, $change );
			case 'replace_all':
				return asu_op_replace_all( $elements, $change );
		}
		return array( $elements, 'missing', 'Unknown change type: ' . $change['type'] );
	}

	/** Find an element by id and pass it by reference to $fn. Returns true if found. */
	function asu_walk_find( array &$elements, $id, callable $fn ) {
		foreach ( $elements as &$el ) {
			if ( isset( $el['id'] ) && $el['id'] === $id ) {
				$fn( $el );
				return true;
			}
			if ( ! empty( $el['elements'] ) && asu_walk_find( $el['elements'], $id, $fn ) ) {
				return true;
			}
		}
		return false;
	}

	function asu_element_exists( array $elements, $id ) {
		$found = false;
		asu_walk_find( $elements, $id, function () use ( &$found ) { $found = true; } );
		return $found;
	}

	/** Replace inside every string of $value (recursively); returns [new, occurrences]. */
	function asu_replace_in_value( $value, $find, $replace ) {
		if ( is_string( $value ) ) {
			$n = substr_count( $value, $find );
			return array( $n ? str_replace( $find, $replace, $value ) : $value, $n );
		}
		if ( is_array( $value ) ) {
			$total = 0;
			foreach ( $value as $k => $v ) {
				list( $value[ $k ], $n ) = asu_replace_in_value( $v, $find, $replace );
				$total += $n;
			}
			return array( $value, $total );
		}
		return array( $value, 0 );
	}

	function asu_value_contains( $value, $needle ) {
		if ( is_string( $value ) ) {
			return '' !== $needle && false !== strpos( $value, $needle );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $v ) {
				if ( asu_value_contains( $v, $needle ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Read a dotted setting path like "link.url" or "tabs.3.tab_content". */
	function asu_path_get( array $arr, $path ) {
		foreach ( explode( '.', $path ) as $key ) {
			if ( ! is_array( $arr ) || ! array_key_exists( $key, $arr ) ) {
				return null;
			}
			$arr = $arr[ $key ];
		}
		return $arr;
	}

	function asu_path_set( array &$arr, $path, $value ) {
		$ref  = &$arr;
		$keys = explode( '.', $path );
		foreach ( $keys as $i => $key ) {
			if ( $i === count( $keys ) - 1 ) {
				$ref[ $key ] = $value;
				return;
			}
			if ( ! isset( $ref[ $key ] ) || ! is_array( $ref[ $key ] ) ) {
				$ref[ $key ] = array();
			}
			$ref = &$ref[ $key ];
		}
	}

	/**
	 * replace: { element?, setting?, find, replace, count? }
	 * Without element, every element is searched. Without setting, every setting is searched.
	 */
	function asu_op_replace( array $elements, array $c ) {
		$total   = 0;
		$already = false;
		$visit   = function ( array &$el ) use ( $c, &$total, &$already ) {
			if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) {
				return;
			}
			if ( ! empty( $c['setting'] ) ) {
				$cur = asu_path_get( $el['settings'], $c['setting'] );
				if ( null === $cur ) {
					return;
				}
				list( $new, $n ) = asu_replace_in_value( $cur, $c['find'], $c['replace'] );
				if ( $n ) {
					asu_path_set( $el['settings'], $c['setting'], $new );
				}
				$already = $already || asu_value_contains( $cur, $c['replace'] );
			} else {
				list( $el['settings'], $n ) = asu_replace_in_value( $el['settings'], $c['find'], $c['replace'] );
				$already = $already || asu_value_contains( $el['settings'], $c['replace'] );
			}
			$total += $n;
		};

		$candidate = $elements;
		if ( ! empty( $c['element'] ) ) {
			asu_walk_find( $candidate, $c['element'], $visit );
		} else {
			asu_walk_all( $candidate, $visit );
		}

		if ( $total > 0 ) {
			if ( isset( $c['count'] ) && (int) $c['count'] !== $total ) {
				return array( $elements, 'missing', sprintf( 'Expected %d match(es), found %d; left unchanged.', $c['count'], $total ) );
			}
			return array( $candidate, 'applied', sprintf( '%d replacement(s).', $total ) );
		}
		if ( $already && '' !== $c['replace'] ) {
			return array( $elements, 'done', 'Already updated.' );
		}
		if ( '' === $c['replace'] && ! empty( $c['done_if_absent'] ) ) {
			return array( $elements, 'done', 'Already removed.' );
		}
		return array( $elements, 'missing', 'Text to replace was not found.' );
	}

	function asu_walk_all( array &$elements, callable $fn ) {
		foreach ( $elements as &$el ) {
			$fn( $el );
			if ( ! empty( $el['elements'] ) ) {
				asu_walk_all( $el['elements'], $fn );
			}
		}
	}

	/** set: { element, setting, value } (value null removes the key) */
	function asu_op_set( array $elements, array $c ) {
		$status = 'missing';
		$found  = asu_walk_find( $elements, $c['element'], function ( array &$el ) use ( $c, &$status ) {
			if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) {
				$el['settings'] = array();
			}
			$cur = asu_path_get( $el['settings'], $c['setting'] );
			if ( $cur === $c['value'] ) {
				$status = 'done';
				return;
			}
			if ( null === $c['value'] ) {
				$keys = explode( '.', $c['setting'] );
				$last = array_pop( $keys );
				$ref  = &$el['settings'];
				foreach ( $keys as $k ) {
					$ref = &$ref[ $k ];
				}
				unset( $ref[ $last ] );
			} else {
				asu_path_set( $el['settings'], $c['setting'], $c['value'] );
			}
			$status = 'applied';
		} );
		if ( ! $found ) {
			return array( $elements, 'missing', 'Element ' . $c['element'] . ' not found.' );
		}
		return array( $elements, $status, 'done' === $status ? 'Already set.' : 'Setting updated.' );
	}

	/** remove: { element } */
	function asu_op_remove( array $elements, array $c ) {
		$removed = false;
		$new     = asu_remove_by_id( $elements, $c['element'], $removed );
		if ( $removed ) {
			return array( $new, 'applied', 'Removed.' );
		}
		return array( $elements, 'done', 'Already removed (or not present).' );
	}

	function asu_remove_by_id( array $elements, $id, &$removed ) {
		$out = array();
		foreach ( $elements as $el ) {
			if ( isset( $el['id'] ) && $el['id'] === $id ) {
				$removed = true;
				continue;
			}
			if ( ! empty( $el['elements'] ) ) {
				$el['elements'] = asu_remove_by_id( $el['elements'], $id, $removed );
			}
			$out[] = $el;
		}
		return $out;
	}

	/** remove_item: { element, setting (repeater key), match_key, match } */
	function asu_op_remove_item( array $elements, array $c ) {
		$removed = 0;
		$found   = asu_walk_find( $elements, $c['element'], function ( array &$el ) use ( $c, &$removed ) {
			$list = asu_path_get( $el['settings'], $c['setting'] );
			if ( ! is_array( $list ) ) {
				return;
			}
			$keep = array();
			foreach ( $list as $item ) {
				$val = is_array( $item ) && isset( $item[ $c['match_key'] ] ) ? $item[ $c['match_key'] ] : '';
				if ( is_string( $val ) && false !== strpos( $val, $c['match'] ) ) {
					$removed++;
					continue;
				}
				$keep[] = $item;
			}
			asu_path_set( $el['settings'], $c['setting'], $keep );
		} );
		if ( ! $found ) {
			return array( $elements, 'missing', 'Element ' . $c['element'] . ' not found.' );
		}
		return $removed
			? array( $elements, 'applied', sprintf( 'Removed %d item(s).', $removed ) )
			: array( $elements, 'done', 'Item already removed.' );
	}

	/** add_item: { element, setting, item, match_key, position? ('end' | index) } */
	function asu_op_add_item( array $elements, array $c ) {
		$status = 'missing';
		$found  = asu_walk_find( $elements, $c['element'], function ( array &$el ) use ( $c, &$status ) {
			$list = asu_path_get( $el['settings'], $c['setting'] );
			if ( ! is_array( $list ) ) {
				$list = array();
			}
			foreach ( $list as $item ) {
				if ( isset( $item[ $c['match_key'] ], $c['item'][ $c['match_key'] ] ) && $item[ $c['match_key'] ] === $c['item'][ $c['match_key'] ] ) {
					$status = 'done';
					return;
				}
			}
			$pos = isset( $c['position'] ) && is_int( $c['position'] ) ? $c['position'] : count( $list );
			array_splice( $list, $pos, 0, array( $c['item'] ) );
			asu_path_set( $el['settings'], $c['setting'], $list );
			$status = 'applied';
		} );
		if ( ! $found ) {
			return array( $elements, 'missing', 'Element ' . $c['element'] . ' not found.' );
		}
		return array( $elements, $status, 'applied' === $status ? 'Item added.' : 'Item already present.' );
	}

	/**
	 * set_item: { element, setting (repeater key), match_key, match, key (dotted path inside the item), value }
	 * Updates the repeater item(s) whose match_key contains match.
	 */
	function asu_op_set_item( array $elements, array $c ) {
		$status = 'missing';
		$found  = asu_walk_find( $elements, $c['element'], function ( array &$el ) use ( $c, &$status ) {
			$list = asu_path_get( $el['settings'], $c['setting'] );
			if ( ! is_array( $list ) ) {
				return;
			}
			foreach ( $list as $i => $item ) {
				$val = is_array( $item ) && isset( $item[ $c['match_key'] ] ) ? $item[ $c['match_key'] ] : '';
				if ( ! is_string( $val ) || false === strpos( $val, $c['match'] ) ) {
					continue;
				}
				if ( asu_path_get( $item, $c['key'] ) === $c['value'] ) {
					if ( 'applied' !== $status ) {
						$status = 'done';
					}
					continue;
				}
				asu_path_set( $list[ $i ], $c['key'], $c['value'] );
				$status = 'applied';
			}
			asu_path_set( $el['settings'], $c['setting'], $list );
		} );
		if ( ! $found ) {
			return array( $elements, 'missing', 'Element ' . $c['element'] . ' not found.' );
		}
		$detail = array( 'applied' => 'Item updated.', 'done' => 'Already updated.', 'missing' => 'No item matched.' );
		return array( $elements, $status, $detail[ $status ] );
	}

	/**
	 * insert: { new (element array with its own id), after | before | append_to | prepend_to | at_end }
	 * The new element's id makes this idempotent.
	 */
	function asu_op_insert( array $elements, array $c ) {
		$new = $c['new'];
		if ( asu_element_exists( $elements, $new['id'] ) ) {
			return array( $elements, 'done', 'Already inserted.' );
		}
		if ( ! empty( $c['at_end'] ) ) {
			$elements[] = $new;
			return array( $elements, 'applied', 'Added at the end of the page.' );
		}
		$ok = false;
		if ( ! empty( $c['append_to'] ) || ! empty( $c['prepend_to'] ) ) {
			$target = ! empty( $c['append_to'] ) ? $c['append_to'] : $c['prepend_to'];
			$ok     = asu_walk_find( $elements, $target, function ( array &$el ) use ( $new, $c ) {
				if ( ! isset( $el['elements'] ) ) {
					$el['elements'] = array();
				}
				if ( ! empty( $c['append_to'] ) ) {
					$el['elements'][] = $new;
				} else {
					array_unshift( $el['elements'], $new );
				}
			} );
		} else {
			$anchor = ! empty( $c['after'] ) ? $c['after'] : $c['before'];
			$after  = ! empty( $c['after'] );
			$elements = asu_insert_sibling( $elements, $anchor, $new, $after, $ok );
		}
		return $ok
			? array( $elements, 'applied', 'Inserted.' )
			: array( $elements, 'missing', 'Anchor element not found.' );
	}

	function asu_insert_sibling( array $elements, $anchor, array $new, $after, &$ok ) {
		$out = array();
		foreach ( $elements as $el ) {
			$hit = isset( $el['id'] ) && $el['id'] === $anchor;
			if ( $hit && ! $after ) {
				$out[] = $new;
				$ok    = true;
			}
			if ( ! $hit && ! empty( $el['elements'] ) ) {
				$el['elements'] = asu_insert_sibling( $el['elements'], $anchor, $new, $after, $ok );
			}
			$out[] = $el;
			if ( $hit && $after ) {
				$out[] = $new;
				$ok    = true;
			}
		}
		return $out;
	}

	/** replace_all: { elements } replaces the whole page layout (first element id makes it idempotent). */
	function asu_op_replace_all( array $elements, array $c ) {
		$first = isset( $c['elements'][0]['id'] ) ? $c['elements'][0]['id'] : null;
		if ( $first && isset( $elements[0]['id'] ) && $elements[0]['id'] === $first ) {
			return array( $elements, 'done', 'Page already replaced.' );
		}
		return array( $c['elements'], 'applied', 'Page content replaced.' );
	}
}
